<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WorkoutLog;
use App\Models\ContentVideo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Admin Analytics Dashboard Controller
 * Provides comprehensive analytics for admin dashboard
 */
class AdminAnalyticsDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        // Add admin role check in production
        // $this->middleware('role:admin');
    }

    /**
     * 1. Dashboard Summary
     * GET /api/admin/analytics/dashboard-summary
     */
    public function getDashboardSummary(Request $request)
    {
        try {
            $period = $request->input('period', 'month'); // week, month, quarter, year
            [$startDate, $endDate] = $this->getDateRange($period);

            $summary = [
                'totalUsers' => User::count(),
                'activeUsers' => User::where('status', 'active')->count(),
                'newUsersThisPeriod' => User::whereBetween('created_at', [$startDate, $endDate])->count(),
                'totalRevenue' => $this->getTotalRevenue($startDate, $endDate),
                'activeSubscriptions' => $this->getActiveSubscriptions(),
                'totalWorkouts' => WorkoutLog::count(),
                'workoutsThisPeriod' => WorkoutLog::whereBetween('completed_at', [$startDate, $endDate])->count(),
                'averageEngagement' => $this->getAverageEngagement($startDate, $endDate),
                'churnRate' => $this->getChurnRate($startDate, $endDate),
                'conversionRate' => $this->getConversionRate($startDate, $endDate),
            ];

            return response()->json([
                'success' => true,
                'data' => $summary,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard summary',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * 2. User Growth Analytics
     * GET /api/admin/analytics/user-growth
     */
    public function getUserGrowth(Request $request)
    {
        try {
            $period = $request->input('period', 'month');
            [$startDate, $endDate] = $this->getDateRange($period);

            $growth = DB::table('users')
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->get();

            $cumulativeGrowth = [];
            $total = User::where('created_at', '<', $startDate)->count();

            foreach ($growth as $day) {
                $total += $day->count;
                $cumulativeGrowth[] = [
                    'date' => $day->date,
                    'newUsers' => $day->count,
                    'totalUsers' => $total,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'dailyGrowth' => $growth,
                    'cumulativeGrowth' => $cumulativeGrowth,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load user growth data',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * 3. User Demographics
     * GET /api/admin/analytics/user-demographics
     */
    public function getUserDemographics(Request $request)
    {
        try {
            $demographics = [
                'byGender' => DB::table('users')
                    ->select('gender', DB::raw('COUNT(*) as count'))
                    ->groupBy('gender')
                    ->get(),
                'byAgeGroup' => $this->getUsersByAgeGroup(),
                'byLocation' => DB::table('users')
                    ->select('country', DB::raw('COUNT(*) as count'))
                    ->groupBy('country')
                    ->orderBy('count', 'desc')
                    ->limit(10)
                    ->get(),
                'bySubscriptionPlan' => DB::table('users')
                    ->join('subscriptions', 'users.id', '=', 'subscriptions.user_id')
                    ->select('subscriptions.plan_name', DB::raw('COUNT(*) as count'))
                    ->where('subscriptions.status', 'active')
                    ->groupBy('subscriptions.plan_name')
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $demographics,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load demographics',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * 4. User Retention
     * GET /api/admin/analytics/user-retention
     */
    public function getUserRetention(Request $request)
    {
        try {
            $cohortMonth = $request->input('cohort_month', now()->format('Y-m'));

            $retention = $this->calculateCohortRetention($cohortMonth);

            return response()->json([
                'success' => true,
                'data' => $retention,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load retention data',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * 5. Revenue Trends
     * GET /api/admin/analytics/revenue-trends
     */
    public function getRevenueTrends(Request $request)
    {
        try {
            $period = $request->input('period', 'month');
            [$startDate, $endDate] = $this->getDateRange($period);

            $revenue = DB::table('payments')
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('SUM(amount) as total_revenue'),
                    DB::raw('COUNT(*) as transaction_count')
                )
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'succeeded')
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'revenue' => $revenue,
                    'totalRevenue' => $revenue->sum('total_revenue'),
                    'averageDailyRevenue' => $revenue->avg('total_revenue'),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load revenue trends',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * 6. Revenue by Plan
     * GET /api/admin/analytics/revenue-by-plan
     */
    public function getRevenueByPlan(Request $request)
    {
        try {
            $period = $request->input('period', 'month');
            [$startDate, $endDate] = $this->getDateRange($period);

            $revenueByPlan = DB::table('subscriptions')
                ->join('payments', 'subscriptions.id', '=', 'payments.subscription_id')
                ->select(
                    'subscriptions.plan_name',
                    DB::raw('COUNT(DISTINCT subscriptions.user_id) as subscribers'),
                    DB::raw('SUM(payments.amount) as total_revenue'),
                    DB::raw('AVG(payments.amount) as average_payment')
                )
                ->whereBetween('payments.created_at', [$startDate, $endDate])
                ->where('payments.status', 'succeeded')
                ->groupBy('subscriptions.plan_name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $revenueByPlan,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load revenue by plan',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * 7. Engagement Metrics
     * GET /api/admin/analytics/engagement-metrics
     */
    public function getEngagementMetrics(Request $request)
    {
        try {
            $period = $request->input('period', 'month');
            [$startDate, $endDate] = $this->getDateRange($period);

            $metrics = [
                'dailyActiveUsers' => $this->getDailyActiveUsers($startDate, $endDate),
                'weeklyActiveUsers' => $this->getWeeklyActiveUsers($startDate, $endDate),
                'monthlyActiveUsers' => $this->getMonthlyActiveUsers($startDate, $endDate),
                'averageSessionDuration' => $this->getAverageSessionDuration($startDate, $endDate),
                'totalWorkoutsCompleted' => WorkoutLog::whereBetween('completed_at', [$startDate, $endDate])->count(),
                'averageWorkoutsPerUser' => $this->getAverageWorkoutsPerUser($startDate, $endDate),
                'contentViewsTotal' => $this->getTotalContentViews($startDate, $endDate),
            ];

            return response()->json([
                'success' => true,
                'data' => $metrics,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load engagement metrics',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * 8. Popular Content
     * GET /api/admin/analytics/popular-content
     */
    public function getPopularContent(Request $request)
    {
        try {
            $period = $request->input('period', 'month');
            $contentType = $request->input('type', 'all'); // all, video, article, workout
            [$startDate, $endDate] = $this->getDateRange($period);

            $popularVideos = DB::table('content_views')
                ->join('content_videos', 'content_views.video_id', '=', 'content_videos.id')
                ->select(
                    'content_videos.id',
                    'content_videos.title',
                    'content_videos.category',
                    DB::raw('COUNT(*) as view_count'),
                    DB::raw('COUNT(DISTINCT content_views.user_id) as unique_viewers')
                )
                ->whereBetween('content_views.viewed_at', [$startDate, $endDate])
                ->groupBy('content_videos.id', 'content_videos.title', 'content_videos.category')
                ->orderBy('view_count', 'desc')
                ->limit(20)
                ->get();

            $popularWorkouts = DB::table('workout_logs')
                ->join('workouts', 'workout_logs.workout_id', '=', 'workouts.id')
                ->select(
                    'workouts.id',
                    'workouts.name',
                    'workouts.category',
                    DB::raw('COUNT(*) as completion_count'),
                    DB::raw('COUNT(DISTINCT workout_logs.user_id) as unique_users')
                )
                ->whereBetween('workout_logs.completed_at', [$startDate, $endDate])
                ->groupBy('workouts.id', 'workouts.name', 'workouts.category')
                ->orderBy('completion_count', 'desc')
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'popularVideos' => $popularVideos,
                    'popularWorkouts' => $popularWorkouts,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load popular content',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * 9. Activity Heatmap
     * GET /api/admin/analytics/activity-heatmap
     */
    public function getActivityHeatmap(Request $request)
    {
        try {
            $period = $request->input('period', 'month');
            [$startDate, $endDate] = $this->getDateRange($period);

            $heatmap = DB::table('workout_logs')
                ->select(
                    DB::raw('DAYOFWEEK(completed_at) as day_of_week'),
                    DB::raw('HOUR(completed_at) as hour_of_day'),
                    DB::raw('COUNT(*) as activity_count')
                )
                ->whereBetween('completed_at', [$startDate, $endDate])
                ->groupBy('day_of_week', 'hour_of_day')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $heatmap,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load activity heatmap',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * 10. System Performance
     * GET /api/admin/analytics/system-performance
     */
    public function getSystemPerformance(Request $request)
    {
        try {
            $performance = [
                'serverStatus' => 'operational',
                'databaseSize' => $this->getDatabaseSize(),
                'totalRequests' => $this->getTotalApiRequests(),
                'averageResponseTime' => $this->getAverageResponseTime(),
                'cacheHitRate' => $this->getCacheHitRate(),
                'storageUsed' => $this->getStorageUsed(),
                'bandwidthUsed' => $this->getBandwidthUsed(),
            ];

            return response()->json([
                'success' => true,
                'data' => $performance,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load system performance',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * 11. API Metrics
     * GET /api/admin/analytics/api-metrics
     */
    public function getApiMetrics(Request $request)
    {
        try {
            $period = $request->input('period', 'day');
            [$startDate, $endDate] = $this->getDateRange($period);

            $metrics = [
                'totalRequests' => $this->getTotalApiRequests($startDate, $endDate),
                'requestsByEndpoint' => $this->getRequestsByEndpoint($startDate, $endDate),
                'requestsByStatusCode' => $this->getRequestsByStatusCode($startDate, $endDate),
                'averageResponseTime' => $this->getAverageResponseTime($startDate, $endDate),
                'slowestEndpoints' => $this->getSlowestEndpoints($startDate, $endDate),
            ];

            return response()->json([
                'success' => true,
                'data' => $metrics,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load API metrics',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * 12. Error Rates
     * GET /api/admin/analytics/error-rates
     */
    public function getErrorRates(Request $request)
    {
        try {
            $period = $request->input('period', 'day');
            [$startDate, $endDate] = $this->getDateRange($period);

            $errors = [
                'totalErrors' => $this->getTotalErrors($startDate, $endDate),
                'errorsByType' => $this->getErrorsByType($startDate, $endDate),
                'errorRate' => $this->getErrorRate($startDate, $endDate),
                'topErrors' => $this->getTopErrors($startDate, $endDate),
                'errorTrend' => $this->getErrorTrend($startDate, $endDate),
            ];

            return response()->json([
                'success' => true,
                'data' => $errors,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load error rates',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * 13. Export Analytics Data
     * POST /api/admin/analytics/export
     */
    public function exportAnalytics(Request $request)
    {
        try {
            $format = $request->input('format', 'pdf'); // pdf, csv, excel
            $dataTypes = $request->input('data_types', ['summary']);
            $period = $request->input('period', 'month');

            [$startDate, $endDate] = $this->getDateRange($period);

            $data = $this->gatherExportData($dataTypes, $startDate, $endDate);

            switch ($format) {
                case 'pdf':
                    return $this->exportToPdf($data, $startDate, $endDate);
                case 'csv':
                    return $this->exportToCsv($data);
                case 'excel':
                    return $this->exportToExcel($data);
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Unsupported export format',
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

    // Helper Methods (Placeholders - implement based on your schema)

    protected function getDateRange($period)
    {
        return match($period) {
            'day' => [now()->startOfDay(), now()],
            'week' => [now()->startOfWeek(), now()],
            'month' => [now()->startOfMonth(), now()],
            'quarter' => [now()->startOfQuarter(), now()],
            'year' => [now()->startOfYear(), now()],
            default => [now()->startOfMonth(), now()],
        };
    }

    protected function getTotalRevenue($startDate, $endDate)
    {
        return DB::table('payments')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'succeeded')
            ->sum('amount') ?? 0;
    }

    protected function getActiveSubscriptions()
    {
        return DB::table('subscriptions')
            ->where('status', 'active')
            ->count();
    }

    protected function getAverageEngagement($startDate, $endDate)
    {
        // Implement based on your engagement tracking
        return 75.5; // Placeholder
    }

    protected function getChurnRate($startDate, $endDate)
    {
        // Implement churn calculation
        return 5.2; // Placeholder
    }

    protected function getConversionRate($startDate, $endDate)
    {
        // Implement conversion tracking
        return 12.5; // Placeholder
    }

    protected function getUsersByAgeGroup()
    {
        // Implement age group calculation
        return collect([
            ['ageGroup' => '18-24', 'count' => 250],
            ['ageGroup' => '25-34', 'count' => 450],
            ['ageGroup' => '35-44', 'count' => 300],
            ['ageGroup' => '45+', 'count' => 150],
        ]);
    }

    protected function calculateCohortRetention($cohortMonth)
    {
        // Implement cohort retention analysis
        return [
            'cohortMonth' => $cohortMonth,
            'cohortSize' => 100,
            'retention' => [
                ['month' => 0, 'users' => 100, 'percentage' => 100],
                ['month' => 1, 'users' => 85, 'percentage' => 85],
                ['month' => 2, 'users' => 72, 'percentage' => 72],
                ['month' => 3, 'users' => 65, 'percentage' => 65],
            ],
        ];
    }

    protected function getDailyActiveUsers($startDate, $endDate)
    {
        return DB::table('user_sessions')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->distinct('user_id')
            ->count('user_id');
    }

    protected function getWeeklyActiveUsers($startDate, $endDate)
    {
        return DB::table('user_sessions')
            ->whereBetween('created_at', [now()->subWeek(), now()])
            ->distinct('user_id')
            ->count('user_id');
    }

    protected function getMonthlyActiveUsers($startDate, $endDate)
    {
        return DB::table('user_sessions')
            ->whereBetween('created_at', [now()->subMonth(), now()])
            ->distinct('user_id')
            ->count('user_id');
    }

    protected function getAverageSessionDuration($startDate, $endDate)
    {
        return 25; // minutes (placeholder)
    }

    protected function getAverageWorkoutsPerUser($startDate, $endDate)
    {
        $totalUsers = User::where('status', 'active')->count();
        $totalWorkouts = WorkoutLog::whereBetween('completed_at', [$startDate, $endDate])->count();
        return $totalUsers > 0 ? round($totalWorkouts / $totalUsers, 2) : 0;
    }

    protected function getTotalContentViews($startDate, $endDate)
    {
        return DB::table('content_views')
            ->whereBetween('viewed_at', [$startDate, $endDate])
            ->count();
    }

    protected function getDatabaseSize()
    {
        return '2.5 GB'; // Placeholder
    }

    protected function getTotalApiRequests($startDate = null, $endDate = null)
    {
        return 150000; // Placeholder
    }

    protected function getAverageResponseTime($startDate = null, $endDate = null)
    {
        return 125; // ms (placeholder)
    }

    protected function getCacheHitRate()
    {
        return 85.5; // Placeholder
    }

    protected function getStorageUsed()
    {
        return '45 GB'; // Placeholder
    }

    protected function getBandwidthUsed()
    {
        return '250 GB'; // Placeholder
    }

    protected function getRequestsByEndpoint($startDate, $endDate)
    {
        // Placeholder
        return [];
    }

    protected function getRequestsByStatusCode($startDate, $endDate)
    {
        // Placeholder
        return [];
    }

    protected function getSlowestEndpoints($startDate, $endDate)
    {
        // Placeholder
        return [];
    }

    protected function getTotalErrors($startDate, $endDate)
    {
        return 125; // Placeholder
    }

    protected function getErrorsByType($startDate, $endDate)
    {
        // Placeholder
        return [];
    }

    protected function getErrorRate($startDate, $endDate)
    {
        return 0.08; // Placeholder
    }

    protected function getTopErrors($startDate, $endDate)
    {
        // Placeholder
        return [];
    }

    protected function getErrorTrend($startDate, $endDate)
    {
        // Placeholder
        return [];
    }

    protected function gatherExportData($dataTypes, $startDate, $endDate)
    {
        $data = [];
        foreach ($dataTypes as $type) {
            $data[$type] = match($type) {
                'summary' => $this->getDashboardSummary(request())->getData()->data,
                'users' => $this->getUserGrowth(request())->getData()->data,
                'revenue' => $this->getRevenueTrends(request())->getData()->data,
                'engagement' => $this->getEngagementMetrics(request())->getData()->data,
                default => null,
            };
        }
        return $data;
    }

    protected function exportToPdf($data, $startDate, $endDate)
    {
        $pdf = Pdf::loadView('admin.analytics.export-pdf', [
            'data' => $data,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);

        return $pdf->download('analytics-report-' . now()->format('Y-m-d') . '.pdf');
    }

    protected function exportToCsv($data)
    {
        // Implement CSV export
        return response()->json(['message' => 'CSV export coming soon']);
    }

    protected function exportToExcel($data)
    {
        // Implement Excel export
        return response()->json(['message' => 'Excel export coming soon']);
    }
}
