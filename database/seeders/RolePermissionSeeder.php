<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // All permissions used in routes
        $permissions = [
            'backups.view', 'backups.create', 'backups.download', 'backups.restore',
            // Products
            'products.view', 'products.create', 'products.edit', 'products.delete',

            // Batches
            'batches.view', 'batches.create', 'batches.edit', 'batches.delete',

            // Warehouses
            'warehouses.view', 'warehouses.create', 'warehouses.edit', 'warehouses.delete',

            // Branches
            'branches.view', 'branches.create', 'branches.edit', 'branches.delete',

            // Suppliers
            'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',

            // Source Lots
            'source-lots.view', 'source-lots.create', 'source-lots.edit', 'source-lots.delete',

            // Process Steps
            'process-steps.view', 'process-steps.create', 'process-steps.edit', 'process-steps.delete',

            // Transformations
            'transformations.view', 'transformations.create',

            // Traceability
            'traceability.view',

            // Stock
            'stock.in', 'stock.out', 'stock.transfer', 'stock.reconcile', 'stock.view',

            // Reports
            'reports.view', 'reports.export',

            // Users
            'users.view', 'users.manage',

            // Roles
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete',

            // Permissions
            'permissions.view', 'permissions.create', 'permissions.edit', 'permissions.delete',
        ];

        // Create permissions
        foreach ($permissions as $perm) {
            Permission::firstOrCreate([
                'name' => $perm,
                'guard_name' => 'api',
            ]);
        }

        // Create roles
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $warehouseManager = Role::firstOrCreate(['name' => 'warehouse manager', 'guard_name' => 'api']);
        $warehouseWorker = Role::firstOrCreate(['name' => 'warehouse worker', 'guard_name' => 'api']);
        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'api']);

        // Admin gets ALL permissions
        $admin->syncPermissions(Permission::where('guard_name', 'api')->pluck('name')->toArray());

        // Warehouse Manager
        $warehouseManager->syncPermissions([
            'products.view', 'products.create', 'products.edit', 'products.delete',
            'batches.view', 'batches.create', 'batches.edit', 'batches.delete',
            'warehouses.view', 'warehouses.create', 'warehouses.edit', 'warehouses.delete',
            'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
            'source-lots.view', 'source-lots.create', 'source-lots.edit', 'source-lots.delete',
            'process-steps.view', 'process-steps.create', 'process-steps.edit', 'process-steps.delete',
            'transformations.view', 'transformations.create',
            'traceability.view',
            'stock.in', 'stock.out', 'stock.transfer', 'stock.reconcile',
            'reports.view', 'reports.export',
        ]);

        // Warehouse Worker
        $warehouseWorker->syncPermissions([
            'products.view', 'batches.view', 'warehouses.view',
            'process-steps.view', 'source-lots.view', 'source-lots.create',
            'transformations.view', 'transformations.create',
            'stock.in', 'stock.out', 'stock.transfer',
        ]);

        // Manager
        $manager->syncPermissions([
            'products.view', 'products.edit',
            'batches.view', 'batches.edit',
            'warehouses.view', 'warehouses.edit',
            'branches.view', 'branches.create', 'branches.edit', 'branches.delete',
            'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
            'source-lots.view', 'source-lots.create', 'source-lots.edit', 'source-lots.delete',
            'process-steps.view', 'process-steps.create', 'process-steps.edit', 'process-steps.delete',
            'transformations.view', 'transformations.create',
            'traceability.view',
            'stock.in', 'stock.out', 'stock.transfer', 'stock.reconcile',
            'reports.view', 'reports.export',
            'users.view',
        ]);
    }
}
