<?php

namespace App\Console\Commands;

use App\Helpers\Helper;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendLoginReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loginreminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Reminder to Users to Login';

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
        info("Login Reminder");
        $users = User::where('first_login', 1)->where(function($query){
            $query->whereNull('last_reminded_at')
                  ->orWhere('last_reminded_at', '<', Carbon::now());
        })->where('created_at', '>', Carbon::now()->subDays(30))
        ->get();

        foreach($users as $user)
        {
            try{
                Helper::sendReminder($user);
                $user->last_reminded_at = Carbon::now();
                $user->save();
            }catch (\Exception $e) {
                info("Login Reminder:".$e->getMessage());
            }
        }
    }
}
