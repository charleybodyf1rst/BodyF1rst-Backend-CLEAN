<?php

namespace App\Console\Commands;

use App\Helpers\Helper;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendCredentials extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendcredentials';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Credentials';

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
        info("Cron Job Working");

        $users = User::where('is_mailed', 0)->get();

        foreach($users as $user)
        {
            $name = $user->first_name .' '.$user->last_name;
            // SECURITY: Password reset link should be sent instead of plaintext password
            Helper::sendCredential("User",$name,$user->email,null,null,null,'Admin');
            $user->is_mailed = 1;
            $user->last_reminded_at = Carbon::now();
            $user->save();
        }
    }
}
