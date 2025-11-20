<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Coach;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PTStudioController extends Controller
{
    /**
     * Get PT Studio dashboard overview
     */
    public function getDashboard(Request $request, $studioId)
    {
        try {
            $startDate = $request->get('start_date', Carbon::now()->startOfMonth());
            $endDate = $request->get('end_date', Carbon::now()->endOfMonth());

            // Get studio coaches
            $coaches = Coach::where('organization_id', $studioId)->get();
            $coachIds = $coaches->pluck('id');

            // Get total clients
            $totalClients = User::whereIn('coach_id', $coachIds)->count();

            // Get appointments stats
            $appointments = Appointment::whereIn('coach_id', $coachIds)
                ->whereBetween('scheduled_at', [$startDate, $endDate])
                ->get();

            $appointmentStats = [
                'total' => $appointments->count(),
                'completed' => $appointments->where('status', 'completed')->count(),
                'scheduled' => $appointments->where('status', 'scheduled')->count(),
                'cancelled' => $appointments->where('status', 'cancelled')->count(),
                'no_show' => $appointments->where('status', 'no-show')->count(),
            ];

            // Get upcoming appointments
            $upcomingAppointments = Appointment::whereIn('coach_id', $coachIds)
                ->where('scheduled_at', '>=', Carbon::now())
                ->where('status', 'scheduled')
                ->with(['coach', 'client'])
                ->orderBy('scheduled_at', 'asc')
                ->limit(10)
                ->get();

            // Get revenue stats (placeholder - would need payment integration)
            $revenue = [
                'total' => $appointments->where('status', 'completed')->count() * 100, // $100 per session placeholder
                'pending' => $appointments->where('status', 'scheduled')->count() * 100,
            ];

            // Get coach performance
            $coachPerformance = [];
            foreach ($coaches as $coach) {
                $coachAppointments = $appointments->where('coach_id', $coach->id);
                $coachPerformance[] = [
                    'coach_id' => $coach->id,
                    'coach_name' => $coach->first_name . ' ' . $coach->last_name,
                    'total_sessions' => $coachAppointments->where('status', 'completed')->count(),
                    'scheduled_sessions' => $coachAppointments->where('status', 'scheduled')->count(),
                    'no_shows' => $coachAppointments->where('status', 'no-show')->count(),
                    'cancellations' => $coachAppointments->where('status', 'cancelled')->count(),
                ];
            }

            return response()->json([
                'studio_id' => $studioId,
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
                'overview' => [
                    'total_coaches' => $coaches->count(),
                    'total_clients' => $totalClients,
                    'total_appointments' => $appointmentStats['total'],
                ],
                'appointment_stats' => $appointmentStats,
                'revenue' => $revenue,
                'upcoming_appointments' => $upcomingAppointments,
                'coach_performance' => $coachPerformance,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting PT Studio dashboard: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to load dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get studio coaches
     */
    public function getCoaches($studioId)
    {
        try {
            $coaches = Coach::where('organization_id', $studioId)
                ->with(['user'])
                ->get();

            return response()->json($coaches);
        } catch (\Exception $e) {
            Log::error('Error getting studio coaches: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to load coaches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get studio clients
     */
    public function getClients(Request $request, $studioId)
    {
        try {
            $coaches = Coach::where('organization_id', $studioId)->pluck('id');

            $query = User::whereIn('coach_id', $coaches);

            // Filter by coach
            if ($request->has('coach_id')) {
                $query->where('coach_id', $request->coach_id);
            }

            // Search by name
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $clients = $query->with(['coach'])->paginate(20);

            return response()->json($clients);
        } catch (\Exception $e) {
            Log::error('Error getting studio clients: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to load clients',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get studio analytics
     */
    public function getAnalytics(Request $request, $studioId)
    {
        try {
            $period = $request->get('period', '30'); // days
            $startDate = Carbon::now()->subDays($period);

            $coaches = Coach::where('organization_id', $studioId)->pluck('id');

            // Daily appointment trends
            $dailyStats = Appointment::whereIn('coach_id', $coaches)
                ->where('scheduled_at', '>=', $startDate)
                ->select(
                    DB::raw('DATE(scheduled_at) as date'),
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed'),
                    DB::raw('SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled'),
                    DB::raw('SUM(CASE WHEN status = "no-show" THEN 1 ELSE 0 END) as no_show')
                )
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->get();

            // Peak hours analysis
            $peakHours = Appointment::whereIn('coach_id', $coaches)
                ->where('scheduled_at', '>=', $startDate)
                ->where('status', 'completed')
                ->select(
                    DB::raw('HOUR(scheduled_at) as hour'),
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('hour')
                ->orderBy('count', 'desc')
                ->get();

            // Client retention
            $returningClients = Appointment::whereIn('coach_id', $coaches)
                ->where('scheduled_at', '>=', $startDate)
                ->select('client_id', DB::raw('COUNT(*) as visit_count'))
                ->groupBy('client_id')
                ->having('visit_count', '>', 1)
                ->count();

            $totalClients = User::whereIn('coach_id', $coaches)->count();
            $retentionRate = $totalClients > 0 ? ($returningClients / $totalClients) * 100 : 0;

            return response()->json([
                'period_days' => $period,
                'daily_stats' => $dailyStats,
                'peak_hours' => $peakHours,
                'retention' => [
                    'returning_clients' => $returningClients,
                    'total_clients' => $totalClients,
                    'retention_rate' => round($retentionRate, 2),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting PT Studio analytics: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to load analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get calendar view for studio
     */
    public function getCalendar(Request $request, $studioId)
    {
        try {
            $startDate = $request->get('start_date', Carbon::now()->startOfMonth());
            $endDate = $request->get('end_date', Carbon::now()->endOfMonth());

            $coaches = Coach::where('organization_id', $studioId)->pluck('id');

            $appointments = Appointment::whereIn('coach_id', $coaches)
                ->whereBetween('scheduled_at', [$startDate, $endDate])
                ->with(['coach', 'client'])
                ->get()
                ->map(function($appointment) {
                    return [
                        'id' => $appointment->id,
                        'title' => $appointment->title,
                        'start' => $appointment->scheduled_at,
                        'end' => $appointment->end_time,
                        'coach' => $appointment->coach->first_name . ' ' . $appointment->coach->last_name,
                        'client' => $appointment->client->first_name . ' ' . $appointment->client->last_name,
                        'status' => $appointment->status,
                        'type' => $appointment->type,
                        'location' => $appointment->location,
                    ];
                });

            return response()->json([
                'appointments' => $appointments,
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting PT Studio calendar: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to load calendar',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
