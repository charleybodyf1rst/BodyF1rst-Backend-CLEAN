<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CharleyUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates Charley user account for Mobile App
     *
     * @return void
     */
    public function run()
    {
        // Mobile App User - Charley
        User::updateOrCreate(
            ['email' => 'Charley@bodyf1rst.com'],
            [
                'first_name' => 'Charley',
                'last_name' => 'BodyF1rst',
                'email' => 'Charley@bodyf1rst.com',
                'password' => 'Password123!',
                'phone' => '',
                'gender' => 'other',
                'age' => 30,
                'weight' => 180,
                'height' => 70,
                'goal' => 'maintenance',
                'activity_level' => 'moderately_active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        echo "Charley user account created: Charley@bodyf1rst.com / Password123!\n";
    }
}
