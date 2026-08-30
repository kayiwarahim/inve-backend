<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class BranchAndWarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create branches
        $branch = [
            [
                'name' => 'Kigunga Factory',
                'code' => 'KGF',
                'location' => 'Kigunga, Mukono District',
            ],
            [
                'name' => 'Ntinda Depot',
                'code' => 'NTD',
                'location' => 'Ntinda, Kampala District',
            ],
            [
                'name' => 'Industrial Area Depot',
                'code' => 'IAD',
                'location' => '5th Street, Kampala District',
            ],
        ];

        $warehouses = [
            [
                'branch_id' => 1,
                'name' => 'Live Store Room',
                'code' => 'KGF-WH',
                'type' => 'storage',
            ],
            [
                'branch_id' => 1,
                'name' => 'EV Room',
                'code' => 'KGF-EV',
                'type' => 'storage',
            ],
            [
                'branch_id' => 1,
                'name' => 'Spin Chiller Room',
                'code' => 'KGF-SC',
                'type' => 'storage',
            ],
            [
                'branch_id' => 1,
                'name' => 'Production Room',
                'code' => 'KGF-PR',
                'type' => 'storage',
            ],
            [
                'branch_id' => 1,
                'name' => 'Blast Freezer Room',
                'code' => 'KGF-BF',
                'type' => 'storage',
            ],
            [
                'branch_id' => 1,
                'name' => 'Cold Room',
                'code' => 'KGF-CR',
                'type' => 'storage',
            ],
        ];

        foreach ($branch as $branchData) {
            Branch::create($branchData);
        }

        foreach ($warehouses as $warehouseData) {
            Warehouse::create($warehouseData);
        }
    }
}
