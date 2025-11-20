<?php

namespace App\Listeners;

use App\Events\MessageFlagged;
use App\Services\PushNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifyAdminOfViolation implements ShouldQueue
{
    use InteractsWithQueue;

    protected $notificationService;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(PushNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event.
     *
     * @param  MessageFlagged  $event
     * @return void
     */
    public function handle(MessageFlagged $event)
    {
        $flag = $event->messageFlag;

        try {
            // Send push notification to admins
            $this->notificationService->sendMessageFlaggedNotification(
                $flag->flag_type,
                $flag->message_id,
                $flag->reason ?? 'Auto-flagged by system'
            );

            // Send email notification to admins
            $this->sendEmailToAdmins($flag);

            // Log the violation
            Log::channel('moderation')->warning('Message flagged', [
                'flag_id' => $flag->id,
                'message_id' => $flag->message_id,
                'flag_type' => $flag->flag_type,
                'flagged_by_type' => $flag->flagged_by_type,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to notify admins of violation: ' . $e->getMessage());
        }
    }

    /**
     * Send email notification to admins
     *
     * @param $flag
     * @return void
     */
    private function sendEmailToAdmins($flag)
    {
        // Get admin emails from database or config
        $adminEmails = config('mail.admin_addresses', []);

        if (empty($adminEmails)) {
            return;
        }

        try {
            // Send email using Laravel Mail
            // This is a placeholder - implement actual email template

            /*
            Mail::to($adminEmails)->send(
                new \App\Mail\MessageFlaggedNotification($flag)
            );
            */
        } catch (\Exception $e) {
            Log::error('Failed to send admin email: ' . $e->getMessage());
        }
    }
}
