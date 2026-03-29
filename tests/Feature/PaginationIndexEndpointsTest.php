<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('paginates the products index and preserves the usage filter', function () {
    $this->withoutMiddleware();

    foreach (range(1, 6) as $number) {
        Product::query()->create([
            'sku' => sprintf('IN-%03d', $number),
            'name' => sprintf('Input Product %02d', $number),
            'unit' => 'kg',
            'category' => 'input',
            'description' => 'Input item',
        ]);

        Product::query()->create([
            'sku' => sprintf('BO-%03d', $number),
            'name' => sprintf('Both Product %02d', $number),
            'unit' => 'kg',
            'category' => 'both',
            'description' => 'Both item',
        ]);
    }

    Product::query()->create([
        'sku' => 'OUT-001',
        'name' => 'Output Product',
        'unit' => 'kg',
        'category' => 'output',
        'description' => 'Output item',
    ]);

    $response = $this->getJson('/api/products?usage=input&per_page=10&page=2');

    $response->assertOk();
    $response->assertJsonPath('per_page', 10);
    $response->assertJsonPath('current_page', 2);
    $response->assertJsonPath('total', 12);
    $response->assertJsonCount(2, 'data');
    $response->assertJsonPath('data.0.name', 'Input Product 05');
    $response->assertJsonPath('data.1.name', 'Input Product 06');
});

it('paginates the users index with audit-log style per_page values', function () {
    $this->withoutMiddleware();

    User::factory()->count(12)->sequence(
        ['name' => 'User 01', 'email' => 'user01@example.com'],
        ['name' => 'User 02', 'email' => 'user02@example.com'],
        ['name' => 'User 03', 'email' => 'user03@example.com'],
        ['name' => 'User 04', 'email' => 'user04@example.com'],
        ['name' => 'User 05', 'email' => 'user05@example.com'],
        ['name' => 'User 06', 'email' => 'user06@example.com'],
        ['name' => 'User 07', 'email' => 'user07@example.com'],
        ['name' => 'User 08', 'email' => 'user08@example.com'],
        ['name' => 'User 09', 'email' => 'user09@example.com'],
        ['name' => 'User 10', 'email' => 'user10@example.com'],
        ['name' => 'User 11', 'email' => 'user11@example.com'],
        ['name' => 'User 12', 'email' => 'user12@example.com'],
    )->create();

    $response = $this->getJson('/api/users?per_page=10&page=2');

    $response->assertOk();
    $response->assertJsonPath('per_page', 10);
    $response->assertJsonPath('current_page', 2);
    $response->assertJsonPath('total', 12);
    $response->assertJsonCount(2, 'data');
    $response->assertJsonPath('data.0.name', 'User 11');
    $response->assertJsonPath('data.1.name', 'User 12');
});

it('returns legacy collection payloads for roles and permissions when per_page is omitted', function () {
    $this->withoutMiddleware();

    Role::query()->create([
        'name' => 'admin',
        'guard_name' => 'api',
    ]);

    Role::query()->create([
        'name' => 'manager',
        'guard_name' => 'api',
    ]);

    Permission::query()->create([
        'name' => 'permissions.view',
        'guard_name' => 'api',
    ]);

    Permission::query()->create([
        'name' => 'roles.view',
        'guard_name' => 'api',
    ]);

    $rolesResponse = $this->getJson('/api/roles');
    $permissionsResponse = $this->getJson('/api/permissions');

    $rolesResponse->assertOk();
    $rolesResponse->assertJsonCount(2);
    $rolesResponse->assertJsonPath('0.name', 'admin');
    $rolesResponse->assertJsonPath('1.name', 'manager');

    $permissionsResponse->assertOk();
    $permissionsResponse->assertJsonCount(2);
    $permissionsResponse->assertJsonPath('0.name', 'permissions.view');
    $permissionsResponse->assertJsonPath('1.name', 'roles.view');
});

it('paginates the roles and permissions indexes when per_page is provided', function () {
    $this->withoutMiddleware();

    foreach (range(1, 12) as $number) {
        Role::query()->create([
            'name' => sprintf('role-%02d', $number),
            'guard_name' => 'api',
        ]);

        Permission::query()->create([
            'name' => sprintf('permission-%02d', $number),
            'guard_name' => 'api',
        ]);
    }

    $rolesResponse = $this->getJson('/api/roles?per_page=10&page=2');
    $permissionsResponse = $this->getJson('/api/permissions?per_page=10&page=2');

    $rolesResponse->assertOk();
    $rolesResponse->assertJsonPath('per_page', 10);
    $rolesResponse->assertJsonPath('current_page', 2);
    $rolesResponse->assertJsonPath('total', 12);
    $rolesResponse->assertJsonCount(2, 'data');
    $rolesResponse->assertJsonPath('data.0.name', 'role-11');
    $rolesResponse->assertJsonPath('data.1.name', 'role-12');

    $permissionsResponse->assertOk();
    $permissionsResponse->assertJsonPath('per_page', 10);
    $permissionsResponse->assertJsonPath('current_page', 2);
    $permissionsResponse->assertJsonPath('total', 12);
    $permissionsResponse->assertJsonCount(2, 'data');
    $permissionsResponse->assertJsonPath('data.0.name', 'permission-11');
    $permissionsResponse->assertJsonPath('data.1.name', 'permission-12');
});

it('rejects unsupported per_page values on list endpoints', function () {
    $this->withoutMiddleware();

    $response = $this->getJson('/api/products?per_page=15');

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['per_page']);
});
