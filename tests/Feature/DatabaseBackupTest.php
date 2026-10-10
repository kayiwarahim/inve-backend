<?php

use App\Jobs\RunDatabaseOperation;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\MySqlBuilder;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

it('uses backup defaults when optional environment settings are blank', function () {
    $settings = [
        'DATABASE_BACKUP_QUEUE_CONNECTION',
        'DATABASE_BACKUP_DUMP_BINARY',
        'DATABASE_BACKUP_RESTORE_BINARY',
        'DATABASE_BACKUP_CONTROL_DATABASE',
        'DATABASE_BACKUP_MAX_UPLOAD_KB',
        'DATABASE_BACKUP_MAX_SQL_BYTES',
    ];
    $previous = [];
    try {
        foreach ($settings as $setting) {
            $previous[$setting] = [$_ENV[$setting] ?? null, $_SERVER[$setting] ?? null, getenv($setting)];
            $_ENV[$setting] = '';
            $_SERVER[$setting] = '';
            putenv($setting.'=');
        }
        $backups = require config_path('database-backups.php');
        $database = require config_path('database.php');
        expect($backups['max_sql_bytes'])->toBe(1073741824)
            ->and($backups['max_upload_kb'])->toBe(262144)
            ->and($backups['queue_connection'])->toBe('database-backups')
            ->and($backups['dump_binary'])->not->toBeEmpty()
            ->and($backups['restore_binary'])->not->toBeEmpty()
            ->and($database['connections']['backup-control']['database'])->toBe(storage_path('app/database-backups/queue.sqlite'));
    } finally {
        foreach ($previous as $setting => [$envValue, $serverValue, $processValue]) {
            if ($envValue === null) {
                unset($_ENV[$setting]);
            } else {
                $_ENV[$setting] = $envValue;
            }
            if ($serverValue === null) {
                unset($_SERVER[$setting]);
            } else {
                $_SERVER[$setting] = $serverValue;
            }
            putenv($processValue === false ? $setting : $setting.'='.$processValue);
        }
    }
});

beforeEach(function (): void {
    config(['database-backups.path' => storage_path('framework/testing/backups-'.Str::uuid())]);
    $this->backups = app(DatabaseBackupService::class);
    $this->backupUser = User::factory()->create();
    foreach (['backups.view', 'backups.create', 'backups.download', 'backups.restore'] as $permission) {
        Permission::findOrCreate($permission, 'api');
    }
});

afterEach(function (): void {
    File::deleteDirectory(config('database-backups.path'));
    config(['database.default' => 'sqlite']);
});

function authenticateBackupUser(User $user, array $permissions): void
{
    $user->givePermissionTo($permissions);
    Sanctum::actingAs($user, ['*', 'mfa:verified']);
    $user->withAccessToken(new PersonalAccessToken(['abilities' => ['*', 'mfa:verified']]));
    test()->actingAs($user, 'api');
}

function createTestBackup(): array
{
    return (new class(app(DatabaseManager::class), app(PermissionRegistrar::class)) extends DatabaseBackupService
    {
        public function create(): array
        {
            return $this->createArchive(1);
        }

        protected function dumpSql(string $path): void
        {
            file_put_contents($path, 'CREATE TABLE example (id INTEGER);');
        }
    })->create();
}

it('protects every database backup endpoint', function (string $method, string $uri) {
    $this->{$method}($uri)->assertUnauthorized();
    authenticateBackupUser($this->backupUser, []);
    $this->{$method}($uri)->assertForbidden();
})->with([
    ['getJson', '/api/database-backups'],
    ['postJson', '/api/database-backups'],
    ['postJson', '/api/database-backups/uploads'],
    ['getJson', '/api/database-backups/aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa/download'],
    ['postJson', '/api/database-backups/aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa/restore'],
    ['getJson', '/api/database-operations/aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa'],
]);

it('lists and downloads private backups and rejects invalid identifiers', function () {
    authenticateBackupUser($this->backupUser, ['backups.view', 'backups.download']);
    $backup = createTestBackup();
    $this->getJson('/api/database-backups')->assertSuccessful()->assertJsonPath('data.0.id', $backup['id']);
    $this->getJson('/api/database-backups/'.$backup['id'].'/download')
        ->assertDownload('database-backup-'.$backup['id'].'.zip');
    $this->getJson('/api/database-backups/../.env/download')->assertNotFound();
    $this->getJson('/api/database-backups/'.Str::uuid().'/download')->assertNotFound();
});

it('uploads a downloaded signed backup without restoring it', function () {
    Bus::fake();
    authenticateBackupUser($this->backupUser, ['backups.restore']);
    $backup = createTestBackup();
    $file = new UploadedFile($this->backups->archivePath($backup['id']), 'backup.zip', 'application/zip', null, true);
    $response = $this->postJson('/api/database-backups/uploads', ['backup' => $file]);
    $response->assertCreated()->assertJsonPath('data.source', 'upload');
    expect($response->json('data.id'))->not->toBe($backup['id']);
    Bus::assertNothingDispatched();
});

