<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TrainingPreferenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $training_preferences = [
            ['name' => 'Strength Training', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Cardio Workouts', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Bodyweight Exercises', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ];

        DB::table('training_preferences')->insert($training_preferences);
    }
}
