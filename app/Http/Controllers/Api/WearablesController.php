<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\HealthMetric;
use App\Models\HealthMetricHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * WearablesController
 *
 * Handles health data sync from wearable devices (HealthKit & Google Fit)
 * Receives health metrics from iOS and Android apps and stores them in the database
 */
class WearablesController extends Controller
{
    /**
     * Sync activity ring data (Move/Exercise/Stand)
     *
     * POST /api/wearables/sync/activity
     */
    public function syncActivity(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'activeCalories' => 'required|integer|min:0',
            'exerciseMinutes' => 'required|integer|min:0',
            'standHours' => 'required|integer|min:0',
            'moveGoal' => 'nullable|integer|min:0',
            'exerciseGoal' => 'nullable|integer|min:0',
            'standGoal' => 'nullable|integer|min:0',
            'source' => 'required|in:HealthKit,GoogleFit',
            'date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            $date = $request->input('date', Carbon::today()->toDateString());

            $metrics = HealthMetric::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'date' => $date,
                ],
                [
                    'active_calories' => $request->input('activeCalories'),
                    'exercise_minutes' => $request->input('exerciseMinutes'),
                    'stand_hours' => $request->input('standHours'),
                    'move_goal' => $request->input('moveGoal', 500),
                    'exercise_goal' => $request->input('exerciseGoal', 30),
                    'stand_goal' => $request->input('standGoal', 12),
                    'last_sync_source' => $request->input('source'),
                    'last_sync_timestamp' => now(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Activity data synced successfully',
                'data' => $metrics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync activity data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync steps data
     *
     * POST /api/wearables/sync/steps
     */
    public function syncSteps(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'steps' => 'required|integer|min:0',
            'source' => 'required|in:HealthKit,GoogleFit',
            'date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $user = $request->user();
            $date = $request->input('date', Carbon::today()->toDateString());

            $metrics = HealthMetric::updateOrCreate(
                ['user_id' => $user->id, 'date' => $date],
                [
                    'steps' => $request->input('steps'),
                    'last_sync_source' => $request->input('source'),
                    'last_sync_timestamp' => now(),
                ]
            );

            return response()->json(['success' => true, 'message' => 'Steps synced successfully', 'data' => $metrics]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to sync steps', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Sync heart rate data
     *
     * POST /api/wearables/sync/heart-rate
     */
    public function syncHeartRate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'heartRate' => 'required|integer|min:0',
            'restingHeartRate' => 'nullable|integer|min:0',
            'source' => 'required|in:HealthKit,GoogleFit',
            'recordedAt' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $user = $request->user();
            $date = Carbon::parse($request->input('recordedAt', now()))->toDateString();

            // Update daily summary
            HealthMetric::updateOrCreate(
                ['user_id' => $user->id, 'date' => $date],
                [
                    'heart_rate' => $request->input('heartRate'),
                    'resting_heart_rate' => $request->input('restingHeartRate'),
                    'last_sync_source' => $request->input('source'),
                    'last_sync_timestamp' => now(),
                ]
            );

            // Store in history for time-series data
            HealthMetricHistory::record(
                $user->id,
                'heart_rate',
                $request->input('heartRate'),
                'bpm',
                null,
                $request->input('source'),
                $request->input('recordedAt', now())
            );

            return response()->json(['success' => true, 'message' => 'Heart rate synced successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to sync heart rate', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Sync blood pressure data
     *
     * POST /api/wearables/sync/blood-pressure
     */
    public function syncBloodPressure(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'systolic' => 'required|integer|min:0',
            'diastolic' => 'required|integer|min:0',
            'source' => 'required|in:HealthKit,GoogleFit',
            'recordedAt' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $user = $request->user();
            $date = Carbon::parse($request->input('recordedAt', now()))->toDateString();

            HealthMetric::updateOrCreate(
                ['user_id' => $user->id, 'date' => $date],
                [
                    'blood_pressure_systolic' => $request->input('systolic'),
                    'blood_pressure_diastolic' => $request->input('diastolic'),
                    'last_sync_source' => $request->input('source'),
                    'last_sync_timestamp' => now(),
                ]
            );

            HealthMetricHistory::record(
                $user->id,
                'blood_pressure',
                $request->input('systolic'),
                'mmHg',
                ['systolic' => $request->input('systolic'), 'diastolic' => $request->input('diastolic')],
                $request->input('source'),
                $request->input('recordedAt', now())
            );

            return response()->json(['success' => true, 'message' => 'Blood pressure synced successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to sync blood pressure', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Sync weight data
     *
     * POST /api/wearables/sync/weight
     */
    public function syncWeight(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'weight' => 'required|numeric|min:0',
            'bodyFat' => 'nullable|numeric|min:0|max:100',
            'leanMass' => 'nullable|numeric|min:0',
            'bmi' => 'nullable|numeric|min:0',
            'source' => 'required|in:HealthKit,GoogleFit,Manual',
            'recordedAt' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $user = $request->user();
            $date = Carbon::parse($request->input('recordedAt', now()))->toDateString();

            HealthMetric::updateOrCreate(
                ['user_id' => $user->id, 'date' => $date],
                [
                    'weight' => $request->input('weight'),
                    'body_fat' => $request->input('bodyFat'),
                    'lean_mass' => $request->input('leanMass'),
                    'bmi' => $request->input('bmi'),
                    'last_sync_source' => $request->input('source'),
                    'last_sync_timestamp' => now(),
                ]
            );

            HealthMetricHistory::record(
                $user->id,
                'weight',
                $request->input('weight'),
                'lbs',
                [
                    'bodyFat' => $request->input('bodyFat'),
                    'leanMass' => $request->input('leanMass'),
                    'bmi' => $request->input('bmi'),
                ],
                $request->input('source'),
                $request->input('recordedAt', now())
            );

            return response()->json(['success' => true, 'message' => 'Weight data synced successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to sync weight', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Sync sleep data
     *
     * POST /api/wearables/sync/sleep
     */
    public function syncSleep(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sleepHours' => 'required|numeric|min:0|max:24',
            'source' => 'required|in:HealthKit,GoogleFit',
            'date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $user = $request->user();
            $date = $request->input('date', Carbon::today()->toDateString());

            HealthMetric::updateOrCreate(
                ['user_id' => $user->id, 'date' => $date],
                [
                    'sleep_hours' => $request->input('sleepHours'),
                    'last_sync_source' => $request->input('source'),
                    'last_sync_timestamp' => now(),
                ]
            );

            return response()->json(['success' => true, 'message' => 'Sleep data synced successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to sync sleep', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Sync distance data
     *
     * POST /api/wearables/sync/distance
     */
    public function syncDistance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'distance' => 'required|numeric|min:0',
            'source' => 'required|in:HealthKit,GoogleFit',
            'date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $user = $request->user();
            $date = $request->input('date', Carbon::today()->toDateString());

            HealthMetric::updateOrCreate(
                ['user_id' => $user->id, 'date' => $date],
                [
                    'distance' => $request->input('distance'),
                    'last_sync_source' => $request->input('source'),
                    'last_sync_timestamp' => now(),
                ]
            );

            return response()->json(['success' => true, 'message' => 'Distance synced successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to sync distance', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Sync nutrition data (calories & water)
     *
     * POST /api/wearables/sync/nutrition
     */
    public function syncNutrition(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'calories' => 'nullable|integer|min:0',
            'water' => 'nullable|integer|min:0',
            'source' => 'required|in:HealthKit,GoogleFit,Manual',
            'date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $user = $request->user();
            $date = $request->input('date', Carbon::today()->toDateString());

            $updateData = [
                'last_sync_source' => $request->input('source'),
                'last_sync_timestamp' => now(),
            ];

            if ($request->has('calories')) {
                $updateData['calories_consumed'] = $request->input('calories');
            }

            if ($request->has('water')) {
                $updateData['water_intake'] = $request->input('water');
            }

            HealthMetric::updateOrCreate(
                ['user_id' => $user->id, 'date' => $date],
                $updateData
            );

            return response()->json(['success' => true, 'message' => 'Nutrition data synced successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to sync nutrition', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Bulk sync all health metrics at once
     * Used for comprehensive wearable sync
     *
     * POST /api/wearables/sync/bulk
     */
    public function syncBulk(Request $request)
    {
        try {
            $user = $request->user();
            $date = $request->input('date', Carbon::today()->toDateString());
            $source = $request->input('source', 'HealthKit');

            $updateData = array_filter([
                'active_calories' => $request->input('activeCalories'),
                'exercise_minutes' => $request->input('exerciseMinutes'),
                'stand_hours' => $request->input('standHours'),
                'steps' => $request->input('steps'),
                'distance' => $request->input('distance'),
                'flights_climbed' => $request->input('flightsClimbed'),
                'heart_rate' => $request->input('heartRate'),
                'resting_heart_rate' => $request->input('restingHeartRate'),
                'hrv' => $request->input('hrv'),
                'blood_pressure_systolic' => $request->input('bloodPressure.systolic'),
                'blood_pressure_diastolic' => $request->input('bloodPressure.diastolic'),
                'blood_oxygen' => $request->input('bloodOxygen'),
                'respiratory_rate' => $request->input('respiratoryRate'),
                'weight' => $request->input('weight'),
                'body_fat' => $request->input('bodyFat'),
                'lean_mass' => $request->input('leanMass'),
                'bmi' => $request->input('bmi'),
                'vo2_max' => $request->input('vo2Max'),
                'calories_consumed' => $request->input('calories'),
                'water_intake' => $request->input('water'),
                'sleep_hours' => $request->input('sleepHours'),
            ], function ($value) {
                return !is_null($value);
            });

            $updateData['last_sync_source'] = $source;
            $updateData['last_sync_timestamp'] = now();

            $metrics = HealthMetric::updateOrCreate(
                ['user_id' => $user->id, 'date' => $date],
                $updateData
            );

            return response()->json([
                'success' => true,
                'message' => 'All health metrics synced successfully',
                'data' => $metrics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync health metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk sync multiple days of health data
     * Enhanced bulk sync for catching up on missed data
     *
     * POST /api/wearables/sync/bulk-multi-day
     */
    public function syncBulkMultiDay(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'data' => 'required|array',
            'data.*.date' => 'required|date',
            'data.*.metrics' => 'required|array',
            'source' => 'required|in:HealthKit,GoogleFit',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            $source = $request->input('source');
            $dataPoints = $request->input('data');

            $syncedCount = 0;
            $failedCount = 0;
            $syncedDates = [];
            $errors = [];

            foreach ($dataPoints as $dayData) {
                try {
                    $date = $dayData['date'];
                    $metrics = $dayData['metrics'];

                    $updateData = array_filter([
                        'active_calories' => $metrics['activeCalories'] ?? null,
                        'exercise_minutes' => $metrics['exerciseMinutes'] ?? null,
                        'stand_hours' => $metrics['standHours'] ?? null,
                        'steps' => $metrics['steps'] ?? null,
                        'distance' => $metrics['distance'] ?? null,
                        'flights_climbed' => $metrics['flightsClimbed'] ?? null,
                        'heart_rate' => $metrics['heartRate'] ?? null,
                        'resting_heart_rate' => $metrics['restingHeartRate'] ?? null,
                        'hrv' => $metrics['hrv'] ?? null,
                        'blood_pressure_systolic' => $metrics['bloodPressure']['systolic'] ?? null,
                        'blood_pressure_diastolic' => $metrics['bloodPressure']['diastolic'] ?? null,
                        'blood_oxygen' => $metrics['bloodOxygen'] ?? null,
                        'respiratory_rate' => $metrics['respiratoryRate'] ?? null,
                        'weight' => $metrics['weight'] ?? null,
                        'body_fat' => $metrics['bodyFat'] ?? null,
                        'lean_mass' => $metrics['leanMass'] ?? null,
                        'bmi' => $metrics['bmi'] ?? null,
                        'vo2_max' => $metrics['vo2Max'] ?? null,
                        'calories_consumed' => $metrics['calories'] ?? null,
                        'water_intake' => $metrics['water'] ?? null,
                        'sleep_hours' => $metrics['sleepHours'] ?? null,
                    ], function ($value) {
                        return !is_null($value);
                    });

                    if (empty($updateData)) {
                        continue;
                    }

                    $updateData['last_sync_source'] = $source;
                    $updateData['last_sync_timestamp'] = now();

                    HealthMetric::updateOrCreate(
                        ['user_id' => $user->id, 'date' => $date],
                        $updateData
                    );

                    $syncedCount++;
                    $syncedDates[] = $date;

                } catch (\Exception $e) {
                    $failedCount++;
                    $errors[] = [
                        'date' => $date ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Synced {$syncedCount} days of health data" . ($failedCount > 0 ? " ({$failedCount} failed)" : ''),
                'summary' => [
                    'totalRequested' => count($dataPoints),
                    'synced' => $syncedCount,
                    'failed' => $failedCount,
                    'syncedDates' => $syncedDates,
                ],
                'errors' => $errors,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync multi-day health metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sync status and latest synced data
     *
     * GET /api/wearables/sync-status
     */
    public function getSyncStatus(Request $request)
    {
        try {
            $user = $request->user();

            $latestMetric = HealthMetric::where('user_id', $user->id)
                ->orderBy('date', 'desc')
                ->first();

            $metricsCount = HealthMetric::where('user_id', $user->id)->count();

            $lastWeekMetrics = HealthMetric::where('user_id', $user->id)
                ->whereBetween('date', [
                    Carbon::now()->subDays(7)->toDateString(),
                    Carbon::now()->toDateString()
                ])
                ->count();

            return response()->json([
                'success' => true,
                'syncStatus' => [
                    'lastSyncDate' => $latestMetric->date ?? null,
                    'lastSyncTime' => $latestMetric->last_sync_timestamp ?? null,
                    'lastSyncSource' => $latestMetric->last_sync_source ?? null,
                    'totalDaysSynced' => $metricsCount,
                    'lastWeekSynced' => $lastWeekMetrics,
                    'isSyncedToday' => $latestMetric && $latestMetric->date === Carbon::today()->toDateString(),
                ],
                'latestData' => $latestMetric ? [
                    'date' => $latestMetric->date,
                    'steps' => $latestMetric->steps,
                    'activeCalories' => $latestMetric->active_calories,
                    'exerciseMinutes' => $latestMetric->exercise_minutes,
                    'heartRate' => $latestMetric->heart_rate,
                    'weight' => $latestMetric->weight,
                ] : null,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get sync status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
