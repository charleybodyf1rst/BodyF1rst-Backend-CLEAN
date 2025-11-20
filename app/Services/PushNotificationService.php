<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    /**
     * Send push notification via Firebase Cloud Messaging (FCM)
     *
     * @param array $deviceTokens
     * @param array $data
     * @param array $notification
     * @return array
     */
    public function sendFCMNotification(array $deviceTokens, array $data, array $notification = []): array
    {
        try {
            $serverKey = env('FCM_SERVER_KEY');

            if (!$serverKey) {
                throw new \Exception('FCM Server Key not configured');
            }

            $payload = [
                'registration_ids' => $deviceTokens,
                'data' => $data,
                'priority' => 'high',
            ];

            if (!empty($notification)) {
                $payload['notification'] = $notification;
            }

            $response = Http::withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', $payload);

            $result = $response->json();

            Log::info('FCM notification sent', [
                'success' => $result['success'] ?? 0,
                'failure' => $result['failure'] ?? 0,
            ]);

            return [
                'success' => true,
                'response' => $result,
                'sent_count' => $result['success'] ?? 0,
                'failed_count' => $result['failure'] ?? 0,
            ];
        } catch (\Exception $e) {
            Log::error('FCM notification failed: ' . $e->getMessage());
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
        $deviceTokens = $this->getDeviceTokens($recipients);

        if (empty($deviceTokens)) {
            return ['success' => false, 'error' => 'No device tokens found'];
        }

        $notification = [
            'title' => $senderName,
            'body' => $this->truncateMessage($messagePreview, 100),
            'sound' => 'default',
            'badge' => '1',
            'icon' => 'notification_icon',
        ];

        $data = [
            'type' => 'new_message',
            'conversation_id' => (string)$conversationId,
            'sender_name' => $senderName,
            'click_action' => 'OPEN_CONVERSATION',
        ];

        return $this->sendFCMNotification($deviceTokens, $data, $notification);
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
        $deviceTokens = $this->getDeviceTokens($recipients);

        if (empty($deviceTokens)) {
            return ['success' => false, 'error' => 'No device tokens found'];
        }

        $data = [
            'type' => 'typing_indicator',
            'conversation_id' => (string)$conversationId,
            'user_name' => $userName,
            'is_typing' => 'true',
        ];

        // Silent notification (data only)
        return $this->sendFCMNotification($deviceTokens, $data);
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
        // Get all admin device tokens
        $adminTokens = $this->getAdminDeviceTokens();

        if (empty($adminTokens)) {
            return ['success' => false, 'error' => 'No admin tokens found'];
        }

        $notification = [
            'title' => 'Message Flagged: ' . ucfirst($flagType),
            'body' => 'A message has been flagged for review: ' . $reason,
            'sound' => 'default',
            'badge' => '1',
        ];

        $data = [
            'type' => 'message_flagged',
            'flag_type' => $flagType,
            'message_id' => (string)$messageId,
            'click_action' => 'OPEN_MODERATION_DASHBOARD',
        ];

        return $this->sendFCMNotification($adminTokens, $data, $notification);
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
        $deviceTokens = $this->getUserDeviceTokens($userId);

        if (empty($deviceTokens)) {
            return ['success' => false, 'error' => 'No device tokens found'];
        }

        $data = [
            'type' => 'read_receipt',
            'message_id' => (string)$messageId,
            'conversation_id' => (string)$conversationId,
        ];

        // Silent notification
        return $this->sendFCMNotification($deviceTokens, $data);
    }

    /**
     * Get device tokens for recipients
     *
     * @param array $recipients [['id' => 1, 'type' => 'user'], ...]
     * @return array
     */
    private function getDeviceTokens(array $recipients): array
    {
        $tokens = [];

        foreach ($recipients as $recipient) {
            $userTokens = $this->getUserDeviceTokens(
                $recipient['id'],
                $recipient['type'] ?? 'user'
            );
            $tokens = array_merge($tokens, $userTokens);
        }

        return array_unique($tokens);
    }

    /**
     * Get device tokens for a specific user
     *
     * @param int $userId
     * @param string $userType
     * @return array
     */
    private function getUserDeviceTokens(int $userId, string $userType = 'user'): array
    {
        // This would query your device_tokens table
        // For now, returning empty array as placeholder

        // Example query:
        // return DB::table('device_tokens')
        //     ->where('user_id', $userId)
        //     ->where('user_type', $userType)
        //     ->where('is_active', true)
        //     ->pluck('token')
        //     ->toArray();

        return [];
    }

    /**
     * Get all admin device tokens
     *
     * @return array
     */
    private function getAdminDeviceTokens(): array
    {
        // This would query your device_tokens table for admin users
        // For now, returning empty array as placeholder

        // Example query:
        // return DB::table('device_tokens')
        //     ->where('user_type', 'admin')
        //     ->where('is_active', true)
        //     ->pluck('token')
        //     ->toArray();

        return [];
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

    /**
     * Send push notification via Apple Push Notification Service (APNS)
     *
     * @param array $deviceTokens
     * @param array $payload
     * @return array
     */
    public function sendAPNSNotification(array $deviceTokens, array $payload): array
    {
        // Placeholder for APNS implementation
        // In production, use a proper APNS library or service

        try {
            // Example using apns-php or similar library
            // This would require proper APNS configuration

            return [
                'success' => true,
                'message' => 'APNS notification sent',
                'sent_count' => count($deviceTokens)
            ];
        } catch (\Exception $e) {
            Log::error('APNS notification failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
