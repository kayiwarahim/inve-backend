<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Create permissions with 'api' guard
        $permissions = [
            'products.view', 'products.create', 'products.edit', 'products.delete',
            'batches.view', 'batches.create', 'batches.edit', 'batches.delete',
            'warehouses.view', 'warehouses.create', 'warehouses.edit', 'warehouses.delete',
            'branches.view', 'branches.create', 'branches.edit', 'branches.delete',
            'stock.in', 'stock.out', 'stock.transfer', 'stock.reconcile',
            'reports.view', 'reports.export',
            'users.view', 'users.manage',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'api']);
        }

        // Create roles with 'api' guard and assign permissions
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $admin->syncPermissions($permissions); // Admin has all

        $warehouseManager = Role::firstOrCreate(['name' => 'warehouse manager', 'guard_name' => 'api']);
        $warehouseManager->syncPermissions([
            'products.view', 'products.create', 'products.edit', 'products.delete',
            'batches.view', 'batches.create', 'batches.edit', 'batches.delete',
            'warehouses.view', 'warehouses.create', 'warehouses.edit', 'warehouses.delete',
            'stock.in', 'stock.out', 'stock.transfer', 'stock.reconcile',
            'reports.view', 'reports.export',
        ]);

        $warehouseWorker = Role::firstOrCreate(['name' => 'warehouse worker', 'guard_name' => 'api']);
        $warehouseWorker->syncPermissions([
            'products.view', 'batches.view', 'warehouses.view',
            'stock.in', 'stock.out', 'stock.transfer',
        ]);

        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'api']);
        $manager->syncPermissions([
            'products.view', 'products.edit',
            'batches.view', 'batches.edit',
            'warehouses.view', 'warehouses.edit',
            'branches.view', 'branches.create', 'branches.edit', 'branches.delete',
            'stock.in', 'stock.out', 'stock.transfer', 'stock.reconcile',
            'reports.view', 'reports.export',
            'users.view',
        ]);

        // Optional: Assign admin to a test user
        // $testUser = User::where('email', 'kayiwarahim@gmail.com')->first();
        // if ($testUser) {
        //     $testUser->syncRoles('admin');
        // }
    }
}
