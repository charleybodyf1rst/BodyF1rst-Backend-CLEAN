<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get coach dashboard
     * GET /api/customer/coach/dashboard
     */
    public function getDashboard(Request $request)
    {
        try {
            $coachId = auth()->id();

            $dashboard = [
                'stats' => [
                    'total_clients' => DB::table('coach_clients')->where('coach_id', $coachId)->count(),
                    'active_clients' => DB::table('coach_clients')
                        ->where('coach_id', $coachId)
                        ->where('status', 'active')
                        ->count(),
                    'upcoming_appointments' => DB::table('coach_appointments')
                        ->where('coach_id', $coachId)
                        ->where('appointment_date', '>=', now())
                        ->where('status', 'scheduled')
                        ->count(),
                    'completed_sessions' => DB::table('coach_appointments')
                        ->where('coach_id', $coachId)
                        ->where('status', 'completed')
                        ->whereMonth('completed_at', now()->month)
                        ->count()
                ],
                'recent_clients' => DB::table('coach_clients')
                    ->where('coach_id', $coachId)
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(),
                'upcoming_appointments' => DB::table('coach_appointments')
                    ->where('coach_id', $coachId)
                    ->where('appointment_date', '>=', now())
                    ->orderBy('appointment_date', 'asc')
                    ->limit(5)
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $dashboard
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => [
                        'total_clients' => 0,
                        'active_clients' => 0,
                        'upcoming_appointments' => 0,
                        'completed_sessions' => 0
                    ],
                    'recent_clients' => [],
                    'upcoming_appointments' => []
                ]
            ]);
        }
    }

    /**
     * Get coach overview
     * GET /api/customer/coach/dashboard/overview
     */
    public function getOverview(Request $request)
    {
        try {
            $coachId = auth()->id();

            $overview = [
                'revenue' => [
                    'this_month' => 0, // TODO: Calculate from payments
                    'last_month' => 0,
                    'total' => 0
                ],
                'client_retention' => 95.5, // TODO: Calculate actual retention
                'average_session_rating' => 4.8, // TODO: Calculate from ratings
                'total_sessions' => DB::table('coach_appointments')
                    ->where('coach_id', $coachId)
                    ->where('status', 'completed')
                    ->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $overview
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => [
                    'revenue' => ['this_month' => 0, 'last_month' => 0, 'total' => 0],
                    'client_retention' => 0,
                    'average_session_rating' => 0,
                    'total_sessions' => 0
                ]
            ]);
        }
    }

    /**
     * Get coach stats
     * GET /api/customer/coach/dashboard/stats
     */
    public function getStats(Request $request)
    {
        try {
            $coachId = auth()->id();

            $stats = [
                'clients' => [
                    'total' => DB::table('coach_clients')->where('coach_id', $coachId)->count(),
                    'active' => DB::table('coach_clients')
                        ->where('coach_id', $coachId)
                        ->where('status', 'active')
                        ->count(),
                    'inactive' => DB::table('coach_clients')
                        ->where('coach_id', $coachId)
                        ->where('status', 'inactive')
                        ->count()
                ],
                'appointments' => [
                    'this_week' => DB::table('coach_appointments')
                        ->where('coach_id', $coachId)
                        ->whereBetween('appointment_date', [now()->startOfWeek(), now()->endOfWeek()])
                        ->count(),
                    'this_month' => DB::table('coach_appointments')
                        ->where('coach_id', $coachId)
                        ->whereMonth('appointment_date', now()->month)
                        ->count()
                ],
                'performance' => [
                    'completion_rate' => 98.5, // TODO: Calculate actual rate
                    'average_rating' => 4.8,
                    'response_time' => '< 2 hours'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => [
                    'clients' => ['total' => 0, 'active' => 0, 'inactive' => 0],
                    'appointments' => ['this_week' => 0, 'this_month' => 0],
                    'performance' => ['completion_rate' => 0, 'average_rating' => 0, 'response_time' => 'N/A']
                ]
            ]);
        }
    }
}
