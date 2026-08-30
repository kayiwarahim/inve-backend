<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            [
                'name' => 'Live chicken',
                'sku' => 'LVC',
                'unit' => 'kg',
                'category' => 'input',
                'description' => 'Live chicken',
            ],
            [
                'name' => 'Wings',
                'sku' => 'WNG',
                'unit' => 'kg',
                'category' => 'both',
                'description' => 'Wings',
            ],
            [
                'name' => 'Thighs',
                'sku' => 'THG',
                'unit' => 'kg',
                'category' => 'both',
                'description' => 'Thighs',
            ],
            [
                'name' => 'Breast fillet',
                'sku' => 'BRF',
                'unit' => 'kg',
                'category' => 'both',
                'description' => 'Breast fillet',
            ],
            [
                'name' => 'Drumsticks',
                'sku' => 'DRM',
                'unit' => 'kg',
                'category' => 'both',
                'description' => 'Drumsticks',
            ],
            [
                'name' => 'Mixed Portions',
                'sku' => 'MIX',
                'unit' => 'kg',
                'category' => 'both',
                'description' => 'Mixed Portions',
            ],
            [
                'name' => 'Waste',
                'sku' => 'WST',
                'unit' => 'kg',
                'category' => 'output',
                'description' => 'Waste',
            ]
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}
