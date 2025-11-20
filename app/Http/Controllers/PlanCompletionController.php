<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Plan;
use App\Models\PlanProgress;
use App\Models\PlanHistory;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PlanCompletionController extends Controller
{
    /**
     * Complete a plan
     */
    public function completePlan(Request $request)
    {
        try {
            $validated = $request->validate([
                'plan_id' => 'required|integer|exists:plans,id',
                'completion_date' => 'nullable|date',
                'final_measurements' => 'nullable|array',
                'final_measurements.weight' => 'nullable|numeric',
                'final_measurements.body_fat' => 'nullable|numeric',
                'final_measurements.muscle_mass' => 'nullable|numeric',
                'final_measurements.waist' => 'nullable|numeric',
                'final_measurements.chest' => 'nullable|numeric',
                'final_measurements.arms' => 'nullable|numeric',
                'final_measurements.thighs' => 'nullable|numeric',
                'final_photos' => 'nullable|array',
                'final_photos.*.url' => 'required|string',
                'final_photos.*.type' => 'required|string|in:front,side,back',
                'feedback' => 'nullable|string|max:2000',
                'rating' => 'nullable|integer|min:1|max:5',
                'would_recommend' => 'nullable|boolean'
            ]);

            DB::beginTransaction();

            // Get the plan
            $plan = Plan::findOrFail($validated['plan_id']);

            // Check if user has access to this plan
            $userPlan = DB::table('user_plans')
                ->where('user_id', Auth::id())
                ->where('plan_id', $validated['plan_id'])
                ->first();

            if (!$userPlan) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this plan'
                ], 403);
            }

            // Update user_plans table
            DB::table('user_plans')
                ->where('user_id', Auth::id())
                ->where('plan_id', $validated['plan_id'])
                ->update([
                    'status' => 'completed',
                    'completed_at' => $validated['completion_date'] ?? now(),
                    'completion_percentage' => 100,
                    'rating' => $validated['rating'] ?? null,
                    'feedback' => $validated['feedback'] ?? null,
                    'would_recommend' => $validated['would_recommend'] ?? null,
                    'updated_at' => now()
                ]);

            // Save final measurements if provided
            if (!empty($validated['final_measurements'])) {
                DB::table('plan_measurements')->insert([
                    'user_id' => Auth::id(),
                    'plan_id' => $validated['plan_id'],
                    'measurement_type' => 'final',
                    'weight' => $validated['final_measurements']['weight'] ?? null,
                    'body_fat' => $validated['final_measurements']['body_fat'] ?? null,
                    'muscle_mass' => $validated['final_measurements']['muscle_mass'] ?? null,
                    'waist' => $validated['final_measurements']['waist'] ?? null,
                    'chest' => $validated['final_measurements']['chest'] ?? null,
                    'arms' => $validated['final_measurements']['arms'] ?? null,
                    'thighs' => $validated['final_measurements']['thighs'] ?? null,
                    'measured_at' => $validated['completion_date'] ?? now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Save final photos if provided
            if (!empty($validated['final_photos'])) {
                foreach ($validated['final_photos'] as $photo) {
                    DB::table('plan_photos')->insert([
                        'user_id' => Auth::id(),
                        'plan_id' => $validated['plan_id'],
                        'photo_type' => $photo['type'],
                        'photo_url' => $photo['url'],
                        'is_final' => true,
                        'uploaded_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            // Create history entry
            PlanHistory::create([
                'user_id' => Auth::id(),
                'plan_id' => $validated['plan_id'],
                'action' => 'completed',
                'details' => json_encode([
                    'completion_date' => $validated['completion_date'] ?? now(),
                    'rating' => $validated['rating'] ?? null,
                    'has_measurements' => !empty($validated['final_measurements']),
                    'has_photos' => !empty($validated['final_photos'])
                ]),
                'created_at' => now()
            ]);

            // Calculate and award completion rewards
            $rewards = $this->calculateCompletionRewards($plan, Auth::id());

            // Update user stats
            $this->updateUserPlanStats(Auth::id());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Plan completed successfully!',
                'rewards' => $rewards,
                'achievements' => $this->checkPlanAchievements(Auth::id())
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update plan progress
     */
    public function updatePlanProgress(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'week_number' => 'required|integer|min:1',
                'day_number' => 'nullable|integer|min:1|max:7',
                'workouts_completed' => 'nullable|integer|min:0',
                'meals_followed' => 'nullable|integer|min:0',
                'water_intake' => 'nullable|numeric|min:0',
                'sleep_hours' => 'nullable|numeric|min:0|max:24',
                'stress_level' => 'nullable|integer|min:1|max:10',
                'energy_level' => 'nullable|integer|min:1|max:10',
                'measurements' => 'nullable|array',
                'measurements.weight' => 'nullable|numeric',
                'measurements.body_fat' => 'nullable|numeric',
                'notes' => 'nullable|string|max:1000',
                'photos' => 'nullable|array',
                'photos.*.url' => 'required|string',
                'photos.*.type' => 'required|string|in:front,side,back,progress'
            ]);

            DB::beginTransaction();

            // Verify user has access to this plan
            $userPlan = DB::table('user_plans')
                ->where('user_id', Auth::id())
                ->where('plan_id', $id)
                ->first();

            if (!$userPlan) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this plan'
                ], 403);
            }

            // Create or update progress entry
            $progress = PlanProgress::updateOrCreate(
                [
                    'user_id' => Auth::id(),
                    'plan_id' => $id,
                    'week_number' => $validated['week_number'],
                    'day_number' => $validated['day_number'] ?? null
                ],
                [
                    'workouts_completed' => $validated['workouts_completed'] ?? 0,
                    'meals_followed' => $validated['meals_followed'] ?? 0,
                    'water_intake' => $validated['water_intake'] ?? 0,
                    'sleep_hours' => $validated['sleep_hours'] ?? 0,
                    'stress_level' => $validated['stress_level'] ?? null,
                    'energy_level' => $validated['energy_level'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'updated_at' => now()
                ]
            );

            // Save measurements if provided
            if (!empty($validated['measurements'])) {
                DB::table('plan_measurements')->insert([
                    'user_id' => Auth::id(),
                    'plan_id' => $id,
                    'progress_id' => $progress->id,
                    'measurement_type' => 'progress',
                    'week_number' => $validated['week_number'],
                    'weight' => $validated['measurements']['weight'] ?? null,
                    'body_fat' => $validated['measurements']['body_fat'] ?? null,
                    'measured_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Save progress photos if provided
            if (!empty($validated['photos'])) {
                foreach ($validated['photos'] as $photo) {
                    DB::table('plan_photos')->insert([
                        'user_id' => Auth::id(),
                        'plan_id' => $id,
                        'progress_id' => $progress->id,
                        'photo_type' => $photo['type'],
                        'photo_url' => $photo['url'],
                        'week_number' => $validated['week_number'],
                        'is_final' => false,
                        'uploaded_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            // Calculate overall progress percentage
            $progressPercentage = $this->calculateProgressPercentage($id, Auth::id());

            // Update user_plans with progress
            DB::table('user_plans')
                ->where('user_id', Auth::id())
                ->where('plan_id', $id)
                ->update([
                    'completion_percentage' => $progressPercentage,
                    'last_activity_at' => now(),
                    'updated_at' => now()
                ]);

            // Create history entry
            PlanHistory::create([
                'user_id' => Auth::id(),
                'plan_id' => $id,
                'action' => 'progress_updated',
                'details' => json_encode([
                    'week' => $validated['week_number'],
                    'day' => $validated['day_number'] ?? null,
                    'percentage' => $progressPercentage
                ]),
                'created_at' => now()
            ]);

            DB::commit();

            // Get updated statistics
            $stats = $this->getPlanStatistics($id, Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'Progress updated successfully',
                'progress' => $progress,
                'overall_percentage' => $progressPercentage,
                'stats' => $stats,
                'milestones' => $this->checkMilestones($id, Auth::id(), $progressPercentage)
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update progress',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's plan history
     */
    public function getUserPlanHistory(Request $request)
    {
        try {
            $validated = $request->validate([
                'status' => 'nullable|string|in:active,completed,paused,cancelled',
                'plan_type' => 'nullable|string|in:workout,nutrition,hybrid,challenge',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date',
                'sort_by' => 'nullable|string|in:recent,oldest,rating,completion',
                'limit' => 'nullable|integer|min:1|max:100'
            ]);

            $query = DB::table('user_plans')
                ->join('plans', 'user_plans.plan_id', '=', 'plans.id')
                ->where('user_plans.user_id', Auth::id())
                ->select(
                    'user_plans.*',
                    'plans.name as plan_name',
                    'plans.description',
                    'plans.duration_weeks',
                    'plans.difficulty',
                    'plans.plan_type',
                    'plans.thumbnail_url'
                );

            // Apply filters
            if (!empty($validated['status'])) {
                $query->where('user_plans.status', $validated['status']);
            }

            if (!empty($validated['plan_type'])) {
                $query->where('plans.plan_type', $validated['plan_type']);
            }

            if (!empty($validated['start_date'])) {
                $query->where('user_plans.started_at', '>=', $validated['start_date']);
            }

            if (!empty($validated['end_date'])) {
                $query->where('user_plans.started_at', '<=', $validated['end_date']);
            }

            // Apply sorting
            switch ($validated['sort_by'] ?? 'recent') {
                case 'oldest':
                    $query->orderBy('user_plans.started_at', 'asc');
                    break;
                case 'rating':
                    $query->orderBy('user_plans.rating', 'desc');
                    break;
                case 'completion':
                    $query->orderBy('user_plans.completion_percentage', 'desc');
                    break;
                default: // recent
                    $query->orderBy('user_plans.started_at', 'desc');
            }

            // Get results
            $limit = $validated['limit'] ?? 20;
            $history = $query->paginate($limit);

            // Get statistics
            $stats = [
                'total_plans' => DB::table('user_plans')
                    ->where('user_id', Auth::id())->count(),
                'completed_plans' => DB::table('user_plans')
                    ->where('user_id', Auth::id())
                    ->where('status', 'completed')->count(),
                'active_plans' => DB::table('user_plans')
                    ->where('user_id', Auth::id())
                    ->where('status', 'active')->count(),
                'average_rating' => DB::table('user_plans')
                    ->where('user_id', Auth::id())
                    ->where('status', 'completed')
                    ->avg('rating'),
                'total_weeks_completed' => $this->calculateTotalWeeksCompleted(Auth::id())
            ];

            // Get achievements
            $achievements = $this->getUserPlanAchievements(Auth::id());

            return response()->json([
                'success' => true,
                'history' => $history,
                'stats' => $stats,
                'achievements' => $achievements
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch plan history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Private helper methods
     */
    private function calculateProgressPercentage($planId, $userId)
    {
        $plan = Plan::find($planId);
        if (!$plan) return 0;

        $totalWeeks = $plan->duration_weeks;
        $completedWeeks = PlanProgress::where('user_id', $userId)
            ->where('plan_id', $planId)
            ->distinct('week_number')
            ->count('week_number');

        return min(100, round(($completedWeeks / $totalWeeks) * 100));
    }

    private function getPlanStatistics($planId, $userId)
    {
        return [
            'total_workouts' => PlanProgress::where('user_id', $userId)
                ->where('plan_id', $planId)
                ->sum('workouts_completed'),
            'total_meals' => PlanProgress::where('user_id', $userId)
                ->where('plan_id', $planId)
                ->sum('meals_followed'),
            'average_water' => PlanProgress::where('user_id', $userId)
                ->where('plan_id', $planId)
                ->avg('water_intake'),
            'average_sleep' => PlanProgress::where('user_id', $userId)
                ->where('plan_id', $planId)
                ->avg('sleep_hours'),
            'weight_change' => $this->calculateWeightChange($planId, $userId)
        ];
    }

    private function calculateWeightChange($planId, $userId)
    {
        $firstWeight = DB::table('plan_measurements')
            ->where('user_id', $userId)
            ->where('plan_id', $planId)
            ->orderBy('measured_at', 'asc')
            ->value('weight');

        $lastWeight = DB::table('plan_measurements')
            ->where('user_id', $userId)
            ->where('plan_id', $planId)
            ->orderBy('measured_at', 'desc')
            ->value('weight');

        if ($firstWeight && $lastWeight) {
            return round($lastWeight - $firstWeight, 2);
        }

        return null;
    }

    private function checkMilestones($planId, $userId, $percentage)
    {
        $milestones = [];

        if ($percentage >= 25 && $percentage < 50) {
            $milestones[] = '25% Complete - Keep Going!';
        } elseif ($percentage >= 50 && $percentage < 75) {
            $milestones[] = '50% Complete - Halfway There!';
        } elseif ($percentage >= 75 && $percentage < 100) {
            $milestones[] = '75% Complete - Final Stretch!';
        } elseif ($percentage == 100) {
            $milestones[] = '100% Complete - Congratulations!';
        }

        return $milestones;
    }

    private function calculateCompletionRewards($plan, $userId)
    {
        $rewards = [
            'body_points' => 0,
            'badges' => [],
            'achievements' => []
        ];

        // Base points for completion
        $rewards['body_points'] = 500;

        // Bonus points based on difficulty
        switch ($plan->difficulty) {
            case 'advanced':
                $rewards['body_points'] += 300;
                break;
            case 'intermediate':
                $rewards['body_points'] += 150;
                break;
            default:
                $rewards['body_points'] += 50;
        }

        // Duration bonus
        $rewards['body_points'] += $plan->duration_weeks * 25;

        // Award body points
        $user = User::find($userId);
        if ($user) {
            $user->increment('body_points', $rewards['body_points']);
        }

        return $rewards;
    }

    private function updateUserPlanStats($userId)
    {
        $user = User::find($userId);
        if ($user) {
            $user->increment('plans_completed');
            $user->update(['last_plan_completed_at' => now()]);
        }
    }

    private function checkPlanAchievements($userId)
    {
        $achievements = [];
        $completedCount = DB::table('user_plans')
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->count();

        if ($completedCount == 1) {
            $achievements[] = 'First Plan Completed!';
        }
        if ($completedCount == 5) {
            $achievements[] = '5 Plans Master!';
        }
        if ($completedCount == 10) {
            $achievements[] = '10 Plans Champion!';
        }

        return $achievements;
    }

    private function calculateTotalWeeksCompleted($userId)
    {
        return DB::table('user_plans')
            ->join('plans', 'user_plans.plan_id', '=', 'plans.id')
            ->where('user_plans.user_id', $userId)
            ->where('user_plans.status', 'completed')
            ->sum('plans.duration_weeks');
    }

    private function getUserPlanAchievements($userId)
    {
        return [
            'first_plan' => DB::table('user_plans')
                ->where('user_id', $userId)
                ->where('status', 'completed')
                ->exists(),
            'consistency_streak' => $this->calculateConsistencyStreak($userId),
            'perfect_completion' => DB::table('user_plans')
                ->where('user_id', $userId)
                ->where('status', 'completed')
                ->where('completion_percentage', 100)
                ->count()
        ];
    }

    private function calculateConsistencyStreak($userId)
    {
        // Calculate consecutive weeks with progress updates
        $progressDates = PlanProgress::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->pluck('created_at');

        $streak = 0;
        $lastWeek = null;

        foreach ($progressDates as $date) {
            $weekNumber = Carbon::parse($date)->weekOfYear;
            if ($lastWeek === null || $lastWeek - $weekNumber === 1) {
                $streak++;
                $lastWeek = $weekNumber;
            } else {
                break;
            }
        }

        return $streak;
    }
}