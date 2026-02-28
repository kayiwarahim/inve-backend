<?php

namespace Database\Seeders;

use App\Models\ProcessStep;
use Illuminate\Database\Seeder;

class ProcessStepSeeder extends Seeder
{
    public function run(): void
    {
        $steps = [
            ['code' => 'SLAUGHTER', 'name' => 'Slaughter', 'sequence' => 10],
            ['code' => 'EV_ROOM', 'name' => 'EV Room', 'sequence' => 20],
            ['code' => 'SPIN_CHILLER', 'name' => 'Spin Chiller', 'sequence' => 30],
            ['code' => 'PRODUCTION_SORTING', 'name' => 'Production Sorting', 'sequence' => 40],
            ['code' => 'CUTTING', 'name' => 'Cutting', 'sequence' => 50],
            ['code' => 'MIX_BUILD', 'name' => 'Mixed Portions Build', 'sequence' => 60],
            ['code' => 'BAGGING', 'name' => 'Bagging', 'sequence' => 70],
            ['code' => 'COLD_ROOM', 'name' => 'Cold Room', 'sequence' => 80],
            ['code' => 'DISPATCH', 'name' => 'Dispatch', 'sequence' => 90],
        ];

        foreach ($steps as $step) {
            ProcessStep::query()->updateOrCreate(
                ['code' => $step['code']],
                [
                    'name' => $step['name'],
                    'sequence' => $step['sequence'],
                    'is_active' => true,
                ]
            );
        }
    }
}
