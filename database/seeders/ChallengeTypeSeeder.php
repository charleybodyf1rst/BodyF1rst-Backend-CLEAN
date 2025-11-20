<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ChallengeTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $timestamp = Carbon::now();

        $challengeTypes = [
            ['type' => 'Body Points', 'is_active' => 1, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['type' => 'Weight Loss', 'is_active' => 1, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['type' => 'Body Fat %', 'is_active' => 1, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['type' => 'Muscle Gain', 'is_active' => 1, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['type' => 'Steps', 'is_active' => 1, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['type' => 'Workouts Complete', 'is_active' => 1, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['type' => 'Meal Plan', 'is_active' => 1, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['type' => 'Run', 'is_active' => 1, 'created_at' => $timestamp, 'updated_at' => $timestamp],
        ];

        DB::table('challenge_types')->insert($challengeTypes);
    }
}
