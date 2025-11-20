<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\NutritionLog;
use Carbon\Carbon;

class AnalyticsReportingController extends Controller
{
    /**
     * Get comprehensive dashboard analytics
     */
    public function getDashboardAnalytics(Request $request)
    {
        try {
            $validated = $request->validate([
                'period' => 'nullable|string|in:week,month,quarter,year',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date'
            ]);

            $userId = Auth::id();
            $period = $validated['period'] ?? 'month';

            // Determine date range
            if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
                $startDate = Carbon::parse($validated['start_date']);
                $endDate = Carbon::parse($validated['end_date']);
            } else {
                $endDate = Carbon::now();
                switch ($period) {
                    case 'week':
                        $startDate = $endDate->copy()->subWeek();
                        break;
                    case 'quarter':
                        $startDate = $endDate->copy()->subQuarter();
                        break;
                    case 'year':
                        $startDate = $endDate->copy()->subYear();
                        break;
                    default: // month
                        $startDate = $endDate->copy()->subMonth();
                }
            }

            $analytics = [
                'period' => [
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString(),
                    'label' => $period
                ],
                'fitness' => $this->getFitnessAnalytics($userId, $startDate, $endDate),
                'nutrition' => $this->getNutritionAnalytics($userId, $startDate, $endDate),
                'progress' => $this->getProgressAnalytics($userId, $startDate, $endDate),
                'achievements' => $this->getAchievementAnalytics($userId, $startDate, $endDate),
                'social' => $this->getSocialAnalytics($userId, $startDate, $endDate),
                'trends' => $this->getTrendAnalytics($userId, $startDate, $endDate)
            ];

            return response()->json([
                'success' => true,
                'analytics' => $analytics
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching dashboard analytics', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get workout analytics
     */
    public function getWorkoutAnalytics(Request $request)
    {
        try {
            $validated = $request->validate([
                'period' => 'nullable|string|in:week,month,quarter,year',
                'workout_type' => 'nullable|string',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date'
            ]);

            $userId = Auth::id();
            $period = $validated['period'] ?? 'month';

            // Determine date range
            $endDate = Carbon::now();
            switch ($period) {
                case 'week':
                    $startDate = $endDate->copy()->subWeek();
                    break;
                case 'quarter':
                    $startDate = $endDate->copy()->subQuarter();
                    break;
                case 'year':
                    $startDate = $endDate->copy()->subYear();
                    break;
                default:
                    $startDate = $endDate->copy()->subMonth();
            }

            // Get workout data
            $query = WorkoutSession::where('user_id', $userId)
                ->whereBetween('created_at', [$startDate, $endDate]);

            if (!empty($validated['workout_type'])) {
                $query->whereHas('workout', function($q) use ($validated) {
                    $q->where('type', $validated['workout_type']);
                });
            }

            $sessions = $query->get();

            // Calculate statistics
            $stats = [
                'total_workouts' => $sessions->count(),
                'completed_workouts' => $sessions->where('status', 'completed')->count(),
                'total_duration_minutes' => $sessions->sum('duration_minutes'),
                'total_calories_burned' => $sessions->sum('calories_burned'),
                'average_duration' => $sessions->avg('duration_minutes'),
                'average_calories' => $sessions->avg('calories_burned'),
                'completion_rate' => $sessions->count() > 0
                    ? round(($sessions->where('status', 'completed')->count() / $sessions->count()) * 100, 2)
                    : 0,
                'current_streak' => $this->calculateWorkoutStreak($userId),
                'longest_streak' => $this->calculateLongestStreak($userId),
                'favorite_workout_type' => $this->getFavoriteWorkoutType($userId, $startDate, $endDate)
            ];

            // Get daily breakdown
            $dailyBreakdown = $this->getDailyWorkoutBreakdown($userId, $startDate, $endDate);

            // Get workout type distribution
            $typeDistribution = $this->getWorkoutTypeDistribution($userId, $startDate, $endDate);

            // Get personal records
            $personalRecords = $this->getPersonalRecords($userId);

            return response()->json([
                'success' => true,
                'period' => [
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString()
                ],
                'stats' => $stats,
                'daily_breakdown' => $dailyBreakdown,
                'type_distribution' => $typeDistribution,
                'personal_records' => $personalRecords
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching workout analytics', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch workout analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get nutrition analytics
     */
    public function getNutritionAnalyticsReport(Request $request)
    {
        try {
            $validated = $request->validate([
                'period' => 'nullable|string|in:week,month,quarter,year',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date'
            ]);

            $userId = Auth::id();
            $period = $validated['period'] ?? 'month';

            // Determine date range
            $endDate = Carbon::now();
            switch ($period) {
                case 'week':
                    $startDate = $endDate->copy()->subWeek();
                    break;
                case 'quarter':
                    $startDate = $endDate->copy()->subQuarter();
                    break;
                case 'year':
                    $startDate = $endDate->copy()->subYear();
                    break;
                default:
                    $startDate = $endDate->copy()->subMonth();
            }

            // Get nutrition logs
            $logs = NutritionLog::where('user_id', $userId)
                ->whereBetween('date', [$startDate, $endDate])
                ->get();

            // Calculate statistics
            $stats = [
                'days_logged' => $logs->count(),
                'average_calories' => round($logs->avg('total_calories'), 2),
                'total_calories' => $logs->sum('total_calories'),
                'average_protein' => round($logs->avg('macros.protein'), 2),
                'average_carbs' => round($logs->avg('macros.carbs'), 2),
                'average_fat' => round($logs->avg('macros.fat'), 2),
                'logging_streak' => $this->calculateNutritionLoggingStreak($userId),
                'goal_adherence' => $this->calculateGoalAdherence($userId, $startDate, $endDate)
            ];

            // Get daily breakdown
            $dailyBreakdown = $logs->map(function($log) {
                return [
                    'date' => $log->date,
                    'calories' => $log->total_calories,
                    'protein' => $log->macros['protein'] ?? 0,
                    'carbs' => $log->macros['carbs'] ?? 0,
                    'fat' => $log->macros['fat'] ?? 0,
                    'water' => $log->water_intake ?? 0
                ];
            });

            // Get macro distribution
            $macroDistribution = [
                'protein' => round($logs->avg('macros.protein'), 2),
                'carbs' => round($logs->avg('macros.carbs'), 2),
                'fat' => round($logs->avg('macros.fat'), 2)
            ];

            // Get insights
            $insights = $this->getNutritionInsights($userId, $logs);

            return response()->json([
                'success' => true,
                'period' => [
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString()
                ],
                'stats' => $stats,
                'daily_breakdown' => $dailyBreakdown,
                'macro_distribution' => $macroDistribution,
                'insights' => $insights
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching nutrition analytics', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch nutrition analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get body composition analytics
     */
    public function getBodyCompositionAnalytics(Request $request)
    {
        try {
            $validated = $request->validate([
                'period' => 'nullable|string|in:month,quarter,year,all',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date'
            ]);

            $userId = Auth::id();
            $period = $validated['period'] ?? 'year';

            // Determine date range
            $endDate = Carbon::now();
            switch ($period) {
                case 'month':
                    $startDate = $endDate->copy()->subMonth();
                    break;
                case 'quarter':
                    $startDate = $endDate->copy()->subQuarter();
                    break;
                case 'year':
                    $startDate = $endDate->copy()->subYear();
                    break;
                default:
                    $startDate = null; // All time
            }

            // Get measurement history
            $query = DB::table('body_measurements')
                ->where('user_id', $userId);

            if ($startDate) {
                $query->where('measured_at', '>=', $startDate);
            }

            $measurements = $query->orderBy('measured_at', 'asc')->get();

            // Calculate changes
            $firstMeasurement = $measurements->first();
            $lastMeasurement = $measurements->last();

            $changes = [];
            if ($firstMeasurement && $lastMeasurement) {
                $changes = [
                    'weight' => [
                        'start' => $firstMeasurement->weight ?? 0,
                        'current' => $lastMeasurement->weight ?? 0,
                        'change' => round(($lastMeasurement->weight ?? 0) - ($firstMeasurement->weight ?? 0), 2),
                        'percentage' => $firstMeasurement->weight > 0
                            ? round(((($lastMeasurement->weight ?? 0) - ($firstMeasurement->weight ?? 0)) / $firstMeasurement->weight) * 100, 2)
                            : 0
                    ],
                    'body_fat' => [
                        'start' => $firstMeasurement->body_fat_percentage ?? 0,
                        'current' => $lastMeasurement->body_fat_percentage ?? 0,
                        'change' => round(($lastMeasurement->body_fat_percentage ?? 0) - ($firstMeasurement->body_fat_percentage ?? 0), 2)
                    ],
                    'muscle_mass' => [
                        'start' => $firstMeasurement->muscle_mass ?? 0,
                        'current' => $lastMeasurement->muscle_mass ?? 0,
                        'change' => round(($lastMeasurement->muscle_mass ?? 0) - ($firstMeasurement->muscle_mass ?? 0), 2)
                    ]
                ];
            }

            // Format measurement history for charting
            $history = $measurements->map(function($m) {
                return [
                    'date' => $m->measured_at,
                    'weight' => $m->weight ?? null,
                    'body_fat' => $m->body_fat_percentage ?? null,
                    'muscle_mass' => $m->muscle_mass ?? null,
                    'waist' => $m->waist ?? null,
                    'chest' => $m->chest ?? null,
                    'arms' => $m->arms ?? null,
                    'thighs' => $m->thighs ?? null
                ];
            });

            return response()->json([
                'success' => true,
                'period' => [
                    'start' => $startDate ? $startDate->toDateString() : 'all_time',
                    'end' => $endDate->toDateString()
                ],
                'changes' => $changes,
                'history' => $history,
                'total_measurements' => $measurements->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching body composition analytics', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch body composition analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get goal progress report
     */
    public function getGoalProgressReport(Request $request)
    {
        try {
            $userId = Auth::id();

            // Get active goals
            $goals = DB::table('user_goals')
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->get();

            $goalsProgress = [];
            foreach ($goals as $goal) {
                $progress = $this->calculateGoalProgress($userId, $goal);

                $goalsProgress[] = [
                    'goal' => [
                        'id' => $goal->id,
                        'type' => $goal->goal_type,
                        'target' => $goal->target_value,
                        'unit' => $goal->unit,
                        'deadline' => $goal->target_date,
                        'created_at' => $goal->created_at
                    ],
                    'progress' => [
                        'current_value' => $progress['current'],
                        'percentage_complete' => $progress['percentage'],
                        'remaining' => $progress['remaining'],
                        'on_track' => $progress['on_track'],
                        'days_remaining' => Carbon::parse($goal->target_date)->diffInDays(Carbon::now()),
                        'estimated_completion' => $progress['estimated_completion']
                    ],
                    'milestones' => $progress['milestones']
                ];
            }

            // Get completed goals
            $completedGoals = DB::table('user_goals')
                ->where('user_id', $userId)
                ->where('status', 'completed')
                ->orderBy('completed_at', 'desc')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'active_goals' => $goalsProgress,
                'completed_goals' => $completedGoals,
                'summary' => [
                    'total_goals' => DB::table('user_goals')->where('user_id', $userId)->count(),
                    'active_count' => count($goalsProgress),
                    'completed_count' => $completedGoals->count(),
                    'success_rate' => $this->calculateGoalSuccessRate($userId)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching goal progress', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch goal progress',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get comprehensive weekly report
     */
    public function getWeeklyReport(Request $request)
    {
        try {
            $validated = $request->validate([
                'week_offset' => 'nullable|integer|min:0|max:52'
            ]);

            $userId = Auth::id();
            $weekOffset = $validated['week_offset'] ?? 0;

            $endDate = Carbon::now()->subWeeks($weekOffset);
            $startDate = $endDate->copy()->startOfWeek();
            $endDate = $endDate->copy()->endOfWeek();

            $report = [
                'week' => [
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString(),
                    'label' => "Week of " . $startDate->format('M d, Y')
                ],
                'fitness' => [
                    'workouts_completed' => WorkoutSession::where('user_id', $userId)
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->where('status', 'completed')
                        ->count(),
                    'total_duration' => WorkoutSession::where('user_id', $userId)
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->sum('duration_minutes'),
                    'calories_burned' => WorkoutSession::where('user_id', $userId)
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->sum('calories_burned')
                ],
                'nutrition' => [
                    'days_logged' => NutritionLog::where('user_id', $userId)
                        ->whereBetween('date', [$startDate, $endDate])
                        ->count(),
                    'average_calories' => NutritionLog::where('user_id', $userId)
                        ->whereBetween('date', [$startDate, $endDate])
                        ->avg('total_calories')
                ],
                'highlights' => $this->getWeekHighlights($userId, $startDate, $endDate),
                'recommendations' => $this->getWeeklyRecommendations($userId, $startDate, $endDate)
            ];

            return response()->json([
                'success' => true,
                'report' => $report
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating weekly report', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate weekly report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export data
     */
    public function exportData(Request $request)
    {
        try {
            $validated = $request->validate([
                'data_type' => 'required|string|in:workouts,nutrition,measurements,all',
                'format' => 'required|string|in:json,csv,pdf',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date'
            ]);

            $userId = Auth::id();
            $startDate = $validated['start_date'] ?? Carbon::now()->subYear();
            $endDate = $validated['end_date'] ?? Carbon::now();

            $data = [];

            // Collect requested data
            if ($validated['data_type'] === 'workouts' || $validated['data_type'] === 'all') {
                $data['workouts'] = WorkoutSession::where('user_id', $userId)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->with(['workout', 'exerciseSets'])
                    ->get();
            }

            if ($validated['data_type'] === 'nutrition' || $validated['data_type'] === 'all') {
                $data['nutrition'] = NutritionLog::where('user_id', $userId)
                    ->whereBetween('date', [$startDate, $endDate])
                    ->get();
            }

            if ($validated['data_type'] === 'measurements' || $validated['data_type'] === 'all') {
                $data['measurements'] = DB::table('body_measurements')
                    ->where('user_id', $userId)
                    ->whereBetween('measured_at', [$startDate, $endDate])
                    ->get();
            }

            // Format based on requested format
            switch ($validated['format']) {
                case 'csv':
                    $export = $this->formatAsCSV($data);
                    return response($export)
                        ->header('Content-Type', 'text/csv')
                        ->header('Content-Disposition', 'attachment; filename=bodyf1rst-export.csv');

                case 'pdf':
                    $export = $this->formatAsPDF($data);
                    return response($export)
                        ->header('Content-Type', 'application/pdf')
                        ->header('Content-Disposition', 'attachment; filename=bodyf1rst-export.pdf');

                default: // json
                    return response()->json([
                        'success' => true,
                        'data' => $data,
                        'export_date' => now(),
                        'period' => [
                            'start' => $startDate,
                            'end' => $endDate
                        ]
                    ]);
            }

        } catch (\Exception $e) {
            Log::error('Error exporting data', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to export data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Private helper methods
     */
    private function getFitnessAnalytics($userId, $startDate, $endDate)
    {
        $sessions = WorkoutSession::where('user_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        return [
            'total_workouts' => $sessions->count(),
            'completed_workouts' => $sessions->where('status', 'completed')->count(),
            'total_duration' => $sessions->sum('duration_minutes'),
            'total_calories' => $sessions->sum('calories_burned'),
            'average_per_week' => round($sessions->count() / max(1, $startDate->diffInWeeks($endDate)), 2)
        ];
    }

    private function getNutritionAnalytics($userId, $startDate, $endDate)
    {
        $logs = NutritionLog::where('user_id', $userId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        return [
            'days_logged' => $logs->count(),
            'average_calories' => round($logs->avg('total_calories'), 2),
            'logging_consistency' => round(($logs->count() / max(1, $startDate->diffInDays($endDate))) * 100, 2)
        ];
    }

    private function getProgressAnalytics($userId, $startDate, $endDate)
    {
        // Get weight change
        $firstWeight = DB::table('body_measurements')
            ->where('user_id', $userId)
            ->where('measured_at', '>=', $startDate)
            ->orderBy('measured_at', 'asc')
            ->value('weight');

        $lastWeight = DB::table('body_measurements')
            ->where('user_id', $userId)
            ->where('measured_at', '<=', $endDate)
            ->orderBy('measured_at', 'desc')
            ->value('weight');

        return [
            'weight_change' => $firstWeight && $lastWeight ? round($lastWeight - $firstWeight, 2) : 0,
            'measurements_taken' => DB::table('body_measurements')
                ->where('user_id', $userId)
                ->whereBetween('measured_at', [$startDate, $endDate])
                ->count()
        ];
    }

    private function getAchievementAnalytics($userId, $startDate, $endDate)
    {
        return [
            'achievements_earned' => DB::table('achievements')
                ->where('user_id', $userId)
                ->whereBetween('achieved_at', [$startDate, $endDate])
                ->count(),
            'body_points_earned' => DB::table('point_logs')
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('points')
        ];
    }

    private function getSocialAnalytics($userId, $startDate, $endDate)
    {
        return [
            'posts_created' => DB::table('posts')
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'comments_made' => DB::table('comments')
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'likes_received' => DB::table('likes')
                ->whereIn('likeable_id', function($query) use ($userId) {
                    $query->select('id')
                        ->from('posts')
                        ->where('user_id', $userId);
                })
                ->where('likeable_type', 'App\Models\Post')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count()
        ];
    }

    private function getTrendAnalytics($userId, $startDate, $endDate)
    {
        // Calculate various trends
        return [
            'workout_trend' => 'increasing', // Implement actual calculation
            'nutrition_trend' => 'stable',
            'weight_trend' => 'decreasing'
        ];
    }

    private function calculateWorkoutStreak($userId)
    {
        $sessions = WorkoutSession::where('user_id', $userId)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->pluck('created_at');

        $streak = 0;
        $lastDate = null;

        foreach ($sessions as $date) {
            $date = Carbon::parse($date)->startOfDay();

            if ($lastDate === null) {
                $streak = 1;
                $lastDate = $date;
            } elseif ($lastDate->diffInDays($date) === 1) {
                $streak++;
                $lastDate = $date;
            } else {
                break;
            }
        }

        return $streak;
    }

    private function calculateLongestStreak($userId)
    {
        // Implement longest streak calculation
        return 0;
    }

    private function getFavoriteWorkoutType($userId, $startDate, $endDate)
    {
        return WorkoutSession::where('user_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('workout_id', DB::raw('COUNT(*) as count'))
            ->groupBy('workout_id')
            ->orderBy('count', 'desc')
            ->first()
            ->workout
            ->type ?? 'N/A';
    }

    private function getDailyWorkoutBreakdown($userId, $startDate, $endDate)
    {
        return WorkoutSession::where('user_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(duration_minutes) as duration, SUM(calories_burned) as calories')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();
    }

    private function getWorkoutTypeDistribution($userId, $startDate, $endDate)
    {
        // Implement workout type distribution
        return [];
    }

    private function getPersonalRecords($userId)
    {
        // Implement personal records tracking
        return [];
    }

    private function calculateNutritionLoggingStreak($userId)
    {
        // Implement nutrition logging streak
        return 0;
    }

    private function calculateGoalAdherence($userId, $startDate, $endDate)
    {
        // Implement goal adherence calculation
        return 0;
    }

    private function getNutritionInsights($userId, $logs)
    {
        // Generate insights based on nutrition data
        return [];
    }

    private function calculateGoalProgress($userId, $goal)
    {
        // Implement goal progress calculation
        return [
            'current' => 0,
            'percentage' => 0,
            'remaining' => $goal->target_value,
            'on_track' => true,
            'estimated_completion' => null,
            'milestones' => []
        ];
    }

    private function calculateGoalSuccessRate($userId)
    {
        $total = DB::table('user_goals')
            ->where('user_id', $userId)
            ->whereIn('status', ['completed', 'failed'])
            ->count();

        if ($total == 0) return 0;

        $completed = DB::table('user_goals')
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->count();

        return round(($completed / $total) * 100, 2);
    }

    private function getWeekHighlights($userId, $startDate, $endDate)
    {
        return [];
    }

    private function getWeeklyRecommendations($userId, $startDate, $endDate)
    {
        return [];
    }

    private function formatAsCSV($data)
    {
        // Implement CSV formatting
        return '';
    }

    private function formatAsPDF($data)
    {
        // Implement PDF formatting
        return '';
    }
}