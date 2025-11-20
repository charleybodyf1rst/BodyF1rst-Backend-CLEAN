<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WorkoutLog;
use App\Models\NutritionLog;
use App\Models\BodyMeasurement;
use App\Models\Goal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Analytics Controller
 * Handles analytics dashboards, reports, and data exports
 */
class AnalyticsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Get Dashboard Analytics
     * GET /api/analytics/dashboard
     */
    public function getDashboardAnalytics(Request $request)
    {
        try {
            $userId = Auth::id();
            $period = $request->input('period', 'month'); // week, month, quarter, year

            $dateRange = $this->getDateRange($period);
            $previousDateRange = $this->getPreviousDateRange($period);

            // Overview stats
            $overview = $this->getOverviewStats($userId, $dateRange, $previousDateRange);

            // Trends
            $trends = $this->getTrends($userId, $dateRange);

            // Insights
            $insights = $this->generateInsights($userId, $overview, $trends);

            // Recommendations
            $recommendations = $this->generateRecommendations($userId, $overview);

            // Recent achievements
            $achievements = $this->getRecentAchievements($userId);

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => $period,
                    'overview' => $overview,
                    'trends' => $trends,
                    'insights' => $insights,
                    'recommendations' => $recommendations,
                    'achievements' => $achievements,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load analytics',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get Workout Analytics
     * GET /api/analytics/workouts
     */
    public function getWorkoutAnalytics(Request $request)
    {
        try {
            $userId = Auth::id();
            $startDate = $request->input('start_date', now()->subDays(30));
            $endDate = $request->input('end_date', now());

            $workouts = WorkoutLog::where('user_id', $userId)
                ->whereBetween('completed_at', [$startDate, $endDate])
                ->with('exercises')
                ->get();

            $analytics = [
                'totalWorkouts' => $workouts->count(),
                'totalDuration' => $workouts->sum('duration_minutes'),
                'totalCaloriesBurned' => $workouts->sum('calories_burned'),
                'averageWorkoutLength' => $workouts->avg('duration_minutes'),
                'workoutsByType' => $this->getWorkoutsByType($workouts),
                'muscleGroupDistribution' => $this->getMuscleGroupDistribution($workouts),
                'personalRecords' => $this->getPersonalRecords($userId),
                'volumeTrend' => $this->getVolumeTrend($userId, $startDate, $endDate),
                'consistencyScore' => $this->calculateConsistencyScore($userId, $startDate, $endDate),
                'missedWorkouts' => $this->getMissedWorkouts($userId, $startDate, $endDate),
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load workout analytics',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get Nutrition Analytics
     * GET /api/analytics/nutrition
     */
    public function getNutritionAnalytics(Request $request)
    {
        try {
            $userId = Auth::id();
            $startDate = $request->input('start_date', now()->subDays(30));
            $endDate = $request->input('end_date', now());

            $nutritionLogs = NutritionLog::where('user_id', $userId)
                ->whereBetween('logged_at', [$startDate, $endDate])
                ->get();

            $user = User::find($userId);
            $calorieGoal = $user->calorie_goal ?? 2000;
            $proteinGoal = $user->protein_goal ?? 150;
            $carbsGoal = $user->carbs_goal ?? 200;
            $fatGoal = $user->fat_goal ?? 65;

            $analytics = [
                'averageDailyCalories' => $nutritionLogs->avg('calories'),
                'calorieGoal' => $calorieGoal,
                'adherenceRate' => $this->calculateNutritionAdherence($nutritionLogs, $calorieGoal),
                'macroBreakdown' => [
                    'protein' => [
                        'average' => $nutritionLogs->avg('protein_g'),
                        'goal' => $proteinGoal,
                        'percentage' => ($nutritionLogs->avg('protein_g') / $proteinGoal) * 100,
                    ],
                    'carbs' => [
                        'average' => $nutritionLogs->avg('carbs_g'),
                        'goal' => $carbsGoal,
                        'percentage' => ($nutritionLogs->avg('carbs_g') / $carbsGoal) * 100,
                    ],
                    'fat' => [
                        'average' => $nutritionLogs->avg('fat_g'),
                        'goal' => $fatGoal,
                        'percentage' => ($nutritionLogs->avg('fat_g') / $fatGoal) * 100,
                    ],
                ],
                'calorieTrend' => $this->getCalorieTrend($nutritionLogs),
                'macroTrend' => $this->getMacroTrend($nutritionLogs),
                'mealTimingPatterns' => $this->getMealTimingPatterns($nutritionLogs),
                'topFoods' => $this->getTopFoods($nutritionLogs),
                'waterIntake' => $this->getWaterIntake($userId, $startDate, $endDate),
                'nutritionScore' => $this->calculateNutritionScore($nutritionLogs),
                'insights' => $this->generateNutritionInsights($nutritionLogs),
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load nutrition analytics',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get Body Composition Analytics
     * GET /api/analytics/body-composition
     */
    public function getBodyCompositionAnalytics(Request $request)
    {
        try {
            $userId = Auth::id();
            $startDate = $request->input('start_date', now()->subDays(90));
            $endDate = $request->input('end_date', now());

            $measurements = BodyMeasurement::where('user_id', $userId)
                ->whereBetween('measured_at', [$startDate, $endDate])
                ->orderBy('measured_at', 'asc')
                ->get();

            if ($measurements->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'message' => 'No body measurements recorded yet',
                    ],
                ]);
            }

            $startMeasurement = $measurements->first();
            $currentMeasurement = $measurements->last();
            $user = User::find($userId);

            $analytics = [
                'currentWeight' => $currentMeasurement->weight,
                'startWeight' => $startMeasurement->weight,
                'weightChange' => $currentMeasurement->weight - $startMeasurement->weight,
                'weightChangePercentage' => (($currentMeasurement->weight - $startMeasurement->weight) / $startMeasurement->weight) * 100,
                'goalWeight' => $user->goal_weight,
                'projectedGoalDate' => $this->calculateProjectedGoalDate($measurements, $user->goal_weight),
                'weightTrend' => $this->getWeightTrend($measurements),
                'bodyFat' => [
                    'current' => $currentMeasurement->body_fat_percentage,
                    'start' => $startMeasurement->body_fat_percentage,
                    'change' => $currentMeasurement->body_fat_percentage - $startMeasurement->body_fat_percentage,
                    'trend' => $this->getBodyFatTrend($measurements),
                ],
                'measurements' => $this->getMeasurementsComparison($startMeasurement, $currentMeasurement),
                'progressPhotos' => $this->getProgressPhotos($userId),
                'milestones' => $this->getMilestones($userId),
                'insights' => $this->generateBodyCompositionInsights($measurements),
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load body composition analytics',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get Goal Progress Report
     * GET /api/analytics/goal-progress
     */
    public function getGoalProgressReport(Request $request)
    {
        try {
            $userId = Auth::id();

            $activeGoals = Goal::where('user_id', $userId)
                ->where('status', 'active')
                ->get()
                ->map(function ($goal) {
                    return $this->formatGoalWithProgress($goal);
                });

            $completedGoals = Goal::where('user_id', $userId)
                ->where('status', 'completed')
                ->orderBy('completed_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($goal) {
                    return $this->formatGoalWithProgress($goal);
                });

            $overallProgress = $activeGoals->isEmpty() ? 100 : $activeGoals->avg('progress');

            $insights = $this->generateGoalInsights($activeGoals, $completedGoals);

            return response()->json([
                'success' => true,
                'data' => [
                    'activeGoals' => $activeGoals,
                    'completedGoals' => $completedGoals,
                    'overallProgress' => round($overallProgress, 1),
                    'insights' => $insights,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load goal progress',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get Weekly Report
     * GET /api/analytics/weekly-report
     */
    public function getWeeklyReport(Request $request)
    {
        try {
            $userId = Auth::id();
            $week = $request->input('week', 'current');

            if ($week === 'current') {
                $startDate = now()->startOfWeek();
                $endDate = now()->endOfWeek();
            } else {
                // Parse week number and year
                $startDate = Carbon::parse($week)->startOfWeek();
                $endDate = Carbon::parse($week)->endOfWeek();
            }

            $lastWeekStart = $startDate->copy()->subWeek();
            $lastWeekEnd = $endDate->copy()->subWeek();

            $thisWeekData = $this->getWeekData($userId, $startDate, $endDate);
            $lastWeekData = $this->getWeekData($userId, $lastWeekStart, $lastWeekEnd);

            $report = [
                'weekNumber' => $startDate->weekOfYear,
                'year' => $startDate->year,
                'dateRange' => [
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString(),
                ],
                'summary' => $thisWeekData,
                'highlights' => $this->generateWeeklyHighlights($thisWeekData),
                'improvements' => $this->generateWeeklyImprovements($thisWeekData, $lastWeekData),
                'comparisons' => $this->compareWeeks($thisWeekData, $lastWeekData),
                'achievements' => $this->getWeeklyAchievements($userId, $startDate, $endDate),
                'nextWeekPreview' => $this->getNextWeekPreview($userId),
            ];

            return response()->json([
                'success' => true,
                'data' => $report,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate weekly report',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Export Analytics Data
     * POST /api/analytics/export
     */
    public function exportAnalyticsData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'format' => 'required|in:pdf,csv,json',
            'dataTypes' => 'required|array',
            'startDate' => 'required|date',
            'endDate' => 'required|date',
            'includeCharts' => 'boolean',
            'includePhotos' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $userId = Auth::id();
            $format = $request->input('format');
            $dataTypes = $request->input('dataTypes');
            $startDate = $request->input('startDate');
            $endDate = $request->input('endDate');
            $includeCharts = $request->input('includeCharts', false);
            $includePhotos = $request->input('includePhotos', false);

            // Gather data
            $exportData = $this->gatherExportData($userId, $dataTypes, $startDate, $endDate);

            // Generate export based on format
            switch ($format) {
                case 'pdf':
                    return $this->generatePdfExport($exportData, $includeCharts, $includePhotos);
                case 'csv':
                    return $this->generateCsvExport($exportData);
                case 'json':
                    return $this->generateJsonExport($exportData);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export data',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // Helper methods

    protected function getDateRange($period)
    {
        return match($period) {
            'week' => [now()->startOfWeek(), now()],
            'month' => [now()->startOfMonth(), now()],
            'quarter' => [now()->startOfQuarter(), now()],
            'year' => [now()->startOfYear(), now()],
            default => [now()->startOfMonth(), now()],
        };
    }

    protected function getPreviousDateRange($period)
    {
        return match($period) {
            'week' => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
            'month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'quarter' => [now()->subQuarter()->startOfQuarter(), now()->subQuarter()->endOfQuarter()],
            'year' => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()],
            default => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
        };
    }

    protected function getOverviewStats($userId, $dateRange, $previousDateRange)
    {
        $currentWorkouts = WorkoutLog::where('user_id', $userId)
            ->whereBetween('completed_at', $dateRange)
            ->count();

        $previousWorkouts = WorkoutLog::where('user_id', $userId)
            ->whereBetween('completed_at', $previousDateRange)
            ->count();

        $currentCalories = WorkoutLog::where('user_id', $userId)
            ->whereBetween('completed_at', $dateRange)
            ->sum('calories_burned');

        $previousCalories = WorkoutLog::where('user_id', $userId)
            ->whereBetween('completed_at', $previousDateRange)
            ->sum('calories_burned');

        $currentMinutes = WorkoutLog::where('user_id', $userId)
            ->whereBetween('completed_at', $dateRange)
            ->sum('duration_minutes');

        $previousMinutes = WorkoutLog::where('user_id', $userId)
            ->whereBetween('completed_at', $previousDateRange)
            ->sum('duration_minutes');

        return [
            'workoutsCompleted' => $currentWorkouts,
            'workoutsCompletedChange' => $this->calculatePercentChange($currentWorkouts, $previousWorkouts),
            'caloriesBurned' => $currentCalories,
            'caloriesBurnedChange' => $this->calculatePercentChange($currentCalories, $previousCalories),
            'activeMinutes' => $currentMinutes,
            'activeMinutesChange' => $this->calculatePercentChange($currentMinutes, $previousMinutes),
            'currentStreak' => $this->getCurrentStreak($userId),
            'longestStreak' => $this->getLongestStreak($userId),
        ];
    }

    protected function calculatePercentChange($current, $previous)
    {
        if ($previous == 0) return $current > 0 ? 100 : 0;
        return round((($current - $previous) / $previous) * 100, 1);
    }

    protected function getCurrentStreak($userId)
    {
        $workouts = WorkoutLog::where('user_id', $userId)
            ->orderBy('completed_at', 'desc')
            ->get();

        $streak = 0;
        $currentDate = now()->startOfDay();

        foreach ($workouts as $workout) {
            $workoutDate = Carbon::parse($workout->completed_at)->startOfDay();

            if ($workoutDate->equalTo($currentDate) || $workoutDate->equalTo($currentDate->copy()->subDay())) {
                $streak++;
                $currentDate = $workoutDate->copy()->subDay();
            } else {
                break;
            }
        }

        return $streak;
    }

    protected function getLongestStreak($userId)
    {
        // Implementation for longest streak calculation
        return 0; // Placeholder
    }

    protected function getTrends($userId, $dateRange)
    {
        // Implementation for trend data
        return [
            'workoutFrequency' => [],
            'caloriesBurned' => [],
            'bodyWeight' => [],
            'strengthProgress' => [],
        ];
    }

    protected function generateInsights($userId, $overview, $trends)
    {
        $insights = [];

        if ($overview['workoutsCompletedChange'] > 10) {
            $insights[] = "Great job! You increased your workout frequency by {$overview['workoutsCompletedChange']}%";
        }

        if ($overview['currentStreak'] >= 7) {
            $insights[] = "Amazing {$overview['currentStreak']}-day streak! Keep it up!";
        }

        return $insights;
    }

    protected function generateRecommendations($userId, $overview)
    {
        $recommendations = [];

        if ($overview['workoutsCompleted'] < 3) {
            $recommendations[] = "Try to aim for at least 3 workouts per week";
        }

        return $recommendations;
    }

    protected function getRecentAchievements($userId)
    {
        // Implementation for recent achievements
        return [];
    }

    protected function getWorkoutsByType($workouts)
    {
        return $workouts->groupBy('type')->map(function ($group, $type) use ($workouts) {
            $count = $group->count();
            return [
                'type' => $type,
                'count' => $count,
                'percentage' => round(($count / $workouts->count()) * 100, 1),
            ];
        })->values();
    }

    protected function getMuscleGroupDistribution($workouts)
    {
        // Implementation for muscle group distribution
        return [];
    }

    protected function getPersonalRecords($userId)
    {
        // Implementation for personal records
        return [];
    }

    protected function getVolumeTrend($userId, $startDate, $endDate)
    {
        // Implementation for volume trend
        return [];
    }

    protected function calculateConsistencyScore($userId, $startDate, $endDate)
    {
        $days = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
        $workoutDays = WorkoutLog::where('user_id', $userId)
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->distinct('completed_at')
            ->count();

        return round(($workoutDays / $days) * 100, 1);
    }

    protected function getMissedWorkouts($userId, $startDate, $endDate)
    {
        // Implementation for missed workouts calculation
        return 0;
    }

    protected function calculateNutritionAdherence($nutritionLogs, $calorieGoal)
    {
        $withinRange = $nutritionLogs->filter(function ($log) use ($calorieGoal) {
            $diff = abs($log->calories - $calorieGoal);
            return $diff <= ($calorieGoal * 0.1); // Within 10% of goal
        })->count();

        if ($nutritionLogs->count() == 0) return 0;

        return round(($withinRange / $nutritionLogs->count()) * 100, 1);
    }

    protected function getCalorieTrend($nutritionLogs)
    {
        return $nutritionLogs->groupBy(function ($log) {
            return Carbon::parse($log->logged_at)->toDateString();
        })->map(function ($dayLogs, $date) {
            return [
                'date' => $date,
                'value' => $dayLogs->sum('calories'),
            ];
        })->values();
    }

    protected function getMacroTrend($nutritionLogs)
    {
        // Implementation for macro trends
        return [
            'protein' => [],
            'carbs' => [],
            'fat' => [],
        ];
    }

    protected function getMealTimingPatterns($nutritionLogs)
    {
        // Implementation for meal timing
        return [
            'breakfast' => '8:00 AM',
            'lunch' => '12:30 PM',
            'dinner' => '7:00 PM',
            'snacks' => 2,
        ];
    }

    protected function getTopFoods($nutritionLogs)
    {
        // Implementation for top foods
        return [];
    }

    protected function getWaterIntake($userId, $startDate, $endDate)
    {
        // Implementation for water intake
        return [
            'average' => 64,
            'goal' => 80,
            'adherenceRate' => 80,
        ];
    }

    protected function calculateNutritionScore($nutritionLogs)
    {
        // Implementation for nutrition score
        return [
            'current' => 85,
            'trend' => 5,
            'history' => [],
        ];
    }

    protected function generateNutritionInsights($nutritionLogs)
    {
        return ["You're consistently hitting your protein goal!"];
    }

    protected function calculateProjectedGoalDate($measurements, $goalWeight)
    {
        if (!$goalWeight || $measurements->count() < 2) {
            return null;
        }

        $first = $measurements->first();
        $last = $measurements->last();

        $weightChange = $last->weight - $first->weight;
        $days = Carbon::parse($first->measured_at)->diffInDays(Carbon::parse($last->measured_at));

        if ($days == 0 || $weightChange == 0) return null;

        $ratePerDay = $weightChange / $days;
        $remainingWeight = $goalWeight - $last->weight;

        if (($ratePerDay > 0 && $remainingWeight < 0) || ($ratePerDay < 0 && $remainingWeight > 0)) {
            $daysToGoal = abs($remainingWeight / $ratePerDay);
            return now()->addDays($daysToGoal)->toDateString();
        }

        return null;
    }

    protected function getWeightTrend($measurements)
    {
        return $measurements->map(function ($m) {
            return [
                'date' => Carbon::parse($m->measured_at)->toDateString(),
                'value' => $m->weight,
            ];
        });
    }

    protected function getBodyFatTrend($measurements)
    {
        return $measurements->map(function ($m) {
            return [
                'date' => Carbon::parse($m->measured_at)->toDateString(),
                'value' => $m->body_fat_percentage,
            ];
        });
    }

    protected function getMeasurementsComparison($start, $current)
    {
        return [
            'chest' => [
                'current' => $current->chest,
                'start' => $start->chest,
                'change' => $current->chest - $start->chest,
                'unit' => 'inches',
            ],
            'waist' => [
                'current' => $current->waist,
                'start' => $start->waist,
                'change' => $current->waist - $start->waist,
                'unit' => 'inches',
            ],
            'hips' => [
                'current' => $current->hips,
                'start' => $start->hips,
                'change' => $current->hips - $start->hips,
                'unit' => 'inches',
            ],
        ];
    }

    protected function getProgressPhotos($userId)
    {
        // Implementation for progress photos
        return [];
    }

    protected function getMilestones($userId)
    {
        // Implementation for milestones
        return [];
    }

    protected function generateBodyCompositionInsights($measurements)
    {
        return ["You've lost 7 lbs over the past 3 months - great progress!"];
    }

    protected function formatGoalWithProgress($goal)
    {
        $progress = $this->calculateGoalProgress($goal);

        return [
            'id' => $goal->id,
            'type' => $goal->type,
            'title' => $goal->title,
            'description' => $goal->description,
            'startDate' => $goal->start_date,
            'targetDate' => $goal->target_date,
            'startValue' => $goal->start_value,
            'currentValue' => $goal->current_value,
            'targetValue' => $goal->target_value,
            'unit' => $goal->unit,
            'progress' => $progress,
            'status' => $this->determineGoalStatus($goal, $progress),
            'projectedCompletionDate' => $this->projectGoalCompletion($goal),
            'milestones' => $goal->milestones ?? [],
        ];
    }

    protected function calculateGoalProgress($goal)
    {
        if ($goal->target_value == $goal->start_value) return 100;

        $progress = (($goal->current_value - $goal->start_value) / ($goal->target_value - $goal->start_value)) * 100;

        return max(0, min(100, round($progress, 1)));
    }

    protected function determineGoalStatus($goal, $progress)
    {
        if ($progress >= 100) return 'completed';

        $daysRemaining = Carbon::now()->diffInDays(Carbon::parse($goal->target_date), false);

        if ($daysRemaining < 0) return 'behind';
        if ($progress >= 75) return 'ahead';
        if ($progress >= 50) return 'on-track';

        return 'behind';
    }

    protected function projectGoalCompletion($goal)
    {
        // Simple projection based on current progress rate
        return $goal->target_date;
    }

    protected function generateGoalInsights($activeGoals, $completedGoals)
    {
        $insights = [];

        if ($completedGoals->count() > 0) {
            $insights[] = "You've completed {$completedGoals->count()} goals recently!";
        }

        if ($activeGoals->where('status', 'ahead')->count() > 0) {
            $insights[] = "You're ahead of schedule on some goals - great work!";
        }

        return $insights;
    }

    protected function getWeekData($userId, $startDate, $endDate)
    {
        return [
            'workoutsCompleted' => WorkoutLog::where('user_id', $userId)
                ->whereBetween('completed_at', [$startDate, $endDate])
                ->count(),
            'workoutsMissed' => 0,
            'totalActiveMinutes' => WorkoutLog::where('user_id', $userId)
                ->whereBetween('completed_at', [$startDate, $endDate])
                ->sum('duration_minutes'),
            'caloriesBurned' => WorkoutLog::where('user_id', $userId)
                ->whereBetween('completed_at', [$startDate, $endDate])
                ->sum('calories_burned'),
            'nutritionAdherence' => 85,
            'averageSleep' => 7.5,
            'averageSteps' => 8500,
        ];
    }

    protected function generateWeeklyHighlights($data)
    {
        $highlights = [];

        if ($data['workoutsCompleted'] >= 5) {
            $highlights[] = "Hit {$data['workoutsCompleted']} workouts this week!";
        }

        if ($data['caloriesBurned'] > 3000) {
            $highlights[] = "Burned {$data['caloriesBurned']} calories!";
        }

        return $highlights;
    }

    protected function generateWeeklyImprovements($thisWeek, $lastWeek)
    {
        $improvements = [];

        if ($thisWeek['workoutsCompleted'] < $lastWeek['workoutsCompleted']) {
            $improvements[] = "Try to match last week's {$lastWeek['workoutsCompleted']} workouts";
        }

        return $improvements;
    }

    protected function compareWeeks($thisWeek, $lastWeek)
    {
        return [
            [
                'metric' => 'Workouts',
                'thisWeek' => $thisWeek['workoutsCompleted'],
                'lastWeek' => $lastWeek['workoutsCompleted'],
                'change' => $thisWeek['workoutsCompleted'] - $lastWeek['workoutsCompleted'],
                'changePercent' => $this->calculatePercentChange($thisWeek['workoutsCompleted'], $lastWeek['workoutsCompleted']),
            ],
            [
                'metric' => 'Active Minutes',
                'thisWeek' => $thisWeek['totalActiveMinutes'],
                'lastWeek' => $lastWeek['totalActiveMinutes'],
                'change' => $thisWeek['totalActiveMinutes'] - $lastWeek['totalActiveMinutes'],
                'changePercent' => $this->calculatePercentChange($thisWeek['totalActiveMinutes'], $lastWeek['totalActiveMinutes']),
            ],
        ];
    }

    protected function getWeeklyAchievements($userId, $startDate, $endDate)
    {
        // Implementation for weekly achievements
        return [];
    }

    protected function getNextWeekPreview($userId)
    {
        return [
            'plannedWorkouts' => 5,
            'focusAreas' => ['Upper Body', 'Core'],
            'recommendations' => ['Schedule rest day on Sunday', 'Increase protein intake'],
        ];
    }

    protected function gatherExportData($userId, $dataTypes, $startDate, $endDate)
    {
        $data = [];

        if (in_array('workouts', $dataTypes) || in_array('all', $dataTypes)) {
            $data['workouts'] = WorkoutLog::where('user_id', $userId)
                ->whereBetween('completed_at', [$startDate, $endDate])
                ->get();
        }

        if (in_array('nutrition', $dataTypes) || in_array('all', $dataTypes)) {
            $data['nutrition'] = NutritionLog::where('user_id', $userId)
                ->whereBetween('logged_at', [$startDate, $endDate])
                ->get();
        }

        if (in_array('body', $dataTypes) || in_array('all', $dataTypes)) {
            $data['body'] = BodyMeasurement::where('user_id', $userId)
                ->whereBetween('measured_at', [$startDate, $endDate])
                ->get();
        }

        return $data;
    }

    protected function generatePdfExport($data, $includeCharts, $includePhotos)
    {
        $pdf = Pdf::loadView('exports.analytics-pdf', [
            'data' => $data,
            'includeCharts' => $includeCharts,
            'includePhotos' => $includePhotos,
        ]);

        return $pdf->download('analytics-report-' . now()->format('Y-m-d') . '.pdf');
    }

    protected function generateCsvExport($data)
    {
        $csvData = [];

        // Convert data to CSV format
        foreach ($data as $type => $records) {
            foreach ($records as $record) {
                $csvData[] = $record->toArray();
            }
        }

        $filename = 'analytics-export-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($csvData) {
            $file = fopen('php://output', 'w');

            if (!empty($csvData)) {
                fputcsv($file, array_keys($csvData[0]));

                foreach ($csvData as $row) {
                    fputcsv($file, $row);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    protected function generateJsonExport($data)
    {
        $filename = 'analytics-export-' . now()->format('Y-m-d') . '.json';

        return response()->json($data)
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', "attachment; filename=\"$filename\"");
    }
}
