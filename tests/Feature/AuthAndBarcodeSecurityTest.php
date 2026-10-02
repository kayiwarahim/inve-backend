<?php

use App\Models\BarcodePrintLog;
use App\Models\Batch;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('requires authentication for protected inventory endpoints', function () {
    $this->getJson('/api/products')->assertUnauthorized();
});

it('rejects authenticated users without the required permission', function () {
    $user = User::query()->create([
        'name' => 'Limited Operator',
        'email' => 'limited-operator@example.com',
        'password' => bcrypt('password'),
    ]);

    Sanctum::actingAs($user);
    $this->actingAs($user, 'api');

    $this->getJson('/api/products')->assertForbidden();
});

it('allows authenticated users with the required permission', function () {
    $user = User::query()->create([
        'name' => 'Catalog Operator',
        'email' => 'catalog-operator@example.com',
        'password' => bcrypt('password'),
    ]);

    Permission::query()->create([
        'name' => 'products.view',
        'guard_name' => 'api',
    ]);
    $user->givePermissionTo('products.view');
    Sanctum::actingAs($user);
    $this->actingAs($user, 'api');

    $product = Product::query()->create([
        'sku' => 'AUTH-001',
        'name' => 'Authorized Product',
        'unit' => 'kg',
        'category' => 'both',
    ]);

    $response = $this->getJson('/api/products');

    $response
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $product->id);
});

it('requires permission middleware on every protected business route', function () {
    $profileRoutes = [
        'api/logout',
        'api/logout-all',
        'api/mfa/status',
        'api/mfa/setup',
        'api/mfa/confirm',
        'api/mfa/recovery-codes/regenerate',
        'api/mfa',
        'api/me',
        'api/profile/update',
        'api/profile/change-password',
    ];

    $routesWithoutPermissions = protectedApiRoutes()
        ->reject(fn ($route): bool => in_array($route->uri(), $profileRoutes, true))
        ->filter(fn ($route): bool => permissionNamesForRoute($route) === [])
        ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();

    expect($routesWithoutPermissions)->toBe([]);
});

it('seeds every permission used by protected routes', function () {
    $this->seed(RolePermissionSeeder::class);

    $routePermissionNames = protectedApiRoutes()
        ->flatMap(fn ($route): array => permissionNamesForRoute($route))
        ->unique()
        ->sort()
        ->values();

    $seededPermissionNames = Permission::query()
        ->where('guard_name', 'api')
        ->pluck('name');

    expect($routePermissionNames->diff($seededPermissionNames)->values()->all())->toBe([]);
});

it('rejects authenticated users without route permissions on readable protected endpoints', function (string $uri) {
    $user = User::query()->create([
        'name' => 'No Access Operator',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
    ]);

    Sanctum::actingAs($user);
    $this->actingAs($user, 'api');

    $this->getJson($uri)->assertForbidden();
})->with([
    'users index' => '/api/users',
    'roles index' => '/api/roles',
    'permissions index' => '/api/permissions',
    'products index' => '/api/products',
    'batches index' => '/api/batches',
    'warehouses index' => '/api/warehouses',
    'branches index' => '/api/branches',
    'suppliers index' => '/api/suppliers',
    'source lots index' => '/api/source-lots',
    'process steps index' => '/api/process-steps',
    'transformations index' => '/api/lot-transformations',
    'stock levels index' => '/api/stock-levels',
    'dashboard product stock' => '/api/dashboard/product-stock',
    'dashboard recent transfers' => '/api/dashboard/recent-transfers',
    'dashboard movement history' => '/api/dashboard/movement-history',
    'audit logs index' => '/api/auditlogs',
]);

it('rate limits public auth and password endpoints', function (string $uri) {
    $route = collect(Route::getRoutes())->first(function ($route) use ($uri): bool {
        return $route->uri() === $uri && in_array('POST', $route->methods(), true);
    });

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('throttle:5,1');
})->with([
    'register' => 'api/register',
    'login' => 'api/login',
    'request otp' => 'api/password/request-otp',
    'verify otp' => 'api/password/verify-otp',
    'reset password' => 'api/password/reset',
]);

it('does not grant admin role during public registration', function () {
    $response = $this->withHeader('Origin', 'http://localhost:5173')->postJson('/api/register', [
        'name' => 'New Operator',
        'email' => 'operator@example.com',
        'password' => 'password123',
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('user.role', null);

    $user = User::query()
        ->where('email', 'operator@example.com')
        ->firstOrFail();

    expect($user->hasRole('admin'))->toBeFalse();
});

it('records barcode print metadata for a batch', function () {
    $user = User::query()->create([
        'name' => 'Print Operator',
        'email' => 'print-operator@example.com',
        'password' => bcrypt('password'),
    ]);

    Permission::query()->create([
        'name' => 'batches.view',
        'guard_name' => 'api',
    ]);
    $user->givePermissionTo('batches.view');
    Sanctum::actingAs($user);
    $this->actingAs($user, 'api');

    $product = Product::query()->create([
        'sku' => 'PRINT-001',
        'name' => 'Packed Chicken',
        'unit' => 'kg',
        'category' => 'output',
    ]);

    $batch = Batch::query()->create([
        'product_id' => $product->id,
        'batch_code' => 'B-PRINT-001',
        'manufactured_at' => now()->toDateString(),
    ]);

    $response = $this->postJson('/api/batches/'.$batch->id.'/print', [
        'copies' => 3,
        'printer_name' => 'Label Station 1',
        'reason' => 'Replacement labels',
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('status', 'printed');

    $printLog = BarcodePrintLog::query()->firstOrFail();

    expect($printLog->batch_id)->toBe($batch->id)
        ->and($printLog->printed_by)->toBe($user->id)
        ->and($printLog->copies)->toBe(3)
        ->and($printLog->printer_name)->toBe('Label Station 1')
        ->and($printLog->reason)->toBe('Replacement labels');
});

function protectedApiRoutes(): Collection
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/'))
        ->filter(fn ($route): bool => in_array('auth:sanctum', $route->gatherMiddleware(), true))
        ->values();
}

function permissionNamesForRoute($route): array
{
    return collect($route->gatherMiddleware())
        ->filter(fn (string $middleware): bool => str_starts_with($middleware, 'permission:'))
        ->flatMap(function (string $middleware): array {
            $definition = str($middleware)
                ->after('permission:')
                ->beforeLast(',api')
                ->toString();

            return array_filter(explode('|', $definition));
        })
        ->values()
        ->all();
}
