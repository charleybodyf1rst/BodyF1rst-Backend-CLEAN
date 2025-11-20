<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkoutPlannerService
{
    /**
     * Workout templates by focus area.
     */
    private array $workoutTemplates = [
        'push' => [
            'Bench Press' => '4 sets x 8-10 reps',
            'Overhead Press' => '3 sets x 8-10 reps',
            'Incline Dumbbell Press' => '3 sets x 10-12 reps',
            'Tricep Dips' => '3 sets x 12-15 reps',
            'Push-ups' => '3 sets x max reps'
        ],
        'pull' => [
            'Pull-ups/Lat Pulldown' => '4 sets x 8-10 reps',
            'Barbell Rows' => '4 sets x 8-10 reps',
            'Cable Rows' => '3 sets x 10-12 reps',
            'Face Pulls' => '3 sets x 15-20 reps',
            'Bicep Curls' => '3 sets x 12-15 reps'
        ],
        'legs' => [
            'Squats' => '4 sets x 8-10 reps',
            'Romanian Deadlifts' => '3 sets x 8-10 reps',
            'Bulgarian Split Squats' => '3 sets x 10-12 each leg',
            'Leg Curls' => '3 sets x 12-15 reps',
            'Calf Raises' => '4 sets x 15-20 reps'
        ],
        'full' => [
            'Deadlifts' => '3 sets x 5 reps',
            'Squats' => '3 sets x 8-10 reps',
            'Pull-ups' => '3 sets x 8-10 reps',
            'Overhead Press' => '3 sets x 8-10 reps',
            'Plank' => '3 sets x 60 seconds'
        ],
        'cardio' => [
            'Warm-up Walk' => '5 minutes',
            'High Intensity Intervals' => '20 minutes (30s on, 30s off)',
            'Steady State Cardio' => '15 minutes',
            'Cool-down Walk' => '5 minutes',
            'Stretching' => '10 minutes'
        ]
    ];

    /**
     * Create a workout plan based on focus and duration.
     *
     * @param array $args
     * @return array
     */
    public function planWorkout(array $args): array
    {
        try {
            // Validate required parameters
            if (empty($args['focus'])) {
                return ['ok' => false, 'error' => 'Missing required field: focus'];
            }

            // Sanitize input
            $focus = strtolower(trim($args['focus']));
            $duration = isset($args['duration_min']) ? (int) $args['duration_min'] : 45;

            // Validate focus area
            if (!array_key_exists($focus, $this->workoutTemplates)) {
                return ['ok' => false, 'error' => 'Invalid focus area. Must be: push, pull, legs, full, or cardio'];
            }

            // Validate duration
            if ($duration < 10 || $duration > 180) {
                return ['ok' => false, 'error' => 'Duration must be between 10 and 180 minutes'];
            }

            // Generate workout plan
            $planId = Str::uuid()->toString();
            $exercises = $this->workoutTemplates[$focus];
            
            // Adjust exercises based on duration
            if ($duration < 30) {
                $exercises = array_slice($exercises, 0, 3, true);
            } elseif ($duration < 60) {
                $exercises = array_slice($exercises, 0, 4, true);
            }

            $workoutPlan = [
                'id' => $planId,
                'focus' => $focus,
                'duration_min' => $duration,
                'exercises' => $exercises,
                'created_at' => now()->toISOString(),
                'user_id' => auth()->id() ?? 1
            ];

            // Save to workout_logs table for persistence
            try {
                DB::table('workout_logs')->insert([
                    'user_id' => $workoutPlan['user_id'],
                    'workout_type' => $focus,
                    'duration_minutes' => $duration,
                    'exercises' => json_encode($exercises),
                    'calories_burned' => $this->estimateCalories($focus, $duration),
                    'logged_at' => now(),
                    'completion_status' => 'planned', // User hasn't completed it yet
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                Log::info('Workout plan saved to database', [
                    'plan_id' => $planId,
                    'user_id' => $workoutPlan['user_id']
                ]);
            } catch (\Exception $dbError) {
                // Log error but don't fail the request - graceful degradation
                Log::error('Failed to save workout to database', [
                    'error' => $dbError->getMessage(),
                    'plan_id' => $planId
                ]);
            }

            return [
                'ok' => true,
                'id' => $planId,
                'message' => 'Workout plan created successfully',
                'plan' => [
                    'focus' => $focus,
                    'duration_min' => $duration,
                    'exercises' => $exercises,
                    'estimated_calories' => $this->estimateCalories($focus, $duration)
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create workout plan', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'args' => $args
            ]);

            return [
                'ok' => false,
                'error' => 'Failed to create workout plan: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Estimate calories burned based on workout type and duration.
     *
     * @param string $focus
     * @param int $duration
     * @return int
     */
    private function estimateCalories(string $focus, int $duration): int
    {
        $caloriesPerMinute = match($focus) {
            'cardio' => 8,
            'legs' => 6,
            'full' => 7,
            'push', 'pull' => 5,
            default => 5
        };

        return $caloriesPerMinute * $duration;
    }
}
