<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * SECURITY: Only legitimate admin account - Charles Blanchard
     *
     * @return void
     */
    public function run()
    {
        // Main admin account - Charles Blanchard
        Admin::create([
            'name' => 'Charles Blanchard',
            'email' => 'charlesblanchard85@gmail.com',
            'password' => 'Ryan238@',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Coaches Dashboard Admin - Charley
        Admin::create([
            'first_name' => 'Charley',
            'last_name' => 'BodyF1rst',
            'email' => 'Charley@bodyf1rst.com',
            'password' => 'Password123!',
            'role' => 'admin',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
