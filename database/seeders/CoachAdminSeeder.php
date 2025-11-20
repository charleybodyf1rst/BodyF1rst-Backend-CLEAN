<?php

namespace Database\Seeders;

use App\Models\Coach;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CoachAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates admin coach account for Coaches Dashboard
     *
     * @return void
     */
    public function run()
    {
        // Coaches Dashboard Admin - Charley
        Coach::create([
            'first_name' => 'Charley',
            'last_name' => 'BodyF1rst',
            'email' => 'Charley@bodyf1rst.com',
            'password' => 'Password123!',
            'phone' => '',
            'role' => 'lead_trainer',  // Admin role for coaches
            'bio' => 'Administrator for BodyF1rst Coaches Dashboard',
            'gender' => 'other',
            'is_accepting_clients' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
