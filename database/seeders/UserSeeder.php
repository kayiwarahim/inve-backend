<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // create a demo user
        User::factory()->create([
            'name' => 'Kayiwa Rahim',
            'email' => 'kayiwarahim@gmail.com',
            'password' => bcrypt('Admin@1234'),
            'phone' => '+256752225375',
        ]);
    }
}
