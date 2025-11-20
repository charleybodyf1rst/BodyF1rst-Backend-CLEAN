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
        Admin::create([
            'name' => 'Charles Blanchard',
            'email' => 'charlesblanchard85@gmail.com',
            'password' => bcrypt('Fighter@5224!'),
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
