<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkoutLog;
use App\Models\NutritionLog;
use App\Models\Subscription;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Admin Analytics Controller
 * Comprehensive analytics dashboard for user growth, revenue, engagement, and system performance
 */
class AnalyticsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:admin', 'role']);
    }

    /**
     * Get Dashboard Summary
     * GET /api/admin/analytics/dashboard-summary
     *
     * Returns: total_users, active_users, new_users_today, total_revenue,
     *          revenue_today, avg_session_duration, total_workouts, completion_rate
     */
    public function getDashboardSummary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start' => 'date',
            'end' => 'date|after_or_equal:start',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $startDate = $request->has('start') ? Carbon::parse($request->start) : Carbon::now()->subDays(30);
            $endDate = $request->has('end') ? Carbon::parse($request->end) : Carbon::now();

            // User metrics
            $totalUsers = User::where('role', '!=', 'admin')->count();
            $activeUsers = User::where('role', '!=', 'admin')
                ->where('last_login_at', '>=', $startDate)
                ->count();
            $newUsersToday = User::where('role', '!=', 'admin')
                ->whereDate('created_at', Carbon::today())
                ->count();

            // Revenue metrics
            $totalRevenue = Payment::where('status', 'completed')->sum('amount') ?? 0;
            $revenueToday = Payment::where('status', 'completed')
                ->whereDate('created_at', Carbon::today())
                ->sum('amount') ?? 0;

            // Workout metrics
            $totalWorkouts = WorkoutLog::whereBetween('completed_at', [$startDate, $endDate])->count();
            $plannedWorkouts = WorkoutLog::whereBetween('created_at', [$startDate, $endDate])->count();
            $completionRate = $plannedWorkouts > 0 ? round(($totalWorkouts / $plannedWorkouts) * 100, 1) : 0;

            // Session duration (avg minutes per user session)
            $avgSessionDuration = $this->calculateAverageSessionDuration($startDate, $endDate);

            // Calculate trends (compare to previous period)
            $previousPeriodDays = $startDate->diffInDays($endDate);
            $previousStart = $startDate->copy()->subDays($previousPeriodDays);
            $previousEnd = $startDate->copy()->subDay();

            $previousActiveUsers = User::where('role', '!=', 'admin')
                ->where('last_login_at', '>=', $previousStart)
                ->where('last_login_at', '<=', $previousEnd)
                ->count();

            $previousRevenue = Payment::where('status', 'completed')
                ->whereBetween('created_at', [$previousStart, $previousEnd])
                ->sum('amount') ?? 0;

            $previousWorkouts = WorkoutLog::whereBetween('completed_at', [$previousStart, $previousEnd])->count();

            $summary = [
                'total_users' => $totalUsers,
                'total_users_trend' => $this->calculateTrend($totalUsers, User::where('role', '!=', 'admin')
                    ->whereBetween('created_at', [$previousStart, $previousEnd])->count()),

                'active_users' => $activeUsers,
                'active_users_trend' => $this->calculateTrend($activeUsers, $previousActiveUsers),

                'new_users_today' => $newUsersToday,

                'total_revenue' => round($totalRevenue, 2),
                'total_revenue_trend' => $this->calculateTrend($totalRevenue, $previousRevenue),

                'revenue_today' => round($revenueToday, 2),

                'avg_session_duration' => $avgSessionDuration,

                'total_workouts' => $totalWorkouts,
                'total_workouts_trend' => $this->calculateTrend($totalWorkouts, $previousWorkouts),

                'completion_rate' => $completionRate,
                'completion_rate_trend' => 0, // Placeholder for completion rate trend
            ];

            return response()->json($summary);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard summary',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get User Growth
     * GET /api/admin/analytics/user-growth
     *
     * Returns: Daily/weekly user growth data with total_users and active_users per period
     */
    public function getUserGrowth(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start' => 'date',
            'end' => 'date|after_or_equal:start',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $startDate = $request->has('start') ? Carbon::parse($request->start) : Carbon::now()->subDays(30);
            $endDate = $request->has('end') ? Carbon::parse($request->end) : Carbon::now();

            $growthData = [];
            $currentDate = $startDate->copy();

            while ($currentDate->lte($endDate)) {
                $totalUsers = User::where('role', '!=', 'admin')
                    ->where('created_at', '<=', $currentDate)
                    ->count();

                $activeUsers = User::where('role', '!=', 'admin')
                    ->where('last_login_at', '>=', $currentDate->copy()->subDays(7))
                    ->where('last_login_at', '<=', $currentDate)
                    ->count();

                $growthData[] = [
                    'date' => $currentDate->format('Y-m-d'),
                    'total_users' => $totalUsers,
                    'active_users' => $activeUsers,
                    'new_users' => User::where('role', '!=', 'admin')
                        ->whereDate('created_at', $currentDate)
                        ->count(),
                ];

                $currentDate->addDay();
            }

            return response()->json($growthData);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load user growth',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get User Demographics
     * GET /api/admin/analytics/user-demographics
     *
     * Returns: age_groups, gender, locations distribution
     */
    public function getUserDemographics(Request $request)
    {
        try {
            // Age groups
            $ageGroups = [
                ['range' => '18-24', 'percentage' => 15, 'count' => 0],
                ['range' => '25-34', 'percentage' => 35, 'count' => 0],
                ['range' => '35-44', 'percentage' => 28, 'count' => 0],
                ['range' => '45-54', 'percentage' => 15, 'count' => 0],
                ['range' => '55+', 'percentage' => 7, 'count' => 0],
            ];

            $totalUsers = User::where('role', '!=', 'admin')->count();

            if ($totalUsers > 0) {
                // Calculate age groups from birth_date if available
                $users = User::where('role', '!=', 'admin')
                    ->whereNotNull('birth_date')
                    ->get();

                $ageCounts = [
                    '18-24' => 0,
                    '25-34' => 0,
                    '35-44' => 0,
                    '45-54' => 0,
                    '55+' => 0,
                ];

                foreach ($users as $user) {
                    $age = Carbon::parse($user->birth_date)->age;
                    if ($age >= 18 && $age <= 24) $ageCounts['18-24']++;
                    elseif ($age >= 25 && $age <= 34) $ageCounts['25-34']++;
                    elseif ($age >= 35 && $age <= 44) $ageCounts['35-44']++;
                    elseif ($age >= 45 && $age <= 54) $ageCounts['45-54']++;
                    elseif ($age >= 55) $ageCounts['55+']++;
                }

                foreach ($ageGroups as $key => $group) {
                    $count = $ageCounts[$group['range']];
                    $ageGroups[$key]['count'] = $count;
                    $ageGroups[$key]['percentage'] = $totalUsers > 0 ? round(($count / $totalUsers) * 100, 1) : 0;
                }
            }

            // Gender distribution
            $genderData = User::where('role', '!=', 'admin')
                ->select('gender', DB::raw('count(*) as count'))
                ->groupBy('gender')
                ->get();

            $gender = [];
            foreach ($genderData as $item) {
                $gender[] = [
                    'type' => $item->gender ?? 'Not specified',
                    'count' => $item->count,
                    'percentage' => $totalUsers > 0 ? round(($item->count / $totalUsers) * 100, 1) : 0,
                ];
            }

            // Location distribution (top 10)
            $locationData = User::where('role', '!=', 'admin')
                ->whereNotNull('country')
                ->select('country', DB::raw('count(*) as count'))
                ->groupBy('country')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get();

            $locations = [];
            foreach ($locationData as $item) {
                $locations[] = [
                    'country' => $item->country,
                    'count' => $item->count,
                    'percentage' => $totalUsers > 0 ? round(($item->count / $totalUsers) * 100, 1) : 0,
                ];
            }

            return response()->json([
                'age_groups' => $ageGroups,
                'gender' => $gender,
                'locations' => $locations,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load user demographics',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get User Retention
     * GET /api/admin/analytics/user-retention
     *
     * Returns: Cohort-based retention data
     */
    public function getUserRetention(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start' => 'date',
            'end' => 'date|after_or_equal:start',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $startDate = $request->has('start') ? Carbon::parse($request->start) : Carbon::now()->subDays(90);
            $endDate = $request->has('end') ? Carbon::parse($request->end) : Carbon::now();

            // Calculate retention by cohort (month of signup)
            $cohorts = [];
            $currentMonth = $startDate->copy()->startOfMonth();

            while ($currentMonth->lte($endDate)) {
                $cohortUsers = User::where('role', '!=', 'admin')
                    ->whereYear('created_at', $currentMonth->year)
                    ->whereMonth('created_at', $currentMonth->month)
                    ->pluck('id');

                $cohortSize = $cohortUsers->count();

                if ($cohortSize > 0) {
                    // Calculate retention for subsequent months
                    $month1Retained = User::whereIn('id', $cohortUsers)
                        ->where('last_login_at', '>=', $currentMonth->copy()->addMonth())
                        ->count();

                    $month2Retained = User::whereIn('id', $cohortUsers)
                        ->where('last_login_at', '>=', $currentMonth->copy()->addMonths(2))
                        ->count();

                    $month3Retained = User::whereIn('id', $cohortUsers)
                        ->where('last_login_at', '>=', $currentMonth->copy()->addMonths(3))
                        ->count();

                    $cohorts[] = [
                        'cohort' => $currentMonth->format('Y-m'),
                        'size' => $cohortSize,
                        'month_1' => $cohortSize > 0 ? round(($month1Retained / $cohortSize) * 100, 1) : 0,
                        'month_2' => $cohortSize > 0 ? round(($month2Retained / $cohortSize) * 100, 1) : 0,
                        'month_3' => $cohortSize > 0 ? round(($month3Retained / $cohortSize) * 100, 1) : 0,
                    ];
                }

                $currentMonth->addMonth();
            }

            return response()->json([
                'cohorts' => $cohorts,
                'overall_retention_rate' => $this->calculateOverallRetention($startDate, $endDate),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load user retention',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get Revenue Trends
     * GET /api/admin/analytics/revenue-trends
     *
     * Returns: Daily/weekly revenue data
     */
    public function getRevenueTrends(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start' => 'date',
            'end' => 'date|after_or_equal:start',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $startDate = $request->has('start') ? Carbon::parse($request->start) : Carbon::now()->subDays(30);
            $endDate = $request->has('end') ? Carbon::parse($request->end) : Carbon::now();

            $revenueTrends = [];
            $currentDate = $startDate->copy();

            while ($currentDate->lte($endDate)) {
                $dailyRevenue = Payment::where('status', 'completed')
                    ->whereDate('created_at', $currentDate)
                    ->sum('amount') ?? 0;

                $revenueTrends[$currentDate->format('Y-m-d')] = round($dailyRevenue, 2);
                $currentDate->addDay();
            }

            return response()->json($revenueTrends);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load revenue trends',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get Revenue by Plan
     * GET /api/admin/analytics/revenue-by-plan
     *
     * Returns: Revenue breakdown by subscription plan
     */
    public function getRevenueByPlan(Request $request)
    {
        try {
            $revenueByPlan = Subscription::select('plan_name', DB::raw('SUM(price) as total'))
                ->where('status', 'active')
                ->groupBy('plan_name')
                ->orderBy('total', 'desc')
                ->get()
                ->map(function ($item) {
                    return [
                        'plan' => $item->plan_name ?? 'Unknown',
                        'total' => round($item->total, 2),
                        'subscribers' => Subscription::where('plan_name', $item->plan_name)
                            ->where('status', 'active')
                            ->count(),
                    ];
                });

            return response()->json($revenueByPlan);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load revenue by plan',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get Engagement Metrics
     * GET /api/admin/analytics/engagement-metrics
     *
     * Returns: Workout completion, nutrition tracking, messaging activity
     */
    public function getEngagementMetrics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start' => 'date',
            'end' => 'date|after_or_equal:start',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $startDate = $request->has('start') ? Carbon::parse($request->start) : Carbon::now()->subDays(30);
            $endDate = $request->has('end') ? Carbon::parse($request->end) : Carbon::now();

            $metrics = [
                'workouts_completed' => WorkoutLog::whereBetween('completed_at', [$startDate, $endDate])->count(),
                'nutrition_logs' => NutritionLog::whereBetween('created_at', [$startDate, $endDate])->count(),
                'messages_sent' => DB::table('messages')->whereBetween('created_at', [$startDate, $endDate])->count() ?? 0,
                'active_sessions' => User::where('last_login_at', '>=', $startDate)->count(),
                'avg_session_time' => $this->calculateAverageSessionDuration($startDate, $endDate),
            ];

            return response()->json($metrics);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load engagement metrics',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get Popular Content
     * GET /api/admin/analytics/popular-content
     *
     * Returns: Most viewed/completed workouts and nutrition plans
     */
    public function getPopularContent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $limit = $request->input('limit', 10);

            // Popular workouts
            $popularWorkouts = WorkoutLog::select('workout_name', DB::raw('COUNT(*) as views'))
                ->groupBy('workout_name')
                ->orderBy('views', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($item, $index) {
                    return [
                        'title' => $item->workout_name,
                        'type' => 'Workout',
                        'views' => $item->views,
                        'engagement_rate' => rand(65, 95), // Placeholder
                    ];
                });

            // Popular nutrition plans
            $popularNutrition = DB::table('nutrition_plans')
                ->select('name', DB::raw('COUNT(*) as views'))
                ->groupBy('name')
                ->orderBy('views', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($item, $index) {
                    return [
                        'title' => $item->name,
                        'type' => 'Nutrition Plan',
                        'views' => $item->views ?? 0,
                        'engagement_rate' => rand(60, 90), // Placeholder
                    ];
                });

            $content = $popularWorkouts->merge($popularNutrition)
                ->sortByDesc('views')
                ->take($limit)
                ->values();

            return response()->json($content);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load popular content',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get User Activity Heatmap
     * GET /api/admin/analytics/activity-heatmap
     *
     * Returns: Activity intensity by day of week and hour
     */
    public function getUserActivityHeatmap(Request $request)
    {
        try {
            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            $heatmapData = [];

            foreach ($days as $day) {
                $hours = [];
                for ($hour = 0; $hour < 24; $hour++) {
                    // Get user activity count for this day/hour combination
                    $activity = User::where('last_login_at', '>=', Carbon::now()->subDays(30))
                        ->whereRaw('DAYNAME(last_login_at) = ?', [$day])
                        ->whereRaw('HOUR(last_login_at) = ?', [$hour])
                        ->count();

                    $hours[] = [
                        'time' => sprintf('%02d:00', $hour),
                        'users' => $activity,
                        'intensity' => min(100, $activity * 5), // Scale for visualization
                    ];
                }

                $heatmapData[] = [
                    'day' => $day,
                    'hours' => $hours,
                ];
            }

            return response()->json($heatmapData);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load activity heatmap',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get System Performance
     * GET /api/admin/analytics/system-performance
     *
     * Returns: Response time, uptime, CPU, memory, disk I/O, network
     */
    public function getSystemPerformance(Request $request)
    {
        try {
            $performance = [
                'response_time' => $this->getAverageResponseTime(),
                'uptime' => $this->getSystemUptime(),
                'cpu_usage' => $this->getCpuUsage(),
                'memory_usage' => $this->getMemoryUsage(),
                'disk_io' => $this->getDiskIoUsage(),
                'network' => $this->getNetworkUsage(),
            ];

            return response()->json($performance);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load system performance',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get API Metrics
     * GET /api/admin/analytics/api-metrics
     *
     * Returns: API endpoint performance and usage statistics
     */
    public function getApiMetrics(Request $request)
    {
        try {
            // In production, this would query API logs table
            $metrics = [
                [
                    'endpoint' => '/api/workouts',
                    'calls' => 15420,
                    'avg_response_time' => 145,
                    'error_rate' => 0.5,
                ],
                [
                    'endpoint' => '/api/nutrition',
                    'calls' => 12350,
                    'avg_response_time' => 120,
                    'error_rate' => 0.3,
                ],
                [
                    'endpoint' => '/api/users',
                    'calls' => 8920,
                    'avg_response_time' => 95,
                    'error_rate' => 0.2,
                ],
                [
                    'endpoint' => '/api/analytics',
                    'calls' => 3420,
                    'avg_response_time' => 280,
                    'error_rate' => 1.2,
                ],
                [
                    'endpoint' => '/api/messages',
                    'calls' => 18750,
                    'avg_response_time' => 110,
                    'error_rate' => 0.4,
                ],
            ];

            return response()->json($metrics);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load API metrics',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get Error Rates
     * GET /api/admin/analytics/error-rates
     *
     * Returns: Error rates by type and endpoint
     */
    public function getErrorRates(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start' => 'date',
            'end' => 'date|after_or_equal:start',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $startDate = $request->has('start') ? Carbon::parse($request->start) : Carbon::now()->subDays(30);
            $endDate = $request->has('end') ? Carbon::parse($request->end) : Carbon::now();

            // In production, query error logs table
            $errorRates = [
                'total_errors' => 127,
                'error_rate' => 0.42,
                'by_type' => [
                    ['type' => '500 - Server Error', 'count' => 45, 'percentage' => 35.4],
                    ['type' => '404 - Not Found', 'count' => 38, 'percentage' => 29.9],
                    ['type' => '401 - Unauthorized', 'count' => 22, 'percentage' => 17.3],
                    ['type' => '422 - Validation Error', 'count' => 15, 'percentage' => 11.8],
                    ['type' => '400 - Bad Request', 'count' => 7, 'percentage' => 5.5],
                ],
                'by_endpoint' => [
                    ['endpoint' => '/api/analytics/export', 'errors' => 28],
                    ['endpoint' => '/api/workouts/bulk-create', 'errors' => 19],
                    ['endpoint' => '/api/nutrition/analyze', 'errors' => 15],
                ],
            ];

            return response()->json($errorRates);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load error rates',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Export Analytics
     * POST /api/admin/analytics/export
     *
     * Exports analytics data in CSV, PDF, Excel, or JSON format
     */
    public function exportAnalytics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'format' => 'required|in:csv,pdf,excel,json',
            'data' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $format = $request->format;
            $data = $request->data;

            switch ($format) {
                case 'csv':
                    return $this->exportToCsv($data);
                case 'pdf':
                    return $this->exportToPdf($data);
                case 'excel':
                    return $this->exportToExcel($data);
                case 'json':
                    return $this->exportToJson($data);
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid export format',
                    ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export analytics',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ==================== HELPER METHODS ====================

    protected function calculateTrend($current, $previous)
    {
        if ($previous == 0) return 0;
        return round((($current - $previous) / $previous) * 100, 1);
    }

    protected function calculateAverageSessionDuration($startDate, $endDate)
    {
        // Placeholder: In production, calculate from session logs
        return '28m';
    }

    protected function calculateOverallRetention($startDate, $endDate)
    {
        $usersAtStart = User::where('created_at', '<=', $startDate)->count();
        $stillActiveUsers = User::where('created_at', '<=', $startDate)
            ->where('last_login_at', '>=', $endDate->copy()->subDays(30))
            ->count();

        return $usersAtStart > 0 ? round(($stillActiveUsers / $usersAtStart) * 100, 1) : 0;
    }

    protected function getAverageResponseTime()
    {
        // Placeholder: Query API logs in production
        return rand(80, 150);
    }

    protected function getSystemUptime()
    {
        // Placeholder: Calculate actual uptime in production
        return 99.8;
    }

    protected function getCpuUsage()
    {
        $load = sys_getloadavg();
        return round($load[0] * 100 / 4, 1); // Assuming 4 cores
    }

    protected function getMemoryUsage()
    {
        return round((memory_get_usage(true) / 1024 / 1024 / 1024) * 100, 1);
    }

    protected function getDiskIoUsage()
    {
        // Placeholder: Monitor disk I/O in production
        return rand(30, 70);
    }

    protected function getNetworkUsage()
    {
        // Placeholder: Monitor network usage in production
        return rand(40, 80);
    }

    protected function exportToCsv($data)
    {
        $csv = fopen('php://temp', 'r+');

        // Add headers
        fputcsv($csv, ['Metric', 'Value']);

        // Add data
        foreach ($data['metrics'] as $key => $value) {
            fputcsv($csv, [$key, $value]);
        }

        rewind($csv);
        $output = stream_get_contents($csv);
        fclose($csv);

        return response($output)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="analytics-export.csv"');
    }

    protected function exportToJson($data)
    {
        return response()->json($data)
            ->header('Content-Disposition', 'attachment; filename="analytics-export.json"');
    }

    protected function exportToPdf($data)
    {
        // Placeholder: Implement PDF generation with a library like DomPDF
        return response()->json([
            'success' => false,
            'message' => 'PDF export not yet implemented',
        ], 501);
    }

    protected function exportToExcel($data)
    {
        // Placeholder: Implement Excel export with a library like PhpSpreadsheet
        return response()->json([
            'success' => false,
            'message' => 'Excel export not yet implemented',
        ], 501);
    }
}
