<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DietaryRestrictionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $dietary_restrictions = [
            ['name' => 'Vegetarian', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Vegan', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Gluten Free', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Non-Dairy', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ];

        DB::table('dietary_restrictions')->insert($dietary_restrictions);
    }
}
