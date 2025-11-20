<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use Carbon\Carbon;

class MobileAppController extends Controller
{
    /**
     * Register mobile device for push notifications
     */
    public function registerDevice(Request $request)
    {
        try {
            $validated = $request->validate([
                'device_token' => 'required|string',
                'device_type' => 'required|string|in:ios,android',
                'device_model' => 'nullable|string',
                'os_version' => 'nullable|string',
                'app_version' => 'nullable|string',
                'device_name' => 'nullable|string'
            ]);

            DB::beginTransaction();

            $userId = Auth::id();

            // Check if device already registered
            $existingDevice = DB::table('mobile_devices')
                ->where('user_id', $userId)
                ->where('device_token', $validated['device_token'])
                ->first();

            if ($existingDevice) {
                // Update existing device
                DB::table('mobile_devices')
                    ->where('id', $existingDevice->id)
                    ->update([
                        'device_model' => $validated['device_model'] ?? $existingDevice->device_model,
                        'os_version' => $validated['os_version'] ?? $existingDevice->os_version,
                        'app_version' => $validated['app_version'] ?? $existingDevice->app_version,
                        'device_name' => $validated['device_name'] ?? $existingDevice->device_name,
                        'last_active_at' => now(),
                        'updated_at' => now()
                    ]);

                $deviceId = $existingDevice->id;
            } else {
                // Register new device
                $deviceId = DB::table('mobile_devices')->insertGetId([
                    'user_id' => $userId,
                    'device_token' => $validated['device_token'],
                    'device_type' => $validated['device_type'],
                    'device_model' => $validated['device_model'] ?? null,
                    'os_version' => $validated['os_version'] ?? null,
                    'app_version' => $validated['app_version'] ?? null,
                    'device_name' => $validated['device_name'] ?? null,
                    'is_active' => true,
                    'last_active_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Device registered successfully',
                'device_id' => $deviceId
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error registering device', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to register device',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update push notification settings
     */
    public function updatePushSettings(Request $request)
    {
        try {
            $validated = $request->validate([
                'enabled' => 'required|boolean',
                'workout_reminders' => 'nullable|boolean',
                'meal_reminders' => 'nullable|boolean',
                'social_notifications' => 'nullable|boolean',
                'achievement_notifications' => 'nullable|boolean',
                'challenge_updates' => 'nullable|boolean',
                'coach_messages' => 'nullable|boolean',
                'quiet_hours_enabled' => 'nullable|boolean',
                'quiet_hours_start' => 'nullable|date_format:H:i',
                'quiet_hours_end' => 'nullable|date_format:H:i'
            ]);

            DB::beginTransaction();

            $userId = Auth::id();

            // Update or create push settings
            DB::table('push_notification_settings')->updateOrInsert(
                ['user_id' => $userId],
                array_merge($validated, [
                    'updated_at' => now()
                ])
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Push notification settings updated successfully',
                'settings' => $validated
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error updating push settings', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update push settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get app configuration
     */
    public function getAppConfig(Request $request)
    {
        try {
            $validated = $request->validate([
                'app_version' => 'required|string',
                'platform' => 'required|string|in:ios,android'
            ]);

            // Check if update required
            $latestVersion = DB::table('app_versions')
                ->where('platform', $validated['platform'])
                ->where('is_active', true)
                ->orderBy('version_code', 'desc')
                ->first();

            $updateRequired = false;
            $forceUpdate = false;

            if ($latestVersion) {
                $currentVersion = $this->parseVersion($validated['app_version']);
                $latestVersionParsed = $this->parseVersion($latestVersion->version);

                if ($currentVersion < $latestVersionParsed) {
                    $updateRequired = true;
                    $forceUpdate = $latestVersion->force_update ?? false;
                }
            }

            // Get app configuration
            $config = [
                'app_version' => [
                    'current' => $validated['app_version'],
                    'latest' => $latestVersion->version ?? $validated['app_version'],
                    'update_required' => $updateRequired,
                    'force_update' => $forceUpdate,
                    'update_url' => $latestVersion->download_url ?? null,
                    'release_notes' => $latestVersion->release_notes ?? null
                ],
                'features' => [
                    'passio_camera_enabled' => true,
                    'social_features_enabled' => true,
                    'challenges_enabled' => true,
                    'coach_ken_enabled' => true,
                    'cbt_enabled' => true,
                    'video_workouts_enabled' => true,
                    'meal_planning_enabled' => true
                ],
                'api' => [
                    'base_url' => config('app.url'),
                    'version' => 'v1',
                    'timeout' => 30
                ],
                'limits' => [
                    'max_upload_size_mb' => 50,
                    'max_video_duration_seconds' => 300,
                    'max_photos_per_post' => 10,
                    'cache_duration_minutes' => 60
                ],
                'settings' => [
                    'default_units' => 'metric',
                    'default_language' => 'en',
                    'offline_mode_enabled' => true,
                    'biometric_auth_enabled' => true
                ]
            ];

            return response()->json([
                'success' => true,
                'config' => $config,
                'server_time' => now()->toIso8601String()
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching app config', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch app configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync offline data
     */
    public function syncOfflineData(Request $request)
    {
        try {
            $validated = $request->validate([
                'workouts' => 'nullable|array',
                'workouts.*.workout_id' => 'required|integer',
                'workouts.*.completed_at' => 'required|date',
                'workouts.*.duration_minutes' => 'required|integer',
                'workouts.*.calories_burned' => 'nullable|integer',
                'nutrition_logs' => 'nullable|array',
                'nutrition_logs.*.date' => 'required|date',
                'nutrition_logs.*.meals' => 'required|array',
                'nutrition_logs.*.total_calories' => 'required|numeric',
                'measurements' => 'nullable|array',
                'measurements.*.measured_at' => 'required|date',
                'measurements.*.weight' => 'nullable|numeric',
                'sync_timestamp' => 'required|integer'
            ]);

            DB::beginTransaction();

            $userId = Auth::id();
            $synced = [
                'workouts' => [],
                'nutrition_logs' => [],
                'measurements' => []
            ];
            $errors = [];

            // Sync workouts
            if (!empty($validated['workouts'])) {
                foreach ($validated['workouts'] as $workout) {
                    try {
                        $workoutId = DB::table('workout_sessions')->insertGetId([
                            'user_id' => $userId,
                            'workout_id' => $workout['workout_id'],
                            'status' => 'completed',
                            'completed_at' => $workout['completed_at'],
                            'duration_minutes' => $workout['duration_minutes'],
                            'calories_burned' => $workout['calories_burned'] ?? 0,
                            'synced_from_offline' => true,
                            'created_at' => $workout['completed_at'],
                            'updated_at' => now()
                        ]);

                        $synced['workouts'][] = $workoutId;
                    } catch (\Exception $e) {
                        $errors[] = "Failed to sync workout: " . $e->getMessage();
                    }
                }
            }

            // Sync nutrition logs
            if (!empty($validated['nutrition_logs'])) {
                foreach ($validated['nutrition_logs'] as $log) {
                    try {
                        DB::table('nutrition_logs')->updateOrInsert(
                            [
                                'user_id' => $userId,
                                'date' => $log['date']
                            ],
                            [
                                'meals' => json_encode($log['meals']),
                                'total_calories' => $log['total_calories'],
                                'synced_from_offline' => true,
                                'updated_at' => now()
                            ]
                        );

                        $synced['nutrition_logs'][] = $log['date'];
                    } catch (\Exception $e) {
                        $errors[] = "Failed to sync nutrition log: " . $e->getMessage();
                    }
                }
            }

            // Sync measurements
            if (!empty($validated['measurements'])) {
                foreach ($validated['measurements'] as $measurement) {
                    try {
                        $measurementId = DB::table('body_measurements')->insertGetId([
                            'user_id' => $userId,
                            'weight' => $measurement['weight'] ?? null,
                            'measured_at' => $measurement['measured_at'],
                            'synced_from_offline' => true,
                            'created_at' => $measurement['measured_at'],
                            'updated_at' => now()
                        ]);

                        $synced['measurements'][] = $measurementId;
                    } catch (\Exception $e) {
                        $errors[] = "Failed to sync measurement: " . $e->getMessage();
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data synced successfully',
                'synced' => $synced,
                'sync_timestamp' => time(),
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error syncing offline data', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync offline data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get updates since timestamp
     */
    public function getUpdatesSince(Request $request)
    {
        try {
            $validated = $request->validate([
                'since_timestamp' => 'required|integer',
                'data_types' => 'nullable|array',
                'data_types.*' => 'string|in:workouts,plans,nutrition,social,notifications'
            ]);

            $userId = Auth::id();
            $sinceDate = Carbon::createFromTimestamp($validated['since_timestamp']);
            $dataTypes = $validated['data_types'] ?? ['workouts', 'plans', 'nutrition', 'social', 'notifications'];

            $updates = [];

            // Get workout updates
            if (in_array('workouts', $dataTypes)) {
                $updates['workouts'] = DB::table('workout_sessions')
                    ->where('user_id', $userId)
                    ->where('updated_at', '>', $sinceDate)
                    ->get();
            }

            // Get plan updates
            if (in_array('plans', $dataTypes)) {
                $updates['plans'] = DB::table('user_plans')
                    ->where('user_id', $userId)
                    ->where('updated_at', '>', $sinceDate)
                    ->get();
            }

            // Get nutrition updates
            if (in_array('nutrition', $dataTypes)) {
                $updates['nutrition'] = DB::table('nutrition_logs')
                    ->where('user_id', $userId)
                    ->where('updated_at', '>', $sinceDate)
                    ->get();
            }

            // Get social updates
            if (in_array('social', $dataTypes)) {
                $updates['social'] = [
                    'new_followers' => DB::table('follows')
                        ->where('following_id', $userId)
                        ->where('created_at', '>', $sinceDate)
                        ->count(),
                    'new_likes' => DB::table('likes')
                        ->whereIn('likeable_id', function($query) use ($userId) {
                            $query->select('id')
                                ->from('posts')
                                ->where('user_id', $userId);
                        })
                        ->where('likeable_type', 'App\Models\Post')
                        ->where('created_at', '>', $sinceDate)
                        ->count(),
                    'new_comments' => DB::table('comments')
                        ->whereIn('post_id', function($query) use ($userId) {
                            $query->select('id')
                                ->from('posts')
                                ->where('user_id', $userId);
                        })
                        ->where('created_at', '>', $sinceDate)
                        ->count()
                ];
            }

            // Get notifications
            if (in_array('notifications', $dataTypes)) {
                $updates['notifications'] = DB::table('notifications')
                    ->where('user_id', $userId)
                    ->where('created_at', '>', $sinceDate)
                    ->orderBy('created_at', 'desc')
                    ->limit(50)
                    ->get();
            }

            return response()->json([
                'success' => true,
                'updates' => $updates,
                'timestamp' => time(),
                'has_more' => false
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching updates', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch updates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Log app error
     */
    public function logAppError(Request $request)
    {
        try {
            $validated = $request->validate([
                'error_type' => 'required|string|in:crash,network,api,ui,data',
                'error_message' => 'required|string|max:2000',
                'stack_trace' => 'nullable|string',
                'device_info' => 'nullable|array',
                'app_version' => 'required|string',
                'occurred_at' => 'required|date'
            ]);

            DB::table('app_error_logs')->insert([
                'user_id' => Auth::id(),
                'error_type' => $validated['error_type'],
                'error_message' => $validated['error_message'],
                'stack_trace' => $validated['stack_trace'] ?? null,
                'device_info' => json_encode($validated['device_info'] ?? []),
                'app_version' => $validated['app_version'],
                'occurred_at' => $validated['occurred_at'],
                'created_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Error logged successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error logging app error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to log error'
            ], 500);
        }
    }

    /**
     * Get cached data
     */
    public function getCachedData(Request $request)
    {
        try {
            $validated = $request->validate([
                'data_keys' => 'required|array',
                'data_keys.*' => 'string|in:workout_templates,exercise_library,meal_plans,challenges,leaderboard'
            ]);

            $cachedData = [];

            foreach ($validated['data_keys'] as $key) {
                $cacheKey = "mobile_app:{$key}";
                $data = Cache::remember($cacheKey, 3600, function() use ($key) {
                    return $this->getCacheableData($key);
                });

                $cachedData[$key] = $data;
            }

            return response()->json([
                'success' => true,
                'data' => $cachedData,
                'cache_expires_at' => now()->addHour()->toIso8601String()
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching cached data', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch cached data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update app settings
     */
    public function updateAppSettings(Request $request)
    {
        try {
            $validated = $request->validate([
                'language' => 'nullable|string|in:en,es,fr,de',
                'units' => 'nullable|string|in:metric,imperial',
                'theme' => 'nullable|string|in:light,dark,auto',
                'notifications_enabled' => 'nullable|boolean',
                'biometric_enabled' => 'nullable|boolean',
                'offline_mode_enabled' => 'nullable|boolean',
                'auto_sync_enabled' => 'nullable|boolean',
                'video_quality' => 'nullable|string|in:low,medium,high,auto'
            ]);

            DB::beginTransaction();

            $userId = Auth::id();

            // Update app settings
            DB::table('app_settings')->updateOrInsert(
                ['user_id' => $userId],
                array_merge($validated, [
                    'updated_at' => now()
                ])
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'App settings updated successfully',
                'settings' => $validated
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error updating app settings', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update app settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Record app usage analytics
     */
    public function recordUsageAnalytics(Request $request)
    {
        try {
            $validated = $request->validate([
                'event_type' => 'required|string',
                'event_data' => 'nullable|array',
                'screen_name' => 'nullable|string',
                'duration_seconds' => 'nullable|integer',
                'occurred_at' => 'required|date'
            ]);

            DB::table('app_usage_analytics')->insert([
                'user_id' => Auth::id(),
                'event_type' => $validated['event_type'],
                'event_data' => json_encode($validated['event_data'] ?? []),
                'screen_name' => $validated['screen_name'] ?? null,
                'duration_seconds' => $validated['duration_seconds'] ?? null,
                'occurred_at' => $validated['occurred_at'],
                'created_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Analytics recorded successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error recording analytics', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to record analytics'
            ], 500);
        }
    }

    /**
     * Private helper methods
     */
    private function parseVersion($version)
    {
        $parts = explode('.', $version);
        return ($parts[0] * 10000) + ($parts[1] * 100) + ($parts[2] ?? 0);
    }

    private function getCacheableData($key)
    {
        switch ($key) {
            case 'workout_templates':
                return DB::table('workouts')
                    ->where('is_template', true)
                    ->where('is_active', true)
                    ->get();

            case 'exercise_library':
                return DB::table('exercises')
                    ->where('is_active', true)
                    ->select('id', 'name', 'description', 'category', 'difficulty', 'video_url')
                    ->get();

            case 'meal_plans':
                return DB::table('meal_plans')
                    ->where('is_public', true)
                    ->where('is_active', true)
                    ->get();

            case 'challenges':
                return DB::table('challenges')
                    ->where('is_active', true)
                    ->where('end_date', '>=', now())
                    ->get();

            case 'leaderboard':
                return DB::table('users')
                    ->where('is_active', true)
                    ->orderBy('body_points', 'desc')
                    ->limit(100)
                    ->get(['id', 'name', 'profile_picture', 'body_points']);

            default:
                return [];
        }
    }
}