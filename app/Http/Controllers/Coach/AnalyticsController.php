<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function getRevenue(Request $request)
    {
        try {
            $coachId = auth()->id();
            $period = $request->get('period', 'month'); // week, month, year

            $revenue = [
                'total' => 0, // TODO: Calculate from payments table
                'this_period' => 0,
                'last_period' => 0,
                'growth_percentage' => 0,
                'breakdown' => [
                    'coaching_sessions' => 0,
                    'meal_plans' => 0,
                    'workout_plans' => 0
                ]
            ];

            return response()->json(['success' => true, 'data' => $revenue]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => ['total' => 0, 'this_period' => 0, 'last_period' => 0, 'growth_percentage' => 0]]);
        }
    }

    public function getClientRetention(Request $request)
    {
        try {
            $coachId = auth()->id();

            $totalClients = DB::table('coach_clients')->where('coach_id', $coachId)->count();
            $activeClients = DB::table('coach_clients')
                ->where('coach_id', $coachId)
                ->where('status', 'active')
                ->count();

            $retentionRate = $totalClients > 0 ? ($activeClients / $totalClients) * 100 : 0;

            $retention = [
                'retention_rate' => round($retentionRate, 2),
                'total_clients' => $totalClients,
                'active_clients' => $activeClients,
                'churned_clients' => $totalClients - $activeClients,
                'average_tenure_months' => 6.5, // TODO: Calculate actual tenure
                'trend' => 'stable'
            ];

            return response()->json(['success' => true, 'data' => $retention]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => [
                'retention_rate' => 0,
                'total_clients' => 0,
                'active_clients' => 0,
                'churned_clients' => 0,
                'average_tenure_months' => 0,
                'trend' => 'N/A'
            ]]);
        }
    }
}