it('rejects unsigned SQL uploads and invalid ZIP files', function () {
    authenticateBackupUser($this->backupUser, ['backups.restore']);
    $this->postJson('/api/database-backups/uploads', ['backup' => UploadedFile::fake()->createWithContent('backup.sql', 'DROP TABLE users;')])
        ->assertUnprocessable()->assertJsonValidationErrors('backup');
    $this->postJson('/api/database-backups/uploads', ['backup' => UploadedFile::fake()->createWithContent('backup.zip', 'invalid')])
        ->assertUnprocessable()->assertJsonValidationErrors('backup');
});

it('rejects tampered, expanded, incompatible, and foreign-key backups', function (string $change) {
    $backup = createTestBackup();
    $path = $this->backups->archivePath($backup['id']);
    if ($change === 'key') {
        config(['app.key' => 'a-different-application-key']);
    } else {
        $zip = new ZipArchive;
        $zip->open($path);
        if ($change === 'sql') {
            $zip->addFromString('database.sql', 'DROP TABLE example;');
        } elseif ($change === 'entry') {
            $zip->addFromString('../outside.sql', 'DROP TABLE users;');
        } elseif ($change === 'limit') {
            config(['database-backups.max_sql_bytes' => 1]);
        } else {
            $envelope = json_decode($zip->getFromName('manifest.json'), true);
            $envelope['signature'] = str_repeat('0', 64);
            $zip->addFromString('manifest.json', json_encode($envelope));
        }
        $zip->close();
    }
    expect(fn () => $this->backups->validateArchive($path))->toThrow(ValidationException::class);
})->with(['sql', 'entry', 'limit', 'signature', 'key']);

it('requires MFA and password confirmation before restoration', function () {
    Bus::fake();
    $this->withoutMiddleware(ThrottleRequests::class);
    authenticateBackupUser($this->backupUser, ['backups.restore']);
    $uri = '/api/database-backups/'.Str::uuid().'/restore';
    $this->postJson($uri, ['password' => 'password', 'confirm' => true, 'writers_paused' => true])->assertForbidden();
    $user = User::factory()->withMfa()->create();
    authenticateBackupUser($user, ['backups.restore']);
    $this->postJson($uri, ['password' => 'wrong', 'confirm' => true, 'writers_paused' => true])
        ->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->postJson($uri, ['password' => 'password'])
        ->assertUnprocessable()->assertJsonValidationErrors(['confirm', 'writers_paused']);
    Bus::assertNothingDispatched();
});

