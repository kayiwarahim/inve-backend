<?php

namespace App\Services;

use App\Jobs\RunDatabaseOperation;
use App\Models\PasswordOtp;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class DatabaseBackupService
{
    public function __construct(private DatabaseManager $database, private PermissionRegistrar $permissions) {}

    public function path(string $filename): string
    {
        $root = config('database-backups.path');
        if (! is_dir($root) && ! mkdir($root, 0700, true) && ! is_dir($root)) {
            throw new RuntimeException('Backup storage is unavailable.');
        }

        return $root.DIRECTORY_SEPARATOR.$filename;
    }

    public function backups(): array
    {
        $records = [];
        foreach (glob($this->path('backup-*.json')) ?: [] as $path) {
            $records[] = $this->read($path);
        }
        usort($records, fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));

        return $records;
    }

    public function operation(string $id): array
    {
        $this->validateId($id);

        return $this->read($this->path('operation-'.$id.'.json'));
    }

    public function archivePath(string $id): string
    {
        $this->validateId($id);
        $this->read($this->path('backup-'.$id.'.json'));
        $path = $this->path($id.'.zip');
        abort_unless(is_file($path), 404, 'Backup archive not found.');

        return $path;
    }

    public function start(string $type, int $userId, ?string $backupId = null): array
    {
        $this->assertConfigured();
        if ($type === 'restore') {
            $this->validateArchive($this->archivePath($backupId));
        }

        $operation = $this->reserve($type, $userId, $backupId);
        try {
            Bus::dispatch((new RunDatabaseOperation($operation['id']))
                ->onConnection(config('database-backups.queue_connection'))
                ->onQueue(config('database-backups.queue')));
        } catch (Throwable) {
            $this->fail($operation['id']);
            abort(503, 'The database operation could not be queued.');
        }

        return $operation;
    }

    public function upload(UploadedFile $file, int $userId): array
    {
        $this->validateArchive($file->getPathname());
        $id = (string) Str::uuid();
        if (! copy($file->getPathname(), $this->path($id.'.zip'))) {
            throw new RuntimeException('Could not store the uploaded backup.');
        }
        chmod($this->path($id.'.zip'), 0600);

        return $this->recordBackup($id, $userId, 'upload');
    }

    public function validateArchive(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            abort(503, 'Enable the PHP ZIP extension to use database backups.');
        }
        $zip = new ZipArchive;
        $valid = false;
        $opened = false;
        try {
            if ($zip->open($path) !== true) {
                throw new RuntimeException;
            }
            $opened = true;
            $manifestStat = $zip->statName('manifest.json');
            $sqlStat = $zip->statName('database.sql');
            if ($zip->numFiles !== 2 || ! $manifestStat || ! $sqlStat
                || $manifestStat['size'] > 16384 || $sqlStat['size'] < 1
                || $sqlStat['size'] > config('database-backups.max_sql_bytes')) {
                throw new RuntimeException;
            }
            $envelope = json_decode($zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
            $payload = $envelope['payload'] ?? null;
            $signature = $envelope['signature'] ?? null;
            if (! is_string($payload) || ! is_string($signature)
                || ! hash_equals(hash_hmac('sha256', $payload, $this->signingKey()), $signature)) {
                throw new RuntimeException;
            }
            $manifest = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
            if (($manifest['version'] ?? null) !== 1
                || ($manifest['schema'] ?? null) !== $this->schemaFingerprint()
                || ($manifest['driver'] ?? null) !== $this->connection()->getDriverName()
                || ($manifest['bytes'] ?? null) !== $sqlStat['size']) {
                throw new RuntimeException;
            }
            $stream = $zip->getStream('database.sql');
            if ($stream === false) {
                throw new RuntimeException;
            }
            try {
                $hash = hash_init('sha256');
                $bytes = hash_update_stream($hash, $stream, $manifest['bytes'] + 1);
                $valid = $bytes === $manifest['bytes'] && hash_equals($manifest['sha256'], hash_final($hash));
            } finally {
                fclose($stream);
            }
        } catch (Throwable) {
            $valid = false;
        } finally {
            if ($opened) {
                $zip->close();
            }
        }
        if (! $valid) {
            throw ValidationException::withMessages(['backup' => 'The backup is invalid, modified, or incompatible with this application and encryption key.']);
        }

        return $manifest;
    }

    public function run(string $id): void
    {
        $operation = $this->operation($id);
        if ($operation['status'] !== 'pending') {
            return;
        }
        $lock = fopen($this->path('operation.lock'), 'c+');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new RuntimeException('Another database operation is running.');
        }
        $traffic = null;
        $destructive = false;
        try {
            $operation = $this->operation($id);
            if ($operation['status'] !== 'pending') {
                return;
            }
            $this->assertConfigured();
            $operation['status'] = 'running';
            $operation['started_at'] = now()->toIso8601String();
            $this->writeOperation($operation);
            if ($operation['type'] === 'backup') {
                $backup = $this->createArchive($operation['user_id']);
                $operation['backup_id'] = $backup['id'];
            } else {
                $archive = $this->archivePath($operation['backup_id']);
                $this->validateArchive($archive);
                $this->write($this->path('restoring'), ['operation_id' => $id]);
                $traffic = fopen($this->path('traffic.lock'), 'c+');
                if ($traffic === false || ! flock($traffic, LOCK_EX)) {
                    throw new RuntimeException('Could not pause HTTP traffic.');
                }
                $safety = $this->createArchive($operation['user_id'], 'recovery');
                $operation['recovery_backup_id'] = $safety['id'];
                $operation['stage'] = 'restoring';
                $this->writeOperation($operation);
                $sql = $this->extractSql($archive);
                try {
                    $destructive = true;
                    $schema = $this->connection()->getSchemaBuilder();
                    $schema->dropAllViews();
                    $schema->dropAllTables();
                    $this->importSql($sql);
                    $this->invalidateAuthentication();
                } finally {
                    if (is_file($sql)) {
                        unlink($sql);
                    }
                }
                unlink($this->path('restoring'));
            }
            $operation['status'] = 'completed';
            $operation['completed_at'] = now()->toIso8601String();
            $this->writeOperation($operation);
        } catch (Throwable) {
            if (! $destructive && is_file($this->path('restoring'))) {
                unlink($this->path('restoring'));
            }
            $this->fail($id, true);
            throw new RuntimeException('Database operation failed. Check the operation record; restore failures may require recovery.');
        } finally {
            if (is_resource($traffic)) {
                flock($traffic, LOCK_UN);
                fclose($traffic);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
            $this->release($id);
        }
    }

    public function fail(string $id, bool $ownsLock = false): void
    {
        $lock = null;
        if (! $ownsLock) {
            $lock = fopen($this->path('operation.lock'), 'c+');
            if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
                if (is_resource($lock)) {
                    fclose($lock);
                }

                return;
            }
        }
        try {
            $operation = $this->operation($id);
            if ($operation['status'] === 'completed') {
                return;
            }
            $operation['status'] = 'failed';
            $operation['message'] = 'Operation failed. If restoration started, use the recovery backup from the server before reopening the application.';
            $operation['completed_at'] = now()->toIso8601String();
            $this->writeOperation($operation);
            $this->release($id);
        } finally {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    public function recover(string $backupId): array
    {
        $this->assertConfigured();
        $this->validateArchive($this->archivePath($backupId));
        $lock = fopen($this->path('operation.lock'), 'c+');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('A database operation is still running.');
        }
        try {
            $this->write($this->path('restoring'), ['recovery_backup_id' => $backupId]);
            if (is_file($this->path('active'))) {
                $activeId = file_get_contents($this->path('active'));
                if (Str::isUuid($activeId) && is_file($this->path('operation-'.$activeId.'.json'))) {
                    $this->fail($activeId, true);
                }
            }
            $traffic = fopen($this->path('traffic.lock'), 'c+');
            if ($traffic === false || ! flock($traffic, LOCK_EX)) {
                throw new RuntimeException('Could not pause HTTP traffic.');
            }
            try {
                $sql = $this->extractSql($this->archivePath($backupId));
                try {
                    $this->connection()->getSchemaBuilder()->dropAllViews();
                    $this->connection()->getSchemaBuilder()->dropAllTables();
                    $this->importSql($sql);
                    $this->invalidateAuthentication();
                } finally {
                    unlink($sql);
                }
                unlink($this->path('restoring'));
                if (is_file($this->path('active'))) {
                    unlink($this->path('active'));
                }
            } finally {
                flock($traffic, LOCK_UN);
                fclose($traffic);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return ['backup_id' => $backupId, 'status' => 'completed'];
    }

    protected function createArchive(int $userId, string $source = 'server'): array
    {
        $id = (string) Str::uuid();
        $sql = $this->path($id.'.sql');
        $archive = $this->path($id.'.zip');
        try {
            $this->dumpSql($sql);
            $size = filesize($sql);
            if (! $size || $size > config('database-backups.max_sql_bytes')) {
                throw new RuntimeException('The database dump exceeds the configured size limit.');
            }
            $payload = json_encode([
                'version' => 1,
                'driver' => $this->connection()->getDriverName(),
                'schema' => $this->schemaFingerprint(),
                'created_at' => now()->toIso8601String(),
                'sha256' => hash_file('sha256', $sql),
                'bytes' => $size,
            ], JSON_THROW_ON_ERROR);
            $zip = new ZipArchive;
            if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new RuntimeException('Could not create the backup archive.');
            }
            $added = $zip->addFile($sql, 'database.sql') && $zip->addFromString('manifest.json', json_encode([
                'payload' => $payload,
                'signature' => hash_hmac('sha256', $payload, $this->signingKey()),
            ], JSON_THROW_ON_ERROR));
            $closed = $zip->close();
            if (! $added || ! $closed) {
                throw new RuntimeException('Could not complete the backup archive.');
            }
            chmod($archive, 0600);

            return $this->recordBackup($id, $userId, $source);
        } catch (Throwable $exception) {
            if (is_file($archive)) {
                unlink($archive);
            }
            throw $exception;
        } finally {
            if (is_file($sql)) {
                unlink($sql);
            }
        }
    }

    protected function dumpSql(string $path): void
    {
        $this->assertSchemaIsCurrent();
        foreach ($this->connection()->getSchemaBuilder()->getTables() as $table) {
            if (strcasecmp($table['engine'], 'InnoDB') !== 0) {
                throw new RuntimeException('Consistent backups require InnoDB tables.');
            }
        }
        $arguments = ['--single-transaction', '--quick', '--routines', '--triggers', '--events', '--hex-blob', '--skip-lock-tables', '--result-file='.$path];
        $this->execute('dump_binary', $arguments);
    }

    protected function importSql(string $path): void
    {
        $stream = fopen($path, 'rb');
        try {
            $this->execute('restore_binary', ['--binary-mode', '--local-infile=0', '--skip-reconnect'], $stream);
        } finally {
            fclose($stream);
        }
    }

    private function execute(string $binary, array $arguments, mixed $input = null): void
    {
        $config = $this->connection()->getConfig();
        $options = ['--host='.$config['host'], '--port='.$config['port'], '--user='.$config['username'], '--default-character-set=utf8mb4'];
        if (! empty($config['unix_socket'])) {
            $options[] = '--socket='.$config['unix_socket'];
        }
        $process = new Process([config('database-backups.'.$binary), ...$options, ...$arguments, '--', $config['database']], env: ['MYSQL_PWD' => (string) $config['password']], input: $input, timeout: config('database-backups.process_timeout'));
        $process->disableOutput();
        try {
            if ($process->run() !== 0) {
                throw new RuntimeException;
            }
        } catch (Throwable) {
            throw new RuntimeException('The database client failed. Check binary configuration, database privileges, and available disk space.');
        }
    }

    private function extractSql(string $path): string
    {
        $manifest = $this->validateArchive($path);
        $target = $this->path((string) Str::uuid().'.sql');
        $zip = new ZipArchive;
        $zip->open($path);
        $stream = $zip->getStream('database.sql');
        $output = fopen($target, 'xb');
        $complete = false;
        try {
            if ($stream === false || $output === false
                || stream_copy_to_stream($stream, $output, $manifest['bytes'] + 1) !== $manifest['bytes']
                || ! fflush($output)
                || ! hash_equals($manifest['sha256'], hash_file('sha256', $target))) {
                throw new RuntimeException('Could not stage the database restore.');
            }
            chmod($target, 0600);
            $complete = true;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (is_resource($output)) {
                fclose($output);
            }
            $zip->close();
            if (! $complete && is_file($target)) {
                unlink($target);
            }
        }

        return $target;
    }

    protected function invalidateAuthentication(): void
    {
        $this->database->purge(config('database-backups.connection'));
        $this->assertSchemaIsCurrent();
        $this->connection()->getSchemaBuilder()->getColumns('users');
        PersonalAccessToken::query()->delete();
        PasswordOtp::query()->delete();
        $this->connection()->table('sessions')->delete();
        $this->connection()->table('password_reset_tokens')->delete();
        User::query()->eachById(function (User $user): void {
            $user->forceFill([
                'remember_token' => Str::random(60),
                'mfa_challenge_hash' => null,
                'mfa_challenge_expires_at' => null,
                'mfa_challenge_attempts' => 0,
                'mfa_pending_secret' => null,
                'mfa_pending_expires_at' => null,
            ])->save();
        });
        Cache::flush();
        $this->permissions->forgetCachedPermissions();
    }

    private function assertConfigured(): void
    {
        $driver = $this->connection()->getDriverName();
        abort_unless(in_array($driver, ['mysql', 'mariadb'], true), 503, 'Database backups require MySQL or MariaDB.');
        abort_unless(class_exists(ZipArchive::class), 503, 'Enable the PHP ZIP extension.');
        abort_unless(config('database-backups.connection') === config('database.default'), 503, 'Backups must target the application database.');
        $queueDriver = config('queue.connections.'.config('database-backups.queue_connection').'.driver');
        $controlQueue = $queueDriver === 'database'
            && config('queue.connections.'.config('database-backups.queue_connection').'.connection') === 'backup-control'
            && config('database.connections.backup-control.driver') === 'sqlite';
        abort_unless($controlQueue || in_array($queueDriver, ['redis', 'sqs', 'beanstalkd'], true), 503, 'Backups require a durable queue independent of the application database.');
        $retryAfter = config('queue.connections.'.config('database-backups.queue_connection').'.retry_after');
        abort_if($retryAfter !== null && $retryAfter <= 3600, 503, 'The backup queue retry_after must exceed 3600 seconds.');
    }

    protected function connection(): Connection
    {
        return $this->database->connection(config('database-backups.connection'));
    }

    private function signingKey(): string
    {
        $key = config('app.key');
        if (! is_string($key) || $key === '') {
            throw new RuntimeException('The application encryption key is missing.');
        }

        return hash_hmac('sha256', 'database-backup-v1', $key, true);
    }

    private function schemaFingerprint(): string
    {
        $files = glob(database_path('migrations/*.php')) ?: [];
        sort($files);

        return hash('sha256', implode('', array_map(fn (string $path): string => basename($path).hash_file('sha256', $path), $files)));
    }

    private function assertSchemaIsCurrent(): void
    {
        $expected = array_map(fn (string $path): string => basename($path, '.php'), glob(database_path('migrations/*.php')) ?: []);
        sort($expected);
        $actual = $this->connection()->table('migrations')->orderBy('migration')->pluck('migration')->all();
        if ($expected !== $actual) {
            throw new RuntimeException('Apply all application migrations before backing up or restoring.');
        }
    }

    private function reserve(string $type, int $userId, ?string $backupId): array
    {
        abort_if(is_file($this->path('restoring')), 409, 'Database recovery is required.');
        $active = @fopen($this->path('active'), 'x');
        abort_if($active === false, 409, 'Another database operation is pending or running.');
        $operation = [
            'id' => (string) Str::uuid(),
            'type' => $type,
            'status' => 'pending',
            'user_id' => $userId,
            'backup_id' => $backupId,
            'created_at' => now()->toIso8601String(),
        ];
        try {
            fwrite($active, $operation['id']);
            $this->writeOperation($operation);
        } catch (Throwable $exception) {
            unlink($this->path('active'));
            throw $exception;
        } finally {
            fclose($active);
        }

        return $operation;
    }

    private function release(string $id): void
    {
        $path = $this->path('active');
        if (is_file($path) && file_get_contents($path) === $id) {
            unlink($path);
        }
    }

    private function recordBackup(string $id, int $userId, string $source): array
    {
        $record = ['id' => $id, 'source' => $source, 'user_id' => $userId, 'created_at' => now()->toIso8601String(), 'size_bytes' => filesize($this->path($id.'.zip'))];
        $this->write($this->path('backup-'.$id.'.json'), $record);

        return $record;
    }

    private function writeOperation(array $operation): void
    {
        $this->write($this->path('operation-'.$operation['id'].'.json'), $operation);
    }

    private function write(string $path, array $data): void
    {
        $temporary = $path.'.'.Str::random(12).'.tmp';
        if (file_put_contents($temporary, json_encode($data, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            throw new RuntimeException('Could not save backup metadata.');
        }
        chmod($temporary, 0600);
        if (! rename($temporary, $path)) {
            unlink($temporary);
            throw new RuntimeException('Could not publish backup metadata.');
        }
    }

    private function read(string $path): array
    {
        abort_unless(is_file($path), 404, 'Backup or operation not found.');

        return json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    private function validateId(string $id): void
    {
        abort_unless(Str::isUuid($id), 404);
    }
}
