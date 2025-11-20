<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

/**
 * WARNING: FOR TESTING PURPOSES ONLY
 * DO NOT run this seeder in production environments
 * Creates a test user with a known password
 */
class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->warn('Skipping TestUserSeeder in production environment');
            return;
        }
        // Create a simple test user for auth testing
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('secret123'),
                'is_active' => 1,
                'email_verified_at' => now(),
            ]
        );

        echo "Test user created: test@example.com / secret123\n";
    }
}
