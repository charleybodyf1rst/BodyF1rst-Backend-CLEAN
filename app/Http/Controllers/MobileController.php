<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\MobileDevice;
use App\Models\PushNotificationSetting;
use Carbon\Carbon;

class MobileController extends Controller
{
    /**
     * Register a mobile device for push notifications
     * POST /api/mobile/device/register
     */
    public function registerDevice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_token' => 'required|string',
            'device_type' => 'required|in:ios,android',
            'device_name' => 'nullable|string',
            'app_version' => 'nullable|string',
            'os_version' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = Auth::id();

        try {
            // Check if device already exists
            $device = MobileDevice::where('user_id', $userId)
                ->where('device_token', $request->device_token)
                ->first();

            if ($device) {
                // Update existing device
                $device->update([
                    'device_name' => $request->device_name ?? $device->device_name,
                    'app_version' => $request->app_version ?? $device->app_version,
                    'os_version' => $request->os_version ?? $device->os_version,
                    'is_active' => true,
                    'last_active_at' => now(),
                ]);

                return response()->json([
                    'status' => 200,
                    'message' => 'Device updated successfully',
                    'data' => [
                        'device_id' => $device->id,
                        'is_new' => false
                    ]
                ]);
            }

            // Create new device
            $device = MobileDevice::create([
                'user_id' => $userId,
                'device_token' => $request->device_token,
                'device_type' => $request->device_type,
                'device_name' => $request->device_name ?? ucfirst($request->device_type) . ' Device',
                'app_version' => $request->app_version,
                'os_version' => $request->os_version,
                'is_active' => true,
                'last_active_at' => now(),
            ]);

            // Create default push notification settings
            PushNotificationSetting::create([
                'user_id' => $userId,
                'device_id' => $device->id,
                'workouts' => true,
                'meals' => true,
                'messages' => true,
                'reminders' => true,
                'progress' => true,
                'social' => false,
            ]);

            return response()->json([
                'status' => 201,
                'message' => 'Device registered successfully',
                'data' => [
                    'device_id' => $device->id,
                    'is_new' => true
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Failed to register device',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update push notification settings
     * POST /api/mobile/push-settings
     */
    public function updatePushSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|exists:mobile_devices,id',
            'workouts' => 'nullable|boolean',
            'meals' => 'nullable|boolean',
            'messages' => 'nullable|boolean',
            'reminders' => 'nullable|boolean',
            'progress' => 'nullable|boolean',
            'social' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = Auth::id();

        try {
            $settings = PushNotificationSetting::where('user_id', $userId)
                ->where('device_id', $request->device_id)
                ->first();

            if (!$settings) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Push notification settings not found'
                ], 404);
            }

            $settings->update([
                'workouts' => $request->has('workouts') ? $request->workouts : $settings->workouts,
                'meals' => $request->has('meals') ? $request->meals : $settings->meals,
                'messages' => $request->has('messages') ? $request->messages : $settings->messages,
                'reminders' => $request->has('reminders') ? $request->reminders : $settings->reminders,
                'progress' => $request->has('progress') ? $request->progress : $settings->progress,
                'social' => $request->has('social') ? $request->social : $settings->social,
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Push notification settings updated',
                'data' => $settings
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Failed to update settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get mobile app configuration
     * GET /api/mobile/config
     */
    public function getConfig(Request $request)
    {
        $userId = Auth::id();
        $user = User::find($userId);

        $config = [
            'status' => 200,
            'data' => [
                'app_version' => [
                    'current' => '2.0.0',
                    'minimum_required' => '1.8.0',
                    'update_available' => false,
                    'force_update' => false,
                ],
                'features' => [
                    'voice_commands' => true,
                    'ai_coach' => true,
                    'offline_mode' => true,
                    'wearables_sync' => true,
                    'video_library' => true,
                    'cbt_program' => true,
                    'meal_scanning' => true,
                    'social_feed' => false,
                ],
                'api' => [
                    'base_url' => config('app.url') . '/api',
                    'timeout' => 30,
                    'retry_attempts' => 3,
                ],
                'push_notifications' => [
                    'enabled' => true,
                    'sound' => true,
                    'vibrate' => true,
                ],
                'offline_sync' => [
                    'enabled' => true,
                    'sync_interval' => 300, // 5 minutes
                    'max_queue_size' => 100,
                ],
                'theme' => [
                    'primary_color' => '#FF6B35',
                    'secondary_color' => '#004E89',
                    'dark_mode' => $user->preferences['dark_mode'] ?? false,
                ],
                'limits' => [
                    'max_photo_size' => 5242880, // 5MB
                    'max_video_size' => 52428800, // 50MB
                    'max_workout_duration' => 7200, // 2 hours
                ],
            ]
        ];

        return response()->json($config);
    }

    /**
     * Sync offline data to server
     * POST /api/mobile/sync-offline
     */
    public function syncOfflineData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'data' => 'required|array',
            'data.*.type' => 'required|in:workout,meal,weight,journal,exercise',
            'data.*.payload' => 'required|array',
            'data.*.timestamp' => 'required|date',
            'data.*.client_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = Auth::id();
        $syncResults = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($request->data as $item) {
            try {
                $result = $this->processSyncItem($userId, $item);
                $syncResults[] = [
                    'client_id' => $item['client_id'],
                    'type' => $item['type'],
                    'status' => $result['status'],
                    'server_id' => $result['server_id'] ?? null,
                    'message' => $result['message'] ?? null,
                ];

                if ($result['status'] === 'success') {
                    $successCount++;
                } else {
                    $failureCount++;
                }

            } catch (\Exception $e) {
                $syncResults[] = [
                    'client_id' => $item['client_id'],
                    'type' => $item['type'],
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
                $failureCount++;
            }
        }

        return response()->json([
            'status' => 200,
            'message' => 'Sync completed',
            'data' => [
                'total' => count($request->data),
                'success' => $successCount,
                'failed' => $failureCount,
                'results' => $syncResults,
                'synced_at' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Process individual sync item
     */
    private function processSyncItem($userId, $item)
    {
        switch ($item['type']) {
            case 'workout':
                // Process workout log
                $workout = DB::table('workout_logs')->insertGetId([
                    'user_id' => $userId,
                    'workout_id' => $item['payload']['workout_id'] ?? null,
                    'duration' => $item['payload']['duration'] ?? 0,
                    'notes' => $item['payload']['notes'] ?? '',
                    'created_at' => $item['timestamp'],
                    'updated_at' => now(),
                ]);
                return ['status' => 'success', 'server_id' => $workout];

            case 'meal':
                // Process meal log
                $meal = DB::table('meals')->insertGetId([
                    'user_id' => $userId,
                    'meal_type' => $item['payload']['meal_type'] ?? 'snack',
                    'calories' => $item['payload']['calories'] ?? 0,
                    'protein' => $item['payload']['protein'] ?? 0,
                    'carbs' => $item['payload']['carbs'] ?? 0,
                    'fats' => $item['payload']['fats'] ?? 0,
                    'notes' => $item['payload']['notes'] ?? '',
                    'created_at' => $item['timestamp'],
                    'updated_at' => now(),
                ]);
                return ['status' => 'success', 'server_id' => $meal];

            case 'weight':
                // Process weight log
                $weight = DB::table('weight_logs')->insertGetId([
                    'user_id' => $userId,
                    'weight' => $item['payload']['weight'],
                    'unit' => $item['payload']['unit'] ?? 'lbs',
                    'notes' => $item['payload']['notes'] ?? '',
                    'logged_at' => $item['timestamp'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                return ['status' => 'success', 'server_id' => $weight];

            case 'journal':
                // Process journal entry
                $journal = DB::table('journal_entries')->insertGetId([
                    'user_id' => $userId,
                    'content' => $item['payload']['content'],
                    'mood' => $item['payload']['mood'] ?? 5,
                    'created_at' => $item['timestamp'],
                    'updated_at' => now(),
                ]);
                return ['status' => 'success', 'server_id' => $journal];

            default:
                return ['status' => 'error', 'message' => 'Unknown sync type'];
        }
    }

    /**
     * Get available updates
     * GET /api/mobile/updates
     */
    public function getUpdates(Request $request)
    {
        $userId = Auth::id();
        $lastSync = $request->get('last_sync', null);
        $lastSyncDate = $lastSync ? Carbon::parse($lastSync) : Carbon::now()->subDays(7);

        $updates = [
            'status' => 200,
            'data' => [
                'workouts' => DB::table('workouts')
                    ->where('user_id', $userId)
                    ->where('updated_at', '>', $lastSyncDate)
                    ->count(),
                'meals' => DB::table('meals')
                    ->where('user_id', $userId)
                    ->where('updated_at', '>', $lastSyncDate)
                    ->count(),
                'messages' => DB::table('messages')
                    ->where('receiver_id', $userId)
                    ->where('created_at', '>', $lastSyncDate)
                    ->count(),
                'notifications' => DB::table('notifications')
                    ->where('user_id', $userId)
                    ->where('created_at', '>', $lastSyncDate)
                    ->count(),
                'last_checked' => now()->toIso8601String(),
            ]
        ];

        return response()->json($updates);
    }

    /**
     * Log mobile app errors
     * POST /api/mobile/error-log
     */
    public function logError(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'error_type' => 'required|string',
            'error_message' => 'required|string',
            'stack_trace' => 'nullable|string',
            'app_version' => 'required|string',
            'device_info' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::table('mobile_error_logs')->insert([
                'user_id' => Auth::id(),
                'error_type' => $request->error_type,
                'error_message' => $request->error_message,
                'stack_trace' => $request->stack_trace,
                'app_version' => $request->app_version,
                'device_info' => json_encode($request->device_info ?? []),
                'created_at' => now(),
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Error logged successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Failed to log error'
            ], 500);
        }
    }

    /**
     * Update app settings
     * POST /api/mobile/settings
     */
    public function updateSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'dark_mode' => 'nullable|boolean',
            'notifications_enabled' => 'nullable|boolean',
            'sound_enabled' => 'nullable|boolean',
            'offline_mode' => 'nullable|boolean',
            'auto_sync' => 'nullable|boolean',
            'language' => 'nullable|string|in:en,es,fr,de',
            'units' => 'nullable|string|in:metric,imperial',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = Auth::id();
        $user = User::find($userId);

        try {
            $preferences = $user->preferences ?? [];

            if ($request->has('dark_mode')) $preferences['dark_mode'] = $request->dark_mode;
            if ($request->has('notifications_enabled')) $preferences['notifications_enabled'] = $request->notifications_enabled;
            if ($request->has('sound_enabled')) $preferences['sound_enabled'] = $request->sound_enabled;
            if ($request->has('offline_mode')) $preferences['offline_mode'] = $request->offline_mode;
            if ($request->has('auto_sync')) $preferences['auto_sync'] = $request->auto_sync;
            if ($request->has('language')) $preferences['language'] = $request->language;
            if ($request->has('units')) $preferences['units'] = $request->units;

            $user->preferences = $preferences;
            $user->save();

            return response()->json([
                'status' => 200,
                'message' => 'Settings updated successfully',
                'data' => $preferences
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Failed to update settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
