<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $departments = [
            ['name' => 'HR', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Finance', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Marketing', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Sales', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'IT', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Customer Service', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ];

        DB::table('departments')->insert($departments);
    }
}
