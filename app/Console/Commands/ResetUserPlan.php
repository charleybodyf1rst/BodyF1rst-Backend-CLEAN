<?php

namespace App\Console\Commands;

use App\Models\UserCompletedWorkout;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResetUserPlan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'resetuserplan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset User Plan';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        // $threshold = Carbon::now()->subHours(18);
        $threshold = Carbon::now()->subMinutes(2);

        $deletedCount = UserCompletedWorkout::where('start_time', '<=', $threshold)
            ->whereHas('plan', function ($query) {
                $query->where('type', 'On Demand');
            })
            ->delete();
        info("deleted $deletedCount data");
        $this->info("deleted $deletedCount data");
    }
}
