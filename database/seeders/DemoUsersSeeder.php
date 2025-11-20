<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

/**
 * WARNING: FOR DEVELOPMENT AND DEMO PURPOSES ONLY
 * DO NOT run this seeder in production environments
 * This creates demo users with default passwords
 */
class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->warn('Skipping DemoUsersSeeder in production environment');
            return;
        }
        $users = [
            // YOUR NAMED ACCOUNTS (BODYF1RST DOMAIN)
            ['name' => 'Charley', 'email' => 'charley@bodyf1rst.com'],
            ['name' => 'Ken',     'email' => 'ken@bodyf1rst.com'],
            ['name' => 'Chris',   'email' => 'chris@bodyf1rst.com'],
            ['name' => 'Buddy',   'email' => 'buddy@bodyf1rst.com'],

            // EXTRA DEMO ACCOUNTS (GENERIC)
            ['name' => 'Alice Demo',   'email' => 'alice@demo.com'],
            ['name' => 'Bob Demo',     'email' => 'bob@demo.com'],
            ['name' => 'Dana Demo',    'email' => 'dana@demo.com'],
        ];

        foreach ($users as $u) {
            User::updateOrCreate(
                ['email' => $u['email']],
                ['name' => $u['name'], 'password' => Hash::make('password123')]
            );
        }
    }
}
