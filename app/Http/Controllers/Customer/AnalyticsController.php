<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    // ========================================================================
    // USER ANALYTICS
    // ========================================================================

    /**
     * Get analytics overview
     * GET /api/analytics/overview
     */
    public function getOverview(Request $request)
    {
        try {
            $userId = auth()->id();
            $period = $request->get('period', 'month'); // week, month, year

            $overview = [
                'total_workouts' => DB::table('workout_logs')
                    ->where('user_id', $userId)
                    ->count(),
                'total_calories' => DB::table('workout_logs')
                    ->where('user_id', $userId)
                    ->sum('calories_burned') ?? 0,
                'total_duration_minutes' => DB::table('workout_logs')
                    ->where('user_id', $userId)
                    ->sum('duration_minutes') ?? 0,
                'current_streak' => $this->calculateCurrentStreak($userId),
                'total_points' => DB::table('users')
                    ->where('id', $userId)
                    ->value('total_points') ?? 0,
                'achievements_count' => DB::table('user_achievements')
                    ->where('user_id', $userId)
                    ->count(),
                'period' => $period
            ];

            return response()->json(['success' => true, 'data' => $overview]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => [
                'total_workouts' => 0,
                'total_calories' => 0,
                'total_duration_minutes' => 0,
                'current_streak' => 0,
                'total_points' => 0,
                'achievements_count' => 0,
                'period' => 'month'
            ]]);
        }
    }

    /**
     * Get progress analytics
     * GET /api/analytics/progress
     */
    public function getProgress(Request $request)
    {
        try {
            $userId = auth()->id();
            $period = $request->get('period', 'month');
            $startDate = $this->getStartDateForPeriod($period);

            $progress = [
                'weight_progress' => $this->getWeightProgress($userId, $startDate),
                'workout_frequency' => $this->getWorkoutFrequency($userId, $startDate),
                'calories_trend' => $this->getCaloriesTrend($userId, $startDate),
                'duration_trend' => $this->getDurationTrend($userId, $startDate),
                'period' => $period
            ];

            return response()->json(['success' => true, 'data' => $progress]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => [
                'weight_progress' => [],
                'workout_frequency' => [],
                'calories_trend' => [],
                'duration_trend' => [],
                'period' => 'month'
            ]]);
        }
    }

    /**
     * Get workout analytics
     * GET /api/analytics/workouts
     */
    public function getWorkoutAnalytics(Request $request)
    {
        try {
            $userId = auth()->id();
            $period = $request->get('period', 'month');
            $startDate = $this->getStartDateForPeriod($period);

            $analytics = [
                'total_workouts' => DB::table('workout_logs')
                    ->where('user_id', $userId)
                    ->where('created_at', '>=', $startDate)
                    ->count(),
                'by_category' => DB::table('workout_logs')
                    ->select('category', DB::raw('COUNT(*) as count'))
                    ->where('user_id', $userId)
                    ->where('created_at', '>=', $startDate)
                    ->groupBy('category')
                    ->get(),
                'total_duration' => DB::table('workout_logs')
                    ->where('user_id', $userId)
                    ->where('created_at', '>=', $startDate)
                    ->sum('duration_minutes') ?? 0,
                'total_calories' => DB::table('workout_logs')
                    ->where('user_id', $userId)
                    ->where('created_at', '>=', $startDate)
                    ->sum('calories_burned') ?? 0,
                'average_intensity' => DB::table('workout_logs')
                    ->where('user_id', $userId)
                    ->where('created_at', '>=', $startDate)
                    ->avg('intensity') ?? 0,
                'period' => $period
            ];

            return response()->json(['success' => true, 'data' => $analytics]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => [
                'total_workouts' => 0,
                'by_category' => [],
                'total_duration' => 0,
                'total_calories' => 0,
                'average_intensity' => 0,
                'period' => 'month'
            ]]);
        }
    }

    /**
     * Get workout stats
     * GET /api/analytics/workout-stats
     */
    public function getWorkoutStats(Request $request)
    {
        try {
            $userId = auth()->id();

            $stats = [
                'all_time' => [
                    'total_workouts' => DB::table('workout_logs')
                        ->where('user_id', $userId)
                        ->count(),
                    'total_duration' => DB::table('workout_logs')
                        ->where('user_id', $userId)
                        ->sum('duration_minutes') ?? 0,
                    'total_calories' => DB::table('workout_logs')
                        ->where('user_id', $userId)
                        ->sum('calories_burned') ?? 0
                ],
                'this_month' => [
                    'total_workouts' => DB::table('workout_logs')
                        ->where('user_id', $userId)
                        ->whereMonth('created_at', now()->month)
                        ->count(),
                    'total_duration' => DB::table('workout_logs')
                        ->where('user_id', $userId)
                        ->whereMonth('created_at', now()->month)
                        ->sum('duration_minutes') ?? 0
                ],
                'favorite_workout' => $this->getFavoriteWorkout($userId),
                'most_active_day' => $this->getMostActiveDay($userId)
            ];

            return response()->json(['success' => true, 'data' => $stats]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => [
                'all_time' => ['total_workouts' => 0, 'total_duration' => 0, 'total_calories' => 0],
                'this_month' => ['total_workouts' => 0, 'total_duration' => 0],
                'favorite_workout' => null,
                'most_active_day' => null
            ]]);
        }
    }

    /**
     * Get nutrition analytics
     * GET /api/analytics/nutrition
     */
    public function getNutritionAnalytics(Request $request)
    {
        try {
            $userId = auth()->id();
            $period = $request->get('period', 'month');
            $startDate = $this->getStartDateForPeriod($period);

            $analytics = [
                'average_calories' => DB::table('nutrition_logs')
                    ->where('user_id', $userId)
                    ->where('created_at', '>=', $startDate)
                    ->avg('calories') ?? 0,
                'average_protein' => DB::table('nutrition_logs')
                    ->where('user_id', $userId)
                    ->where('created_at', '>=', $startDate)
                    ->avg('protein_grams') ?? 0,
                'average_carbs' => DB::table('nutrition_logs')
                    ->where('user_id', $userId)
                    ->where('created_at', '>=', $startDate)
                    ->avg('carbs_grams') ?? 0,
                'average_fat' => DB::table('nutrition_logs')
                    ->where('user_id', $userId)
                    ->where('created_at', '>=', $startDate)
                    ->avg('fat_grams') ?? 0,
                'water_intake' => DB::table('water_logs')
                    ->where('user_id', $userId)
                    ->where('created_at', '>=', $startDate)
                    ->sum('amount_ml') ?? 0,
                'period' => $period
            ];

            return response()->json(['success' => true, 'data' => $analytics]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => [
                'average_calories' => 0,
                'average_protein' => 0,
                'average_carbs' => 0,
                'average_fat' => 0,
                'water_intake' => 0,
                'period' => 'month'
            ]]);
        }
    }

    /**
     * Get nutrition stats
     * GET /api/analytics/nutrition-stats
     */
    public function getNutritionStats(Request $request)
    {
        try {
            $userId = auth()->id();

            $stats = [
                'today' => [
                    'calories' => DB::table('nutrition_logs')
                        ->where('user_id', $userId)
                        ->whereDate('created_at', today())
                        ->sum('calories') ?? 0,
                    'protein' => DB::table('nutrition_logs')
                        ->where('user_id', $userId)
                        ->whereDate('created_at', today())
                        ->sum('protein_grams') ?? 0,
                    'water' => DB::table('water_logs')
                        ->where('user_id', $userId)
                        ->whereDate('created_at', today())
                        ->sum('amount_ml') ?? 0
                ],
                'week_average' => [
                    'calories' => DB::table('nutrition_logs')
                        ->where('user_id', $userId)
                        ->where('created_at', '>=', now()->subDays(7))
                        ->avg('calories') ?? 0,
                    'protein' => DB::table('nutrition_logs')
                        ->where('user_id', $userId)
                        ->where('created_at', '>=', now()->subDays(7))
                        ->avg('protein_grams') ?? 0
                ],
                'compliance_rate' => $this->getComplianceRate($userId)
            ];

            return response()->json(['success' => true, 'data' => $stats]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => [
                'today' => ['calories' => 0, 'protein' => 0, 'water' => 0],
                'week_average' => ['calories' => 0, 'protein' => 0],
                'compliance_rate' => 0
            ]]);
        }
    }

    /**
     * Get body metrics
     * GET /api/analytics/body-metrics
     */
    public function getBodyMetrics(Request $request)
    {
        try {
            $userId = auth()->id();
            $period = $request->get('period', 'month');
            $startDate = $this->getStartDateForPeriod($period);

            $metrics = DB::table('body_metrics')
                ->where('user_id', $userId)
                ->where('created_at', '>=', $startDate)
                ->orderBy('created_at', 'asc')
                ->get();

            $analytics = [
                'weight_trend' => $metrics->pluck('weight', 'created_at'),
                'body_fat_trend' => $metrics->pluck('body_fat_percentage', 'created_at'),
                'muscle_mass_trend' => $metrics->pluck('muscle_mass', 'created_at'),
                'current_weight' => $metrics->last()->weight ?? 0,
                'weight_change' => $this->calculateWeightChange($metrics),
                'period' => $period
            ];

            return response()->json(['success' => true, 'data' => $analytics]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => [
                'weight_trend' => [],
                'body_fat_trend' => [],
                'muscle_mass_trend' => [],
                'current_weight' => 0,
                'weight_change' => 0,
                'period' => 'month'
            ]]);
        }
    }

    // ========================================================================
    // ACHIEVEMENTS & GOALS
    // ========================================================================

    /**
     * Get achievements analytics
     * GET /api/analytics/achievements
     */
    public function getAchievementsAnalytics(Request $request)
    {
        try {
            $userId = auth()->id();

            $achievements = DB::table('user_achievements')
                ->where('user_id', $userId)
                ->orderBy('earned_at', 'desc')
                ->get()
                ->map(function($achievement) {
                    $achievementData = DB::table('achievements')->find($achievement->achievement_id);
                    return [
                        'id' => $achievement->id,
                        'name' => $achievementData->name ?? 'Unknown',
                        'description' => $achievementData->description ?? '',
                        'category' => $achievementData->category ?? 'general',
                        'points' => $achievementData->points ?? 0,
                        'earned_at' => $achievement->earned_at
                    ];
                });

            $analytics = [
                'total_achievements' => $achievements->count(),
                'total_points' => $achievements->sum('points'),
                'by_category' => $achievements->groupBy('category')->map->count(),
                'recent_achievements' => $achievements->take(5),
                'completion_rate' => $this->getAchievementCompletionRate($userId)
            ];

            return response()->json(['success' => true, 'data' => $analytics]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => [
                'total_achievements' => 0,
                'total_points' => 0,
                'by_category' => [],
                'recent_achievements' => [],
                'completion_rate' => 0
            ]]);
        }
    }

    /**
     * Get goals analytics
     * GET /api/analytics/goals
     */
    public function getGoalsAnalytics(Request $request)
    {
        try {
            $userId = auth()->id();

            $goals = DB::table('user_goals')
                ->where('user_id', $userId)
                ->get();

            $analytics = [
                'total_goals' => $goals->count(),
                'active_goals' => $goals->where('status', 'active')->count(),
                'completed_goals' => $goals->where('status', 'completed')->count(),
                'completion_rate' => $goals->count() > 0
                    ? ($goals->where('status', 'completed')->count() / $goals->count()) * 100
                    : 0,
                'by_category' => $goals->groupBy('category')->map->count(),
                'upcoming_deadlines' => $goals->where('status', 'active')
                    ->sortBy('target_date')
                    ->take(5)
            ];

            return response()->json(['success' => true, 'data' => $analytics]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => [
                'total_goals' => 0,
                'active_goals' => 0,
                'completed_goals' => 0,
                'completion_rate' => 0,
                'by_category' => [],
                'upcoming_deadlines' => []
            ]]);
        }
    }

    /**
     * Get goal progress
     * GET /api/analytics/goal-progress/{id}
     */
    public function getGoalProgress($id)
    {
        try {
            $userId = auth()->id();

            $goal = DB::table('user_goals')
                ->where('id', $id)
                ->where('user_id', $userId)
                ->first();

            if (!$goal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Goal not found'
                ], 404);
            }

            $progress = [
                'goal_id' => $goal->id,
                'title' => $goal->title,
                'target_value' => $goal->target_value,
                'current_value' => $goal->current_value ?? 0,
                'progress_percentage' => $goal->target_value > 0
                    ? ($goal->current_value / $goal->target_value) * 100
                    : 0,
                'status' => $goal->status,
                'target_date' => $goal->target_date,
                'days_remaining' => $goal->target_date
                    ? now()->diffInDays($goal->target_date, false)
                    : null
            ];

            return response()->json(['success' => true, 'data' => $progress]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching goal progress',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get trends
     * GET /api/analytics/trends
     */
    public function getTrends(Request $request)
    {
        try {
            $userId = auth()->id();
            $period = $request->get('period', 'month');

            $trends = [
                'workout_trend' => $this->getWorkoutTrend($userId, $period),
                'weight_trend' => $this->getWeightTrendData($userId, $period),
                'calories_trend' => $this->getNutritionTrend($userId, $period),
                'streak_trend' => $this->getStreakTrend($userId, $period),
                'period' => $period
            ];

            return response()->json(['success' => true, 'data' => $trends]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => [
                'workout_trend' => 'stable',
                'weight_trend' => 'stable',
                'calories_trend' => 'stable',
                'streak_trend' => 'stable',
                'period' => 'month'
            ]]);
        }
    }

    /**
     * Get milestones
     * GET /api/analytics/milestones
     */
    public function getMilestones(Request $request)
    {
        try {
            $userId = auth()->id();

            $milestones = [
                'workouts' => [
                    ['milestone' => 10, 'achieved' => $this->checkWorkoutMilestone($userId, 10)],
                    ['milestone' => 50, 'achieved' => $this->checkWorkoutMilestone($userId, 50)],
                    ['milestone' => 100, 'achieved' => $this->checkWorkoutMilestone($userId, 100)],
                    ['milestone' => 500, 'achieved' => $this->checkWorkoutMilestone($userId, 500)]
                ],
                'streak' => [
                    ['milestone' => 7, 'achieved' => $this->checkStreakMilestone($userId, 7)],
                    ['milestone' => 30, 'achieved' => $this->checkStreakMilestone($userId, 30)],
                    ['milestone' => 90, 'achieved' => $this->checkStreakMilestone($userId, 90)],
                    ['milestone' => 365, 'achieved' => $this->checkStreakMilestone($userId, 365)]
                ],
                'points' => [
                    ['milestone' => 1000, 'achieved' => $this->checkPointsMilestone($userId, 1000)],
                    ['milestone' => 5000, 'achieved' => $this->checkPointsMilestone($userId, 5000)],
                    ['milestone' => 10000, 'achieved' => $this->checkPointsMilestone($userId, 10000)]
                ]
            ];

            return response()->json(['success' => true, 'data' => $milestones]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => [
                'workouts' => [],
                'streak' => [],
                'points' => []
            ]]);
        }
    }

    // ========================================================================
    // STREAKS & CONSISTENCY
    // ========================================================================

    /**
     * Get streak analytics
     * GET /api/analytics/streaks
     */
    public function getStreakAnalytics(Request $request)
    {
        try {
            $userId = auth()->id();

            $analytics = [
                'current_streak' => $this->calculateCurrentStreak($userId),
                'longest_streak' => DB::table('users')
                    ->where('id', $userId)
                    ->value('longest_streak') ?? 0,
                'streak_history' => $this->getStreakHistory($userId),
                'consistency_score' => $this->calculateConsistencyScore($userId)
            ];

            return response()->json(['success' => true, 'data' => $analytics]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => [
                'current_streak' => 0,
                'longest_streak' => 0,
                'streak_history' => [],
                'consistency_score' => 0
            ]]);
        }
    }

    /**
     * Get consistency metrics
     * GET /api/analytics/consistency
     */
    public function getConsistencyMetrics(Request $request)
    {
        try {
            $userId = auth()->id();
            $period = $request->get('period', 'month');

            $metrics = [
                'workout_consistency' => $this->getWorkoutConsistency($userId, $period),
                'nutrition_logging_consistency' => $this->getNutritionConsistency($userId, $period),
                'weekly_activity' => $this->getWeeklyActivityPattern($userId),
                'best_day' => $this->getBestActivityDay($userId),
                'period' => $period
            ];

            return response()->json(['success' => true, 'data' => $metrics]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => [
                'workout_consistency' => 0,
                'nutrition_logging_consistency' => 0,
                'weekly_activity' => [],
                'best_day' => null,
                'period' => 'month'
            ]]);
        }
    }

    // ========================================================================
    // EXPORT
    // ========================================================================

    /**
     * Export analytics to PDF
     * GET /api/analytics/export/pdf
     */
    public function exportPDF(Request $request)
    {
        try {
            $userId = auth()->id();
            $period = $request->get('period', 'month');

            // TODO: Generate PDF report
            return response()->json([
                'success' => true,
                'message' => 'PDF export feature coming soon',
                'data' => [
                    'format' => 'pdf',
                    'period' => $period
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error exporting PDF',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export analytics to CSV
     * GET /api/analytics/export/csv
     */
    public function exportCSV(Request $request)
    {
        try {
            $userId = auth()->id();
            $period = $request->get('period', 'month');

            // TODO: Generate CSV export
            return response()->json([
                'success' => true,
                'message' => 'CSV export feature coming soon',
                'data' => [
                    'format' => 'csv',
                    'period' => $period
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error exporting CSV',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ========================================================================
    // BODY POINTS & GAMIFICATION
    // ========================================================================

    /**
     * Get body points breakdown
     * GET /api/analytics/body-points
     */
    public function getBodyPointsBreakdown(Request $request)
    {
        try {
            $userId = auth()->id();

            $user = DB::table('users')->find($userId);
            $totalPoints = $user->total_points ?? 0;

            $breakdown = [
                'total_points' => $totalPoints,
                'level' => $this->calculateLevel($totalPoints),
                'next_level_points' => $this->getNextLevelPoints($totalPoints),
                'points_to_next_level' => $this->getPointsToNextLevel($totalPoints),
                'sources' => [
                    'workouts' => DB::table('points_history')
                        ->where('user_id', $userId)
                        ->where('source', 'workout')
                        ->sum('points') ?? 0,
                    'achievements' => DB::table('points_history')
                        ->where('user_id', $userId)
                        ->where('source', 'achievement')
                        ->sum('points') ?? 0,
                    'streaks' => DB::table('points_history')
                        ->where('user_id', $userId)
                        ->where('source', 'streak')
                        ->sum('points') ?? 0,
                    'challenges' => DB::table('points_history')
                        ->where('user_id', $userId)
                        ->where('source', 'challenge')
                        ->sum('points') ?? 0
                ]
            ];

            return response()->json(['success' => true, 'data' => $breakdown]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => [
                'total_points' => 0,
                'level' => 1,
                'next_level_points' => 100,
                'points_to_next_level' => 100,
                'sources' => [
                    'workouts' => 0,
                    'achievements' => 0,
                    'streaks' => 0,
                    'challenges' => 0
                ]
            ]]);
        }
    }

    /**
     * Get points history
     * GET /api/analytics/points-history
     */
    public function getPointsHistory(Request $request)
    {
        try {
            $userId = auth()->id();
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 50);

            $history = DB::table('points_history')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $history->items(),
                'pagination' => [
                    'current_page' => $history->currentPage(),
                    'total_pages' => $history->lastPage(),
                    'total_items' => $history->total(),
                    'per_page' => $history->perPage()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'total_pages' => 1,
                    'total_items' => 0,
                    'per_page' => 50
                ]
            ]);
        }
    }

    /**
     * Get level progression
     * GET /api/analytics/level-progression
     */
    public function getLevelProgression(Request $request)
    {
        try {
            $userId = auth()->id();
            $user = DB::table('users')->find($userId);
            $totalPoints = $user->total_points ?? 0;
            $currentLevel = $this->calculateLevel($totalPoints);

            $progression = [
                'current_level' => $currentLevel,
                'current_points' => $totalPoints,
                'next_level' => $currentLevel + 1,
                'points_for_next_level' => $this->getNextLevelPoints($totalPoints),
                'progress_percentage' => $this->getLevelProgressPercentage($totalPoints),
                'rank' => $this->getUserRank($userId)
            ];

            return response()->json(['success' => true, 'data' => $progression]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => [
                'current_level' => 1,
                'current_points' => 0,
                'next_level' => 2,
                'points_for_next_level' => 100,
                'progress_percentage' => 0,
                'rank' => 0
            ]]);
        }
    }

    /**
     * Get badges
     * GET /api/analytics/badges
     */
    public function getBadges(Request $request)
    {
        try {
            $userId = auth()->id();

            $badges = DB::table('user_badges')
                ->where('user_id', $userId)
                ->get()
                ->map(function($userBadge) {
                    $badge = DB::table('badges')->find($userBadge->badge_id);
                    return [
                        'id' => $badge->id ?? null,
                        'name' => $badge->name ?? 'Unknown',
                        'description' => $badge->description ?? '',
                        'icon' => $badge->icon ?? null,
                        'rarity' => $badge->rarity ?? 'common',
                        'earned_at' => $userBadge->earned_at
                    ];
                });

            return response()->json(['success' => true, 'data' => $badges]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => []]);
        }
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    private function calculateCurrentStreak($userId)
    {
        try {
            $workouts = DB::table('workout_logs')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->pluck('created_at')
                ->map(function($date) {
                    return \Carbon\Carbon::parse($date)->format('Y-m-d');
                })
                ->unique()
                ->values();

            $streak = 0;
            $currentDate = now()->format('Y-m-d');

            foreach ($workouts as $workoutDate) {
                if ($workoutDate == $currentDate || $workoutDate == now()->subDay()->format('Y-m-d')) {
                    $streak++;
                    $currentDate = \Carbon\Carbon::parse($currentDate)->subDay()->format('Y-m-d');
                } else {
                    break;
                }
            }

            return $streak;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getStartDateForPeriod($period)
    {
        switch ($period) {
            case 'week':
                return now()->subDays(7);
            case 'year':
                return now()->subYear();
            case 'month':
            default:
                return now()->subDays(30);
        }
    }

    private function getWeightProgress($userId, $startDate)
    {
        try {
            return DB::table('body_metrics')
                ->where('user_id', $userId)
                ->where('created_at', '>=', $startDate)
                ->orderBy('created_at', 'asc')
                ->pluck('weight', 'created_at');
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getWorkoutFrequency($userId, $startDate)
    {
        try {
            return DB::table('workout_logs')
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
                ->where('user_id', $userId)
                ->where('created_at', '>=', $startDate)
                ->groupBy('date')
                ->pluck('count', 'date');
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getCaloriesTrend($userId, $startDate)
    {
        try {
            return DB::table('workout_logs')
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(calories_burned) as total'))
                ->where('user_id', $userId)
                ->where('created_at', '>=', $startDate)
                ->groupBy('date')
                ->pluck('total', 'date');
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getDurationTrend($userId, $startDate)
    {
        try {
            return DB::table('workout_logs')
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(duration_minutes) as total'))
                ->where('user_id', $userId)
                ->where('created_at', '>=', $startDate)
                ->groupBy('date')
                ->pluck('total', 'date');
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getFavoriteWorkout($userId)
    {
        try {
            return DB::table('workout_logs')
                ->select('workout_type', DB::raw('COUNT(*) as count'))
                ->where('user_id', $userId)
                ->groupBy('workout_type')
                ->orderBy('count', 'desc')
                ->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getMostActiveDay($userId)
    {
        try {
            return DB::table('workout_logs')
                ->select(DB::raw('DAYNAME(created_at) as day'), DB::raw('COUNT(*) as count'))
                ->where('user_id', $userId)
                ->groupBy('day')
                ->orderBy('count', 'desc')
                ->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getComplianceRate($userId)
    {
        try {
            $targetDays = 7;
            $loggedDays = DB::table('nutrition_logs')
                ->where('user_id', $userId)
                ->where('created_at', '>=', now()->subDays(7))
                ->distinct()
                ->count(DB::raw('DATE(created_at)'));

            return ($loggedDays / $targetDays) * 100;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function calculateWeightChange($metrics)
    {
        if ($metrics->isEmpty()) return 0;

        $first = $metrics->first();
        $last = $metrics->last();

        return ($last->weight ?? 0) - ($first->weight ?? 0);
    }

    private function getAchievementCompletionRate($userId)
    {
        try {
            $totalAchievements = DB::table('achievements')->count();
            $earnedAchievements = DB::table('user_achievements')
                ->where('user_id', $userId)
                ->count();

            return $totalAchievements > 0
                ? ($earnedAchievements / $totalAchievements) * 100
                : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getWorkoutTrend($userId, $period)
    {
        // Simplified trend calculation
        return 'stable';
    }

    private function getWeightTrendData($userId, $period)
    {
        return 'stable';
    }

    private function getNutritionTrend($userId, $period)
    {
        return 'stable';
    }

    private function getStreakTrend($userId, $period)
    {
        return 'stable';
    }

    private function checkWorkoutMilestone($userId, $milestone)
    {
        try {
            $count = DB::table('workout_logs')
                ->where('user_id', $userId)
                ->count();
            return $count >= $milestone;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkStreakMilestone($userId, $milestone)
    {
        try {
            $longestStreak = DB::table('users')
                ->where('id', $userId)
                ->value('longest_streak') ?? 0;
            return $longestStreak >= $milestone;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkPointsMilestone($userId, $milestone)
    {
        try {
            $points = DB::table('users')
                ->where('id', $userId)
                ->value('total_points') ?? 0;
            return $points >= $milestone;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getStreakHistory($userId)
    {
        // TODO: Implement streak history tracking
        return [];
    }

    private function calculateConsistencyScore($userId)
    {
        // TODO: Implement consistency score calculation
        return 0;
    }

    private function getWorkoutConsistency($userId, $period)
    {
        return 0;
    }

    private function getNutritionConsistency($userId, $period)
    {
        return 0;
    }

    private function getWeeklyActivityPattern($userId)
    {
        return [];
    }

    private function getBestActivityDay($userId)
    {
        return null;
    }

    private function calculateLevel($points)
    {
        return floor($points / 100) + 1;
    }

    private function getNextLevelPoints($points)
    {
        $currentLevel = $this->calculateLevel($points);
        return $currentLevel * 100;
    }

    private function getPointsToNextLevel($points)
    {
        return $this->getNextLevelPoints($points) - $points;
    }

    private function getLevelProgressPercentage($points)
    {
        $currentLevel = $this->calculateLevel($points);
        $pointsInCurrentLevel = $points - (($currentLevel - 1) * 100);
        return ($pointsInCurrentLevel / 100) * 100;
    }

    private function getUserRank($userId)
    {
        try {
            $rank = DB::table('users')
                ->where('total_points', '>', function($query) use ($userId) {
                    $query->select('total_points')
                          ->from('users')
                          ->where('id', $userId);
                })
                ->count();

            return $rank + 1;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
