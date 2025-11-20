<?php

namespace App\Console\Commands;

use App\Helpers\Helper;
use App\Models\AppNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendAccountabilityNotification extends Command
{
    protected $signature = 'sendaccountabilitynotifications';
    protected $description = 'Send Accountability Notifications based on user accountability level.';

    public function handle()
    {
        $currentDate = Carbon::now()->timezone('America/New_York')->toDateString();
        $today = Carbon::now()->timezone('America/New_York')->format('l');

        $this->processNotifications('High', $currentDate);

        if (in_array($today, ['Monday', 'Thursday'])) {
            $this->processNotifications('Medium', $currentDate);
        }

        if ($today === 'Friday') {
            $this->processNotifications('Low', $currentDate);
        }

        $this->info('Accountability notifications sent successfully.');
        return 0;
    }

    private function processNotifications($accountabilityType, $currentDate)
    {
        $users = User::with(['assigned_plans.upload_by:id,first_name,last_name,profile_image'])
            ->whereHas('assigned_plans', function($query) use ($currentDate) {
                $query->whereDate('start_date', '<=', $currentDate)
                      ->whereDate('end_date', '>=', $currentDate);
            })
            ->where('accountability', $accountabilityType)
            ->get();

        if ($users->isNotEmpty()) {
            $this->sendNotification($users, $accountabilityType);
        }
    }

    private function sendNotification($users, $type)
    {
        $titles = [
            'High' => "Your Daily Motivation!",
            'Medium' => "Stay on Track with Your Plan!",
            'Low' => "A Little Nudge to Keep You Going!"
        ];

        $messages = [
            'High' => "Hey :name, it’s :sender_name checking in! I’m proud of the work you’ve been putting in. Remember, every small step counts toward your big goals. Let me know if you have any questions or need help with your plan. You’ve got this!",
            'Medium' => "Hi :name, this is :sender_name. I hope you're doing well! Just a quick check-in to ensure you're staying on track with your plan. Remember, consistency is key, and I'm here to support you. Keep up the great work!",
            'Low' => "Hi :name, this is :sender_name. I wanted to remind you about your plan and encourage you to make some progress this week. You're doing great, and I'm here if you need anything. Let's keep moving forward together!"
        ];

        foreach ($users as $user) {
            $name = $user->first_name . " " . $user->last_name;
            $sender_name = optional(optional($user->assigned_plans->first())->upload_by)->first_name . ' ' . optional(optional($user->assigned_plans->first())->upload_by)->last_name;

            $title = $titles[$type];
            $message = str_replace([':name', ':sender_name'], [$name, $sender_name], $messages[$type]);

            $app_notification = AppNotification::create([
                'user_id' => $user->id,
                'title' => $title,
                'message' => $message,
            ]);

            $api_response = Helper::sendPush($title, $message, $user->id, $app_notification->id);
            $app_notification->api_response = $api_response;
            $app_notification->module = "app";
            $app_notification->save();

        }
    }
}
