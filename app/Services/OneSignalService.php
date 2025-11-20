<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OneSignalService
{
    private string $appId;
    private string $restApiKey;
    private string $baseUrl = 'https://onesignal.com/api/v1';

    public function __construct()
    {
        $this->appId = config('services.onesignal.app_id');
        $this->restApiKey = config('services.onesignal.rest_api_key');
    }

    /**
     * Send push notification to specific users
     *
     * @param array $userIds OneSignal player IDs
     * @param string $title Notification title
     * @param string $message Notification message
     * @param array $data Additional data payload
     * @return array
     */
    public function sendToUsers(array $userIds, string $title, string $message, array $data = []): array
    {
        try {
            $payload = [
                'app_id' => $this->appId,
                'include_player_ids' => $userIds,
                'headings' => ['en' => $title],
                'contents' => ['en' => $message],
                'data' => $data,
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $this->restApiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/notifications', $payload);

            $result = $response->json();

            Log::info('OneSignal notification sent', [
                'recipients' => $result['recipients'] ?? 0,
                'id' => $result['id'] ?? null,
            ]);

            return [
                'success' => true,
                'notification_id' => $result['id'] ?? null,
                'recipients' => $result['recipients'] ?? 0,
            ];
        } catch (\Exception $e) {
            Log::error('OneSignal notification failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send notification to all users
     *
     * @param string $title
     * @param string $message
     * @param array $data
     * @return array
     */
    public function sendToAll(string $title, string $message, array $data = []): array
    {
        try {
            $payload = [
                'app_id' => $this->appId,
                'included_segments' => ['All'],
                'headings' => ['en' => $title],
                'contents' => ['en' => $message],
                'data' => $data,
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $this->restApiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/notifications', $payload);

            $result = $response->json();

            return [
                'success' => true,
                'notification_id' => $result['id'] ?? null,
                'recipients' => $result['recipients'] ?? 0,
            ];
        } catch (\Exception $e) {
            Log::error('OneSignal broadcast failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send notification to users with tags/segments
     *
     * @param array $filters OneSignal filter conditions
     * @param string $title
     * @param string $message
     * @param array $data
     * @return array
     */
    public function sendToSegment(array $filters, string $title, string $message, array $data = []): array
    {
        try {
            $payload = [
                'app_id' => $this->appId,
                'filters' => $filters,
                'headings' => ['en' => $title],
                'contents' => ['en' => $message],
                'data' => $data,
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $this->restApiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/notifications', $payload);

            $result = $response->json();

            return [
                'success' => true,
                'notification_id' => $result['id'] ?? null,
                'recipients' => $result['recipients'] ?? 0,
            ];
        } catch (\Exception $e) {
            Log::error('OneSignal segment notification failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create or update a user device (player)
     *
     * @param string $deviceToken
     * @param int $userId
     * @param string $deviceType (ios|android|web)
     * @return array
     */
    public function createDevice(string $deviceToken, int $userId, string $deviceType = 'android'): array
    {
        try {
            $payload = [
                'app_id' => $this->appId,
                'device_type' => $this->getDeviceTypeCode($deviceType),
                'identifier' => $deviceToken,
                'external_user_id' => (string)$userId,
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $this->restApiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/players', $payload);

            $result = $response->json();

            return [
                'success' => true,
                'player_id' => $result['id'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('OneSignal device creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send workout reminder notification
     *
     * @param array $userIds
     * @param string $workoutName
     * @param string $scheduledTime
     * @return array
     */
    public function sendWorkoutReminder(array $userIds, string $workoutName, string $scheduledTime): array
    {
        return $this->sendToUsers(
            $userIds,
            'Workout Reminder',
            "Time for your {$workoutName} workout at {$scheduledTime}!",
            [
                'type' => 'workout_reminder',
                'workout_name' => $workoutName,
                'scheduled_time' => $scheduledTime,
            ]
        );
    }

    /**
     * Send new message notification
     *
     * @param array $userIds
     * @param string $senderName
     * @param string $messagePreview
     * @param int $conversationId
     * @return array
     */
    public function sendNewMessageNotification(
        array $userIds,
        string $senderName,
        string $messagePreview,
        int $conversationId
    ): array {
        return $this->sendToUsers(
            $userIds,
            $senderName,
            $this->truncateMessage($messagePreview, 100),
            [
                'type' => 'new_message',
                'conversation_id' => (string)$conversationId,
                'sender_name' => $senderName,
            ]
        );
    }

    /**
     * Send meal log reminder
     *
     * @param array $userIds
     * @param string $mealType (breakfast|lunch|dinner|snack)
     * @return array
     */
    public function sendMealLogReminder(array $userIds, string $mealType): array
    {
        return $this->sendToUsers(
            $userIds,
            'Meal Logging Reminder',
            "Don't forget to log your {$mealType}!",
            [
                'type' => 'meal_reminder',
                'meal_type' => $mealType,
            ]
        );
    }

    /**
     * Get device type code for OneSignal
     *
     * @param string $deviceType
     * @return int
     */
    private function getDeviceTypeCode(string $deviceType): int
    {
        return match ($deviceType) {
            'ios' => 0,
            'android' => 1,
            'amazon' => 2,
            'windows_phone' => 3,
            'chrome_web' => 5,
            'safari_web' => 7,
            'firefox_web' => 8,
            'email' => 11,
            default => 1, // Default to Android
        };
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
     * Test OneSignal connectivity
     *
     * @return array
     */
    public function ping(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $this->restApiKey,
            ])->get($this->baseUrl . '/apps/' . $this->appId);

            if ($response->successful()) {
                return [
                    'status' => 'success',
                    'message' => 'Successfully connected to OneSignal',
                    'app_name' => $response->json()['name'] ?? 'BodyF1rst',
                ];
            }

            return [
                'status' => 'failed',
                'message' => 'Failed to connect to OneSignal',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
}