it('queues backups independently and rejects concurrent operations', function () {
    Bus::fake();
    config(['database.default' => 'mysql', 'database-backups.connection' => 'mysql']);
    $operation = $this->backups->start('backup', $this->backupUser->id);
    expect($operation['status'])->toBe('pending');
    Bus::assertDispatched(RunDatabaseOperation::class, fn ($job): bool => $job->connection === 'database-backups' && $job->operationId === $operation['id']);
    try {
        $this->backups->start('backup', $this->backupUser->id);
        test()->fail('A concurrent operation was accepted.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(409);
    }
    $this->backups->fail($operation['id']);
    expect($this->backups->operation($operation['id'])['status'])->toBe('failed');
    expect(is_file($this->backups->path('active')))->toBeFalse();
});

it('refuses a queue stored in the target database', function () {
    Bus::fake();
    config(['database.default' => 'mysql', 'database-backups.connection' => 'mysql', 'database-backups.queue_connection' => 'database']);
    try {
        $this->backups->start('backup', 1);
        test()->fail('The unsafe queue was accepted.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(503);
    }
    Bus::assertNothingDispatched();
});

it('blocks traffic before authentication when restoration requires recovery', function () {
    file_put_contents($this->backups->path('restoring'), '{}');
    $this->getJson('/api/database-backups')->assertServiceUnavailable()->assertHeader('Retry-After', '30');
    $this->get('/')->assertServiceUnavailable();
});

it('requires explicit confirmation for server recovery', function () {
    $this->artisan('database-backups:restore', ['backup' => (string) Str::uuid(), '--no-interaction' => true])->assertFailed();
});

it('queues an explicitly confirmed restoration and exposes its operation status', function () {
    Bus::fake();
    $user = User::factory()->withMfa()->create();
    authenticateBackupUser($user, ['backups.restore', 'backups.view']);
    config(['database.default' => 'mysql', 'database-backups.connection' => 'mysql']);
    Event::fake([RequestHandled::class]);
    $backup = createTestBackup();
    $response = $this->postJson('/api/database-backups/'.$backup['id'].'/restore', [
        'password' => 'password', 'confirm' => true, 'writers_paused' => true,
    ])->assertAccepted()->assertJsonPath('data.type', 'restore');
    $id = $response->json('data.id');
    $this->getJson('/api/database-operations/'.$id)->assertSuccessful()->assertJsonPath('data.status', 'pending');
    Bus::assertDispatched(RunDatabaseOperation::class, fn ($job): bool => $job->operationId === $id);
});

it('preserves a recovery backup and keeps traffic blocked after a destructive restore failure', function (string $stage) {
    Bus::fake();
    config(['database.default' => 'mysql', 'database-backups.connection' => 'mysql']);
    $backup = createTestBackup();
    $operation = $this->backups->start('restore', 1, $backup['id']);
    $service = Mockery::mock(DatabaseBackupService::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $schema = Mockery::mock(MySqlBuilder::class);
    $connection = Mockery::mock(MySqlConnection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getSchemaBuilder')->andReturn($schema);
    $service->shouldReceive('connection')->andReturn($connection);
    if ($stage === 'safety') {
        $service->shouldReceive('createArchive')->once()->andThrow(new RuntimeException('Dump failed'));
        $schema->shouldNotReceive('dropAllTables');
    } else {
        $service->shouldReceive('createArchive')->once()->andReturn(['id' => 'recovery-backup-id']);
        $schema->shouldReceive('dropAllViews')->once();
        $schema->shouldReceive('dropAllTables')->once();
        $service->shouldReceive('importSql')->once()->andThrow(new RuntimeException('Import failed'));
    }
    $service->shouldNotReceive('invalidateAuthentication');
    expect(fn () => $service->run($operation['id']))->toThrow(RuntimeException::class);
    $result = $service->operation($operation['id']);
    expect($result['status'])->toBe('failed');
    expect(is_file($service->path('restoring')))->toBe($stage === 'import');
    expect(is_file($service->path('active')))->toBeFalse();
    if ($stage === 'import') {
        expect($result['recovery_backup_id'])->toBe('recovery-backup-id');
    }
    expect(glob($service->path('*.sql')))->toBe([]);
})->with(['safety', 'import']);

it('finishes restoration only after authentication invalidation succeeds', function () {
    Bus::fake();
    config(['database.default' => 'mysql', 'database-backups.connection' => 'mysql']);
    $backup = createTestBackup();
    $operation = $this->backups->start('restore', 1, $backup['id']);
    $service = Mockery::mock(DatabaseBackupService::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $schema = Mockery::mock(MySqlBuilder::class);
    $connection = Mockery::mock(MySqlConnection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getSchemaBuilder')->andReturn($schema);
    $service->shouldReceive('connection')->andReturn($connection);
    $service->shouldReceive('createArchive')->once()->andReturn(['id' => 'recovery-backup-id']);
    $schema->shouldReceive('dropAllViews')->once()->ordered();
    $schema->shouldReceive('dropAllTables')->once()->ordered();
    $service->shouldReceive('importSql')->once()->with(Mockery::on(fn (string $path): bool => file_get_contents($path) === 'CREATE TABLE example (id INTEGER);'));
    $service->shouldReceive('invalidateAuthentication')->once();
    $service->run($operation['id']);
    expect($service->operation($operation['id'])['status'])->toBe('completed');
    expect(is_file($service->path('restoring')))->toBeFalse();
    expect(is_file($service->path('active')))->toBeFalse();
    $service->run($operation['id']);
});

it('does not fail a running operation when a duplicate worker is rejected', function () {
    Bus::fake();
    config(['database.default' => 'mysql', 'database-backups.connection' => 'mysql']);
    $operation = $this->backups->start('backup', 1);
    $lock = fopen($this->backups->path('operation.lock'), 'c+');
    flock($lock, LOCK_EX);
    try {
        $this->backups->fail($operation['id']);
        expect($this->backups->operation($operation['id'])['status'])->toBe('pending');
        expect(is_file($this->backups->path('active')))->toBeTrue();
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
});

it('cleans up a failed queue dispatch without exposing database credentials', function () {
    config(['database.default' => 'mysql', 'database-backups.connection' => 'mysql']);
    Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Sensitive client details'));
    try {
        $this->backups->start('backup', 1);
        test()->fail('Queue dispatch should fail.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(503);
        expect($exception->getMessage())->not->toContain('Sensitive');
    }
    expect(is_file($this->backups->path('active')))->toBeFalse();
});

it('persists queued jobs in the independent SQLite control database', function () {
    (require database_path('migrations/2026_10_09_184932_create_database_backup_queue.php'))->up();
    config(['database.default' => 'mysql', 'database-backups.connection' => 'mysql']);
    $operation = $this->backups->start('backup', 1);
    $queue = app(DatabaseManager::class)->connection('backup-control');
    try {
        $job = $queue->table('jobs')->where('queue', 'database-backups')->first();
        expect($job)->not->toBeNull();
        expect($job->payload)->toContain($operation['id']);
        expect($queue->getDriverName())->toBe('sqlite');
    } finally {
        $queue->table('jobs')->delete();
        $this->backups->fail($operation['id']);
    }
});
