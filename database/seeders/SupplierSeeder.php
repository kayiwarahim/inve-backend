<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'name' => 'Sikyomu Farm',
                'code' => 'SUP-A',
                'phone' => '+25671234567',
                'email' => 'a9A9o@example.com',
                'address' => 'Kyanuna Hoima Road',
                'is_active' => true,
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::create($supplier);
        }
    }
}
