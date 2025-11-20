<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VideoTagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $tags = [
            ['tag' => 'Chest', 'type' => 'Video', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['tag' => 'Shoulder', 'type' => 'Video', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['tag' => 'Leg', 'type' => 'Video', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['tag' => 'Tricep', 'type' => 'Video', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['tag' => 'Back', 'type' => 'Video', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['tag' => 'Bicep', 'type' => 'Video', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['tag' => 'Core', 'type' => 'Video', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['tag' => 'Glutes', 'type' => 'Video', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['tag' => 'Forearms', 'type' => 'Video', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['tag' => 'Calves', 'type' => 'Video', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ];

        DB::table('tags')->insert($tags);
    }
}
