<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RewardProgramSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $departments = [
            ['name' => 'Paid Time Off', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Gift Cards', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Cash', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Stock Options', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Nector', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ];

        DB::table('reward_programs')->insert($departments);
    }
}
