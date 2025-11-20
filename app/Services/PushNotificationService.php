<?php

namespace App\Services;

use App\Helpers\Helper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    /**
     * Send push notification via OneSignal
     *
     * @param string $title
     * @param string $message
     * @param array $userIds Array of user IDs to send notification to
     * @param string $type Notification type (e.g., 'message', 'appointment', 'workout')
     * @param int|null $modelId Related model ID
     * @param array $metadata Additional metadata
     * @return array
     */
    public function sendOneSignalNotification(
        string $title,
        string $message,
        array $userIds = [],
        string $type = 'general',
        $modelId = null,
        array $metadata = []
    ): array {
        try {
            // Use Helper::sendPush() which is already configured with OneSignal
            $response = Helper::sendPush(
                $title,
                $message,
                null, // user_id (not used in Helper for multiple users)
                null, // notification_id (optional)
                $type,
                $modelId,
                $userIds // users array for targeting specific users
            );

            Log::info('OneSignal notification sent', [
                'title' => $title,
                'type' => $type,
                'recipients' => count($userIds),
                'response' => $response
            ]);

            return [
                'success' => true,
                'response' => $response,
                'sent_count' => count($userIds)
            ];
        } catch (\Exception $e) {
            Log::error('OneSignal notification failed: ' . $e->getMessage(), [
                'title' => $title,
                'type' => $type
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send new message notification
     *
     * @param array $recipients
     * @param string $senderName
     * @param string $messagePreview
     * @param int $conversationId
     * @return array
     */
    public function sendNewMessageNotification(
        array $recipients,
        string $senderName,
        string $messagePreview,
        int $conversationId
    ): array {
        $userIds = $this->extractUserIds($recipients);

        if (empty($userIds)) {
            return ['success' => false, 'error' => 'No user IDs found'];
        }

        $title = $senderName;
        $message = $this->truncateMessage($messagePreview, 100);

        return $this->sendOneSignalNotification(
            $title,
            $message,
            $userIds,
            'new_message',
            $conversationId
        );
    }

    /**
     * Send typing notification
     *
     * @param array $recipients
     * @param string $userName
     * @param int $conversationId
     * @return array
     */
    public function sendTypingNotification(
        array $recipients,
        string $userName,
        int $conversationId
    ): array {
        $userIds = $this->extractUserIds($recipients);

        if (empty($userIds)) {
            return ['success' => false, 'error' => 'No user IDs found'];
        }

        // Silent notification - OneSignal will handle as data-only
        return $this->sendOneSignalNotification(
            '',
            $userName . ' is typing...',
            $userIds,
            'typing_indicator',
            $conversationId
        );
    }

    /**
     * Send message flagged notification to admins
     *
     * @param string $flagType
     * @param int $messageId
     * @param string $reason
     * @return array
     */
    public function sendMessageFlaggedNotification(
        string $flagType,
        int $messageId,
        string $reason
    ): array {
        // Get all admin user IDs
        $adminIds = $this->getAdminUserIds();

        if (empty($adminIds)) {
            return ['success' => false, 'error' => 'No admin users found'];
        }

        $title = 'Message Flagged: ' . ucfirst($flagType);
        $message = 'A message has been flagged for review: ' . $reason;

        return $this->sendOneSignalNotification(
            $title,
            $message,
            $adminIds,
            'message_flagged',
            $messageId
        );
    }

    /**
     * Send read receipt notification
     *
     * @param int $userId
     * @param int $messageId
     * @param int $conversationId
     * @return array
     */
    public function sendReadReceiptNotification(
        int $userId,
        int $messageId,
        int $conversationId
    ): array {
        // Silent notification for read receipts
        return $this->sendOneSignalNotification(
            '',
            'Message read',
            [$userId],
            'read_receipt',
            $messageId
        );
    }

    /**
     * Extract user IDs from recipients array
     *
     * @param array $recipients [['id' => 1, 'type' => 'user'], ...]
     * @return array
     */
    private function extractUserIds(array $recipients): array
    {
        $userIds = [];

        foreach ($recipients as $recipient) {
            if (isset($recipient['id'])) {
                $userIds[] = $recipient['id'];
            }
        }

        return array_unique($userIds);
    }

    /**
     * Get all admin user IDs
     *
     * @return array
     */
    private function getAdminUserIds(): array
    {
        // Query admins table for all admin user IDs
        return DB::table('admins')
            ->where('role', 'admin')
            ->pluck('id')
            ->toArray();
    }

    /**
     * Truncate message preview
     *
     * @param string $message
     * @param int $length
     * @return string
     */
    private function truncateMessage(string $message, int $length = 100): string
    {
        if (strlen($message) <= $length) {
            return $message;
        }

        return substr($message, 0, $length) . '...';
    }
}
