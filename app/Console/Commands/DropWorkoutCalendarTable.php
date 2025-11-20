<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DropWorkoutCalendarTable extends Command
{
    protected $signature = 'table:drop-workout-calendar';
    protected $description = 'Safely drop the workout_calendar table with foreign key checks disabled';

    public function handle()
    {
        $this->info('Dropping workout_calendar table safely...');
        
        try {
            // Disable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            
            // Drop table if exists
            Schema::dropIfExists('workout_calendar');
            
            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            
            $this->info('✅ workout_calendar table dropped successfully');
            $this->info('You can now run: php artisan migrate --force');
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to drop table: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
