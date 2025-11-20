<?php

namespace Database\Seeders;

use App\Models\EquipmentPreference;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();
        $this->call([
            AdminSeeder::class,
            CoachAdminSeeder::class,
            CharleyUserSeeder::class,
            // DepartmentSeeder::class,
            // RewardProgramSeeder::class,
            // DietaryRestrictionSeeder::class,
            // TrainingPreferenceSeeder::class,
            // EquipmentPreferenceSeeder::class,
            // ChallengeTypeSeeder::class,
            VideoTagSeeder::class,
        ]);
    }
}
