<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('loginreminder')->dailyAt("09:00");
        // $schedule->command('sendcredentials')->everyThreeHours();
        $schedule->command('sendaccountabilitynotifications')->timezone('America/New_York')->dailyAt('16:30');
        $schedule->command('resetuserplan')->everyMinute();

        // Send appointment reminders for appointments happening in the next 24 hours
        $schedule->command('appointments:send-reminders')->dailyAt('09:00');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
