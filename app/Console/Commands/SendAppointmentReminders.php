<?php

namespace App\Console\Commands;

use App\Services\AppointmentNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendAppointmentReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appointments:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send email reminders for appointments happening in the next 24 hours';

    protected $notificationService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(AppointmentNotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Sending appointment reminders...');

        try {
            $count = $this->notificationService->sendUpcomingReminders();

            $this->info("Successfully sent {$count} appointment reminder(s)");
            Log::info("Appointment reminders sent: {$count}");

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to send appointment reminders: ' . $e->getMessage());
            Log::error('Failed to send appointment reminders: ' . $e->getMessage());

            return 1;
        }
    }
}
