<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\HealthMetric;
use App\Models\HealthMetricHistory;
use App\Models\CoachAnalyticsSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * HealthMetricsController
 *
 * Handles health dashboard endpoints for retrieving and syncing health data
 * Used by the frontend dashboard to display health metrics
 */
class HealthMetricsController extends Controller
{
    /**
     * Get today's health metrics summary
     *
     * GET /api/customer/health/today
     */
    public function getToday(Request $request)
    {
        try {
            $user = $request->user();
            $metrics = HealthMetric::getToday($user->id);

            return response()->json([
                'success' => true,
                'data' => [
                    // Activity Rings
                    'activeCalories' => $metrics->active_calories,
                    'moveGoal' => $metrics->move_goal,
                    'exerciseMinutes' => $metrics->exercise_minutes,
                    'exerciseGoal' => $metrics->exercise_goal,
                    'standHours' => $metrics->stand_hours,
                    'standGoal' => $metrics->stand_goal,

                    // Vital Signs
                    'heartRate' => $metrics->heart_rate,
                    'restingHR' => $metrics->resting_heart_rate,
                    'hrv' => $metrics->hrv,
                    'bloodPressure' => $metrics->bloodPressure,
                    'spo2' => $metrics->blood_oxygen,
                    'respiratoryRate' => $metrics->respiratory_rate,

                    // Body Measurements
                    'weight' => $metrics->weight,
                    'bodyFat' => $metrics->body_fat,
                    'leanMass' => $metrics->lean_mass,
                    'bmi' => $metrics->bmi,

                    // Fitness Metrics
                    'steps' => $metrics->steps,
                    'distance' => $metrics->distance,
                    'flightsClimbed' => $metrics->flights_climbed,
                    'vo2Max' => $metrics->vo2_max,

                    // Nutrition
                    'caloriesConsumed' => $metrics->calories_consumed,
                    'waterIntake' => $metrics->water_intake,

                    // Sleep
                    'sleepHours' => $metrics->sleep_hours,

                    // Metadata
                    'lastSync' => $metrics->last_sync_timestamp,
                    'lastSyncSource' => $metrics->last_sync_source,

                    // Activity Ring Progress
                    'moveProgress' => $metrics->getMoveProgress(),
                    'exerciseProgress' => $metrics->getExerciseProgress(),
                    'standProgress' => $metrics->getStandProgress(),
                    'allRingsClosed' => $metrics->hasClosedAllRings(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve health metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get health metrics for a specific date
     *
     * GET /api/customer/health/date/{date}
     */
    public function getForDate(Request $request, $date)
    {
        try {
            $user = $request->user();
            $metrics = HealthMetric::getForDate($user->id, $date);

            if (!$metrics) {
                return response()->json([
                    'success' => false,
                    'message' => 'No health metrics found for this date'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $metrics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve health metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync health data to coach analytics
     * Allows coaches to see client health metrics in their dashboard
     *
     * POST /api/customer/health/sync-to-analytics
     */
    public function syncToCoachAnalytics(Request $request)
    {
        try {
            $user = $request->user();

            // Get user's coach (assuming there's a coach_id field on users table)
            $coachId = $user->coach_id;

            if (!$coachId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No coach assigned to this user'
                ], 400);
            }

            // Get today's health metrics
            $metrics = HealthMetric::getToday($user->id);

            // Prepare health data snapshot
            $healthData = [
                'date' => Carbon::today()->toDateString(),
                'userId' => $user->id,
                'userName' => $user->name,
                'metrics' => [
                    'activeCalories' => $metrics->active_calories,
                    'exerciseMinutes' => $metrics->exercise_minutes,
                    'standHours' => $metrics->stand_hours,
                    'steps' => $metrics->steps,
                    'heartRate' => $metrics->heart_rate,
                    'weight' => $metrics->weight,
                    'sleepHours' => $metrics->sleep_hours,
                    'distance' => $metrics->distance,
                    'calories' => $metrics->calories_consumed,
                    'water' => $metrics->water_intake,
                ],
                'goals' => [
                    'moveGoal' => $metrics->move_goal,
                    'exerciseGoal' => $metrics->exercise_goal,
                    'standGoal' => $metrics->stand_goal,
                ],
                'progress' => [
                    'moveProgress' => $metrics->getMoveProgress(),
                    'exerciseProgress' => $metrics->getExerciseProgress(),
                    'standProgress' => $metrics->getStandProgress(),
                    'allRingsClosed' => $metrics->hasClosedAllRings(),
                ]
            ];

            // Sync to coach analytics
            CoachAnalyticsSync::syncToCoach($user->id, $coachId, $healthData);

            return response()->json([
                'success' => true,
                'message' => 'Health data synced to coach analytics successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync health data to coach analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
