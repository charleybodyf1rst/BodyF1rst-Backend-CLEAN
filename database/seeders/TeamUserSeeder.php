<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TeamUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create team members for BodyF1rst
        $users = [
            [
                'email' => 'dustin@bodyf1rst.com',
                'username' => 'dustin',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'first_name' => 'Dustin',
                'last_name' => 'Combs',
                'email_verified_at' => now(),
            ],
            [
                'email' => 'chris@bodyf1rst.com',
                'username' => 'chris',
                'password' => Hash::make('password123'),
                'role' => 'coach',
                'first_name' => 'Chris',
                'last_name' => 'Vanberg',
                'email_verified_at' => now(),
            ],
            [
                'email' => 'ken@bodyf1rst.com',
                'username' => 'ken',
                'password' => Hash::make('password123'),
                'role' => 'coach',
                'first_name' => 'Ken',
                'last_name' => 'Laney',
                'email_verified_at' => now(),
            ],
            [
                'email' => 'charley@bodyf1rst.com',
                'username' => 'charley',
                'password' => Hash::make('password123'),
                'role' => 'developer',
                'first_name' => 'Charles',
                'last_name' => 'Blanchard',
                'email_verified_at' => now(),
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }

        $this->command->info('Team users created successfully!');
    }
}
