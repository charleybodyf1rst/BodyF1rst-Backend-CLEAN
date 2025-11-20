<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EquipmentPreferenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $equipment_preferences = [
            ['name' => 'Treadmills', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Dumbbells', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Resistance Bands', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ];

        DB::table('equipment_preferences')->insert($equipment_preferences);
    }
}
