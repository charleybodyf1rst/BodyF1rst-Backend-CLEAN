<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Workout;
use App\Models\Plan;
use App\Models\UserCompletedWorkout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CoachDashboardController extends Controller
{
    /**
     * Get coach dashboard overview
     */
    public function getDashboardOverview(Request $request)
    {
        try {
            if (Auth::guard('admin')->check()) {
                $coach = Auth::guard('admin')->user();
                $coachId = $coach->id;
            } else {
                $coachId = Auth::id();
                $coach = User::find($coachId);
            }

            if (!$coach) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.'
                ], 404);
            }

            $startDate = Carbon::now()->subDays(30);
            $endDate = Carbon::now();

            $stats = [
                'total_clients' => $this->getTotalClients($coachId),
                'active_clients' => $this->getActiveClients($coachId),
                'total_workouts_created' => $this->getTotalWorkoutsCreated($coachId),
                'total_plans_created' => $this->getTotalPlansCreated($coachId),
                'client_completion_rate' => $this->getClientCompletionRate($coachId),
                'average_client_progress' => $this->getAverageClientProgress($coachId),
                'new_clients_this_month' => $this->getNewClientsThisMonth($coachId),
                'revenue_this_month' => $this->getRevenueThisMonth($coachId)
            ];

            $recentActivities = $this->getRecentActivities($coachId, 10);

            $topClients = $this->getTopPerformingClients($coachId, 5);

            $upcomingSessions = $this->getUpcomingSessions($coachId, 5);

            $engagement = [
                'daily_active_users' => $this->getDailyActiveUsers($coachId),
                'weekly_active_users' => $this->getWeeklyActiveUsers($coachId),
                'average_sessions_per_week' => $this->getAverageSessionsPerWeek($coachId),
                'client_retention_rate' => $this->getClientRetentionRate($coachId)
            ];

            return response()->json([
                'success' => true,
                'coach' => [
                    'id' => $coach->id,
                    'name' => $coach->name,
                    'email' => $coach->email,
                    'specialization' => $coach->specialization ?? 'General Fitness'
                ],
                'stats' => $stats,
                'recent_activities' => $recentActivities,
                'top_clients' => $topClients,
                'upcoming_sessions' => $upcomingSessions,
                'engagement' => $engagement
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard overview',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get coach's clients
     */
    public function getClients(Request $request)
    {
        try {
            $validated = $request->validate([
                'status' => 'nullable|string|in:active,inactive,all',
                'search' => 'nullable|string|max:100',
                'sort_by' => 'nullable|string|in:name,joined_date,last_activity,progress',
                'order' => 'nullable|string|in:asc,desc',
                'limit' => 'nullable|integer|min:1|max:100',
                'page' => 'nullable|integer|min:1'
            ]);

            $coachId = Auth::id();

            $query = DB::table('coach_client_relationships')
                ->join('users', 'coach_client_relationships.client_id', '=', 'users.id')
                ->where('coach_client_relationships.coach_id', $coachId)
                ->select(
                    'users.id',
                    'users.name',
                    'users.email',
                    'users.profile_picture',
                    'users.created_at as joined_date',
                    'coach_client_relationships.assigned_at',
                    'coach_client_relationships.status',
                    'coach_client_relationships.notes'
                );

            if (!empty($validated['status']) && $validated['status'] !== 'all') {
                $query->where('coach_client_relationships.status', $validated['status']);
            }

            if (!empty($validated['search'])) {
                $query->where(function($q) use ($validated) {
                    $q->where('users.name', 'like', '%' . $validated['search'] . '%')
                      ->orWhere('users.email', 'like', '%' . $validated['search'] . '%');
                });
            }

            $sortBy = $validated['sort_by'] ?? 'name';
            $order = $validated['order'] ?? 'asc';

            switch ($sortBy) {
                case 'joined_date':
                    $query->orderBy('users.created_at', $order);
                    break;
                case 'last_activity':
                    $query->orderBy('coach_client_relationships.last_interaction_at', $order);
                    break;
                default:
                    $query->orderBy('users.name', $order);
            }

            $limit = $validated['limit'] ?? 20;
            $clients = $query->paginate($limit);

            foreach ($clients->items() as &$client) {
                $client->stats = $this->getClientStats($client->id);
                $client->current_plans = $this->getClientCurrentPlans($client->id);
                $client->last_workout = $this->getClientLastWorkout($client->id);
            }

            return response()->json([
                'success' => true,
                'clients' => $clients
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch clients',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific client details
     */
    public function getClientDetails($clientId)
    {
        try {
            $coachId = Auth::id();

            $relationship = DB::table('coach_client_relationships')
                ->where('coach_id', $coachId)
                ->where('client_id', $clientId)
                ->first();

            if (!$relationship) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this client'
                ], 403);
            }

            $client = User::find($clientId);
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found'
                ], 404);
            }

            $clientData = [
                'profile' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'phone' => $client->phone,
                    'profile_picture' => $client->profile_picture,
                    'age' => $client->age,
                    'gender' => $client->gender,
                    'height' => $client->height,
                    'weight' => $client->weight,
                    'fitness_level' => $client->fitness_level,
                    'goals' => $client->goals,
                    'medical_conditions' => $client->medical_conditions
                ],
                'stats' => $this->getDetailedClientStats($clientId),
                'current_plans' => $this->getClientPlansDetailed($clientId),
                'workout_history' => $this->getClientWorkoutHistory($clientId, 10),
                'progress' => $this->getClientProgress($clientId),
                'measurements' => $this->getClientMeasurements($clientId),
                'achievements' => $this->getClientAchievements($clientId),
                'notes' => $this->getCoachNotesForClient($coachId, $clientId)
            ];

            return response()->json([
                'success' => true,
                'client' => $clientData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch client details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign workout to clients
     */
    public function assignWorkoutToClients(Request $request)
    {
        try {
            $validated = $request->validate([
                'workout_id' => 'required|integer|exists:workouts,id',
                'client_ids' => 'required|array',
                'client_ids.*' => 'integer|exists:users,id',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date',
                'frequency' => 'nullable|string|in:daily,weekly,custom',
                'days_of_week' => 'nullable|array',
                'days_of_week.*' => 'integer|min:1|max:7',
                'notes' => 'nullable|string|max:500'
            ]);

            $coachId = Auth::id();

            DB::beginTransaction();

            foreach ($validated['client_ids'] as $clientId) {
                $hasAccess = DB::table('coach_client_relationships')
                    ->where('coach_id', $coachId)
                    ->where('client_id', $clientId)
                    ->exists();

                if (!$hasAccess) {
                    throw new \Exception("You don't have access to client ID: {$clientId}");
                }
            }

            $assignments = [];
            foreach ($validated['client_ids'] as $clientId) {
                $assignment = DB::table('workout_assignments')->insertGetId([
                    'workout_id' => $validated['workout_id'],
                    'client_id' => $clientId,
                    'coach_id' => $coachId,
                    'start_date' => $validated['start_date'] ?? now(),
                    'end_date' => $validated['end_date'] ?? null,
                    'frequency' => $validated['frequency'] ?? 'weekly',
                    'days_of_week' => !empty($validated['days_of_week']) ?
                        json_encode($validated['days_of_week']) : null,
                    'notes' => $validated['notes'] ?? null,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $assignments[] = $assignment;

                $this->notifyClientOfWorkoutAssignment($clientId, $validated['workout_id']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Workout assigned successfully to ' . count($validated['client_ids']) . ' clients',
                'assignment_ids' => $assignments
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign workout',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign plan to clients
     */
    public function assignPlanToClients(Request $request)
    {
        try {
            $validated = $request->validate([
                'plan_id' => 'required|integer|exists:plans,id',
                'client_ids' => 'required|array',
                'client_ids.*' => 'integer|exists:users,id',
                'start_date' => 'nullable|date',
                'customizations' => 'nullable|array',
                'customizations.*.client_id' => 'required|integer',
                'customizations.*.modifications' => 'required|array',
                'notes' => 'nullable|string|max:500'
            ]);

            $coachId = Auth::id();

            DB::beginTransaction();

            foreach ($validated['client_ids'] as $clientId) {
                $hasAccess = DB::table('coach_client_relationships')
                    ->where('coach_id', $coachId)
                    ->where('client_id', $clientId)
                    ->exists();

                if (!$hasAccess) {
                    throw new \Exception("You don't have access to client ID: {$clientId}");
                }
            }

            $assignments = [];
            foreach ($validated['client_ids'] as $clientId) {
                $customization = null;
                if (!empty($validated['customizations'])) {
                    foreach ($validated['customizations'] as $custom) {
                        if ($custom['client_id'] == $clientId) {
                            $customization = $custom['modifications'];
                            break;
                        }
                    }
                }

                $assignment = DB::table('user_plans')->insertGetId([
                    'user_id' => $clientId,
                    'plan_id' => $validated['plan_id'],
                    'assigned_by' => $coachId,
                    'started_at' => $validated['start_date'] ?? now(),
                    'status' => 'active',
                    'customizations' => $customization ? json_encode($customization) : null,
                    'coach_notes' => $validated['notes'] ?? null,
                    'completion_percentage' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $assignments[] = $assignment;

                $this->notifyClientOfPlanAssignment($clientId, $validated['plan_id']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Plan assigned successfully to ' . count($validated['client_ids']) . ' clients',
                'assignment_ids' => $assignments
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add coach note for client
     */
    public function addClientNote(Request $request, $clientId)
    {
        try {
            $validated = $request->validate([
                'note' => 'required|string|max:2000',
                'category' => 'nullable|string|in:progress,medical,goals,general',
                'is_private' => 'nullable|boolean'
            ]);

            $coachId = Auth::id();

            $hasAccess = DB::table('coach_client_relationships')
                ->where('coach_id', $coachId)
                ->where('client_id', $clientId)
                ->exists();

            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this client'
                ], 403);
            }

            $noteId = DB::table('coach_notes')->insertGetId([
                'coach_id' => $coachId,
                'client_id' => $clientId,
                'note' => $validated['note'],
                'category' => $validated['category'] ?? 'general',
                'is_private' => $validated['is_private'] ?? true,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Note added successfully',
                'note_id' => $noteId
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add note',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get coach analytics
     */
    public function getAnalytics(Request $request)
    {
        try {
            $validated = $request->validate([
                'period' => 'nullable|string|in:week,month,quarter,year',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date'
            ]);

            $coachId = Auth::id();
            $period = $validated['period'] ?? 'month';

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
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d')
                ],
                'client_metrics' => $this->getClientMetrics($coachId, $startDate, $endDate),
                'workout_metrics' => $this->getWorkoutMetrics($coachId, $startDate, $endDate),
                'engagement_metrics' => $this->getEngagementMetrics($coachId, $startDate, $endDate),
                'revenue_metrics' => $this->getRevenueMetrics($coachId, $startDate, $endDate),
                'performance_trends' => $this->getPerformanceTrends($coachId, $startDate, $endDate)
            ];

            return response()->json([
                'success' => true,
                'analytics' => $analytics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Private helper methods
     */
    private function getTotalClients($coachId)
    {
        return DB::table('coach_client_relationships')
            ->where('coach_id', $coachId)
            ->count();
    }

    private function getActiveClients($coachId)
    {
        return DB::table('coach_client_relationships')
            ->where('coach_id', $coachId)
            ->where('status', 'active')
            ->count();
    }

    private function getTotalWorkoutsCreated($coachId)
    {
        return Workout::where('uploaded_by', $coachId)->count();
    }

    private function getTotalPlansCreated($coachId)
    {
        return Plan::where('uploaded_by', $coachId)->count();
    }

    private function getClientCompletionRate($coachId)
    {
        $clients = DB::table('coach_client_relationships')
            ->where('coach_id', $coachId)
            ->pluck('client_id');

        if ($clients->isEmpty()) return 0;

        $totalSessions = WorkoutSession::whereIn('user_id', $clients)->count();
        $completedSessions = WorkoutSession::whereIn('user_id', $clients)
            ->where('status', 'completed')
            ->count();

        return $totalSessions > 0 ? round(($completedSessions / $totalSessions) * 100, 2) : 0;
    }

    private function getAverageClientProgress($coachId)
    {
        $clients = DB::table('coach_client_relationships')
            ->where('coach_id', $coachId)
            ->pluck('client_id');

        if ($clients->isEmpty()) return 0;

        return DB::table('user_plans')
            ->whereIn('user_id', $clients)
            ->where('status', 'active')
            ->avg('completion_percentage') ?? 0;
    }

    private function getNewClientsThisMonth($coachId)
    {
        return DB::table('coach_client_relationships')
            ->where('coach_id', $coachId)
            ->where('assigned_at', '>=', Carbon::now()->startOfMonth())
            ->count();
    }

    private function getRevenueThisMonth($coachId)
    {
        $revenue = DB::table('coach_session_payments')
            ->where('coach_id', $coachId)
            ->where('status', 'paid')
            ->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
            ->sum('amount');

        return $revenue ?? 0;
    }

    private function getRecentActivities($coachId, $limit)
    {
        return DB::table('activity_logs')
            ->where('coach_id', $coachId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    private function getTopPerformingClients($coachId, $limit)
    {
        $clients = DB::table('coach_client_relationships')
            ->where('coach_id', $coachId)
            ->pluck('client_id');

        return WorkoutSession::whereIn('user_id', $clients)
            ->where('status', 'completed')
            ->select('user_id', DB::raw('COUNT(*) as completed_workouts'))
            ->groupBy('user_id')
            ->orderBy('completed_workouts', 'desc')
            ->limit($limit)
            ->with('user:id,name,email,profile_picture')
            ->get();
    }

    private function getUpcomingSessions($coachId, $limit)
    {
        return DB::table('scheduled_sessions')
            ->where('coach_id', $coachId)
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at', 'asc')
            ->limit($limit)
            ->get();
    }

    private function getDailyActiveUsers($coachId)
    {
        $clients = DB::table('coach_client_relationships')
            ->where('coach_id', $coachId)
            ->pluck('client_id');

        return User::whereIn('id', $clients)
            ->where('last_activity_at', '>=', Carbon::now()->startOfDay())
            ->count();
    }

    private function getWeeklyActiveUsers($coachId)
    {
        $clients = DB::table('coach_client_relationships')
            ->where('coach_id', $coachId)
            ->pluck('client_id');

        return User::whereIn('id', $clients)
            ->where('last_activity_at', '>=', Carbon::now()->startOfWeek())
            ->count();
    }

    private function getAverageSessionsPerWeek($coachId)
    {
        $clients = DB::table('coach_client_relationships')
            ->where('coach_id', $coachId)
            ->pluck('client_id');

        if ($clients->isEmpty()) return 0;

        $sessions = WorkoutSession::whereIn('user_id', $clients)
            ->where('created_at', '>=', Carbon::now()->subWeeks(4))
            ->count();

        return round($sessions / 4, 2);
    }

    private function getClientRetentionRate($coachId)
    {
        $totalClients = DB::table('coach_client_relationships')
            ->where('coach_id', $coachId)
            ->where('assigned_at', '<=', Carbon::now()->subMonths(3))
            ->count();

        if ($totalClients == 0) return 100;

        $activeClients = DB::table('coach_client_relationships')
            ->where('coach_id', $coachId)
            ->where('assigned_at', '<=', Carbon::now()->subMonths(3))
            ->where('status', 'active')
            ->count();

        return round(($activeClients / $totalClients) * 100, 2);
    }

    private function getClientStats($clientId)
    {
        return [
            'total_workouts' => WorkoutSession::where('user_id', $clientId)->count(),
            'completed_workouts' => WorkoutSession::where('user_id', $clientId)
                ->where('status', 'completed')->count(),
            'active_plans' => DB::table('user_plans')
                ->where('user_id', $clientId)
                ->where('status', 'active')->count()
        ];
    }

    private function getClientCurrentPlans($clientId)
    {
        return DB::table('user_plans')
            ->join('plans', 'user_plans.plan_id', '=', 'plans.id')
            ->where('user_plans.user_id', $clientId)
            ->where('user_plans.status', 'active')
            ->select('plans.name', 'user_plans.completion_percentage')
            ->get();
    }

    private function getClientLastWorkout($clientId)
    {
        return WorkoutSession::where('user_id', $clientId)
            ->orderBy('created_at', 'desc')
            ->first(['id', 'workout_id', 'created_at', 'status']);
    }

    private function getDetailedClientStats($clientId)
    {
        return [
            'total_workouts' => WorkoutSession::where('user_id', $clientId)->count(),
            'completed_workouts' => WorkoutSession::where('user_id', $clientId)
                ->where('status', 'completed')->count(),
            'total_duration' => WorkoutSession::where('user_id', $clientId)
                ->sum('duration_minutes'),
            'total_calories' => WorkoutSession::where('user_id', $clientId)
                ->sum('calories_burned'),
            'active_plans' => DB::table('user_plans')
                ->where('user_id', $clientId)
                ->where('status', 'active')->count(),
            'completed_plans' => DB::table('user_plans')
                ->where('user_id', $clientId)
                ->where('status', 'completed')->count()
        ];
    }

    private function getClientPlansDetailed($clientId)
    {
        return DB::table('user_plans')
            ->join('plans', 'user_plans.plan_id', '=', 'plans.id')
            ->where('user_plans.user_id', $clientId)
            ->select(
                'plans.*',
                'user_plans.status',
                'user_plans.started_at',
                'user_plans.completed_at',
                'user_plans.completion_percentage'
            )
            ->get();
    }

    private function getClientWorkoutHistory($clientId, $limit)
    {
        return WorkoutSession::where('user_id', $clientId)
            ->with(['workout', 'exerciseSets'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    private function getClientProgress($clientId)
    {
        $progress = [];
        for ($i = 11; $i >= 0; $i--) {
            $weekStart = Carbon::now()->subWeeks($i)->startOfWeek();
            $weekEnd = Carbon::now()->subWeeks($i)->endOfWeek();

            $progress[] = [
                'week' => $weekStart->format('Y-m-d'),
                'workouts' => WorkoutSession::where('user_id', $clientId)
                    ->whereBetween('created_at', [$weekStart, $weekEnd])
                    ->where('status', 'completed')
                    ->count(),
                'duration' => WorkoutSession::where('user_id', $clientId)
                    ->whereBetween('created_at', [$weekStart, $weekEnd])
                    ->sum('duration_minutes'),
                'calories' => WorkoutSession::where('user_id', $clientId)
                    ->whereBetween('created_at', [$weekStart, $weekEnd])
                    ->sum('calories_burned')
            ];
        }

        return $progress;
    }

    private function getClientMeasurements($clientId)
    {
        return DB::table('plan_measurements')
            ->where('user_id', $clientId)
            ->orderBy('measured_at', 'desc')
            ->limit(10)
            ->get();
    }

    private function getClientAchievements($clientId)
    {
        return DB::table('user_achievements')
            ->where('user_id', $clientId)
            ->orderBy('achieved_at', 'desc')
            ->get();
    }

    private function getCoachNotesForClient($coachId, $clientId)
    {
        return DB::table('coach_notes')
            ->where('coach_id', $coachId)
            ->where('client_id', $clientId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    private function notifyClientOfWorkoutAssignment($clientId, $workoutId)
    {
    }

    private function notifyClientOfPlanAssignment($clientId, $planId)
    {
    }

    private function getClientMetrics($coachId, $startDate, $endDate)
    {
        $clients = DB::table('coach_client_relationships')
            ->where('coach_id', $coachId)
            ->pluck('client_id');

        return [
            'new_clients' => DB::table('coach_client_relationships')
                ->where('coach_id', $coachId)
                ->whereBetween('assigned_at', [$startDate, $endDate])
                ->count(),
            'active_clients' => User::whereIn('id', $clients)
                ->where('last_activity_at', '>=', $startDate)
                ->count(),
            'client_churn' => DB::table('coach_client_relationships')
                ->where('coach_id', $coachId)
                ->where('status', 'inactive')
                ->whereBetween('updated_at', [$startDate, $endDate])
                ->count()
        ];
    }

    private function getWorkoutMetrics($coachId, $startDate, $endDate)
    {
        $clients = DB::table('coach_client_relationships')
            ->where('coach_id', $coachId)
            ->pluck('client_id');

        return [
            'total_sessions' => WorkoutSession::whereIn('user_id', $clients)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'completed_sessions' => WorkoutSession::whereIn('user_id', $clients)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'completed')
                ->count(),
            'total_duration' => WorkoutSession::whereIn('user_id', $clients)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('duration_minutes'),
            'average_duration' => WorkoutSession::whereIn('user_id', $clients)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->avg('duration_minutes')
        ];
    }

    private function getEngagementMetrics($coachId, $startDate, $endDate)
    {
        $clients = DB::table('coach_client_relationships')
            ->where('coach_id', $coachId)
            ->pluck('client_id');

        return [
            'messages_sent' => DB::table('messages')
                ->where('sender_id', $coachId)
                ->whereIn('recipient_id', $clients)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'messages_received' => DB::table('messages')
                ->whereIn('sender_id', $clients)
                ->where('recipient_id', $coachId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'average_response_time' => '2.5 hours' // Placeholder
        ];
    }

    private function getRevenueMetrics($coachId, $startDate, $endDate)
    {
        $totalRevenue = DB::table('coach_session_payments')
            ->where('coach_id', $coachId)
            ->where('status', 'paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        $newRevenue = DB::table('coach_session_payments')
            ->where('coach_id', $coachId)
            ->where('status', 'paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotIn('client_id', function($query) use ($coachId, $startDate) {
                $query->select('client_id')
                    ->from('coach_session_payments')
                    ->where('coach_id', $coachId)
                    ->where('status', 'paid')
                    ->where('created_at', '<', $startDate);
            })
            ->sum('amount');

        $recurringRevenue = DB::table('subscriptions')
            ->join('coach_client_relationships', function($join) use ($coachId) {
                $join->on('subscriptions.user_id', '=', 'coach_client_relationships.client_id')
                     ->where('coach_client_relationships.coach_id', '=', $coachId);
            })
            ->where('subscriptions.status', 'active')
            ->whereBetween('subscriptions.current_period_start', [$startDate, $endDate])
            ->count();

        $totalClients = DB::table('coach_client_relationships')
            ->where('coach_id', $coachId)
            ->where('status', 'active')
            ->count();

        return [
            'total_revenue' => $totalRevenue ?? 0,
            'new_revenue' => $newRevenue ?? 0,
            'recurring_revenue' => $recurringRevenue,
            'average_client_value' => $totalClients > 0 ? round($totalRevenue / $totalClients, 2) : 0
        ];
    }

    private function getPerformanceTrends($coachId, $startDate, $endDate)
    {
        $trends = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $dayEnd = $current->copy()->endOfDay();

            $clients = DB::table('coach_client_relationships')
                ->where('coach_id', $coachId)
                ->pluck('client_id');

            $trends[] = [
                'date' => $current->format('Y-m-d'),
                'workouts' => WorkoutSession::whereIn('user_id', $clients)
                    ->whereDate('created_at', $current)
                    ->count(),
                'active_users' => User::whereIn('id', $clients)
                    ->whereDate('last_activity_at', $current)
                    ->count()
            ];

            $current->addDay();
        }

        return $trends;
    }

    /**
     * Get progression dashboard data
     */
    public function getProgressionDashboard(Request $request)
    {
        try {
            $coachId = Auth::id();

            $clients = DB::table('coach_client_relationships')
                ->where('coach_id', $coachId)
                ->where('status', 'active')
                ->pluck('client_id');

            $stats = [
                'clients_on_track' => $this->getClientsOnTrack($clients),
                'average_compliance' => $this->getAverageCompliance($clients),
                'total_progress_photos' => DB::table('progress_photos')->whereIn('user_id', $clients)->count(),
                'milestones_achieved_this_week' => $this->getMilestonesAchievedThisWeek($clients)
            ];

            $clientProgress = [];
            foreach ($clients as $clientId) {
                $user = User::find($clientId);
                if ($user) {
                    $clientProgress[] = [
                        'client_id' => $user->id,
                        'client_name' => trim($user->first_name . ' ' . $user->last_name),
                        'client_avatar' => $user->profile_image,
                        'compliance_rate' => $this->getClientComplianceRate($user->id),
                        'workouts_completed' => WorkoutSession::where('user_id', $user->id)
                            ->where('status', 'completed')
                            ->where('created_at', '>=', Carbon::now()->subDays(30))
                            ->count(),
                        'current_streak' => $this->getClientStreak($user->id),
                        'last_activity' => $user->last_activity_at
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'client_progress' => $clientProgress,
                    'recent_achievements' => $this->getRecentAchievements($clients, 10)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch progression dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get calendar dashboard data
     */
    public function getCalendarDashboard(Request $request)
    {
        try {
            $coachId = Auth::id();

            $stats = [
                'total_appointments' => DB::table('appointments')
                    ->where('coach_id', $coachId)
                    ->count(),
                'upcoming_appointments' => DB::table('appointments')
                    ->where('coach_id', $coachId)
                    ->where('scheduled_at', '>', now())
                    ->where('status', 'scheduled')
                    ->count(),
                'completed_this_week' => DB::table('appointments')
                    ->where('coach_id', $coachId)
                    ->where('status', 'completed')
                    ->whereBetween('scheduled_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                    ->count(),
                'available_slots' => DB::table('coach_availability')
                    ->where('coach_id', $coachId)
                    ->where('is_available', true)
                    ->count()
            ];

            $appointments = DB::table('appointments')
                ->join('users', 'appointments.client_id', '=', 'users.id')
                ->where('appointments.coach_id', $coachId)
                ->where('appointments.scheduled_at', '>=', Carbon::now()->subMonths(1))
                ->select(
                    'appointments.id',
                    'appointments.title',
                    'appointments.client_id',
                    DB::raw("CONCAT_WS(' ', users.first_name, users.last_name) as client_name"),
                    'appointments.type',
                    'appointments.scheduled_at as startTime',
                    'appointments.end_time as endTime',
                    'appointments.duration',
                    'appointments.location',
                    'appointments.notes',
                    'appointments.status',
                    DB::raw("CASE
                        WHEN appointments.type = 'session' THEN '#3880ff'
                        WHEN appointments.type = 'check-in' THEN '#2dd36f'
                        WHEN appointments.type = 'consultation' THEN '#ffc409'
                        ELSE '#5856d6'
                    END as color")
                )
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'appointments' => $appointments
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch calendar dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get measurements for clients
     */
    public function getMeasurements(Request $request)
    {
        try {
            $coachId = Auth::id();

            $clientId = $request->query('client_id');

            if ($clientId) {
                $hasAccess = DB::table('coach_client_relationships')
                    ->where('coach_id', $coachId)
                    ->where('client_id', $clientId)
                    ->exists();

                if (!$hasAccess) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Access denied'
                    ], 403);
                }

                $measurements = DB::table('measurements')
                    ->where('user_id', $clientId)
                    ->orderBy('measured_at', 'desc')
                    ->limit(20)
                    ->get();
            } else {
                $clients = DB::table('coach_client_relationships')
                    ->where('coach_id', $coachId)
                    ->pluck('client_id');

                $measurements = DB::table('measurements')
                    ->join('users', 'measurements.user_id', '=', 'users.id')
                    ->whereIn('measurements.user_id', $clients)
                    ->orderBy('measurements.measured_at', 'desc')
                    ->select('measurements.*', DB::raw("CONCAT_WS(' ', users.first_name, users.last_name) as client_name"))
                    ->limit(50)
                    ->get();
            }

            return response()->json([
                'success' => true,
                'data' => $measurements
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch measurements',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get milestones for clients
     */
    public function getMilestones(Request $request)
    {
        try {
            $coachId = Auth::id();

            $clients = DB::table('coach_client_relationships')
                ->where('coach_id', $coachId)
                ->pluck('client_id');

            $milestones = DB::table('milestones')
                ->join('users', 'milestones.user_id', '=', 'users.id')
                ->whereIn('milestones.user_id', $clients)
                ->when($request->filled('category'), function ($query) use ($request) {
                    $query->where('milestones.category', $request->query('category'));
                })
                ->orderBy('milestones.achieved_at', 'desc')
                ->select(
                    'milestones.*',
                    DB::raw("CONCAT_WS(' ', users.first_name, users.last_name) as client_name"),
                    'users.profile_image as client_avatar'
                )
                ->limit(50)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $milestones
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch milestones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getClientsOnTrack($clients)
    {
        $onTrack = 0;
        foreach ($clients as $clientId) {
            $compliance = $this->getClientComplianceRate($clientId);
            if ($compliance >= 70) {
                $onTrack++;
            }
        }
        return $onTrack;
    }

    private function getAverageCompliance($clients)
    {
        if ($clients->isEmpty()) return 0;

        $totalCompliance = 0;
        foreach ($clients as $clientId) {
            $totalCompliance += $this->getClientComplianceRate($clientId);
        }

        return round($totalCompliance / $clients->count(), 2);
    }

    private function getMilestonesAchievedThisWeek($clients)
    {
        return DB::table('milestones')
            ->whereIn('user_id', $clients)
            ->whereBetween('achieved_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->count();
    }

    private function getClientComplianceRate($clientId)
    {
        $assigned = DB::table('workout_assignments')
            ->where('client_id', $clientId)
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->count();

        if ($assigned == 0) return 0;

        $completed = WorkoutSession::where('user_id', $clientId)
            ->where('status', 'completed')
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->count();

        return round(($completed / $assigned) * 100, 2);
    }

    private function getClientStreak($clientId)
    {
        $streak = 0;
        $currentDate = Carbon::now();

        for ($i = 0; $i < 30; $i++) {
            $hasWorkout = WorkoutSession::where('user_id', $clientId)
                ->whereDate('created_at', $currentDate)
                ->where('status', 'completed')
                ->exists();

            if ($hasWorkout) {
                $streak++;
                $currentDate->subDay();
            } else {
                break;
            }
        }

        return $streak;
    }

    private function getRecentAchievements($clients, $limit)
    {
        return DB::table('milestones')
            ->join('users', 'milestones.user_id', '=', 'users.id')
            ->whereIn('milestones.user_id', $clients)
            ->orderBy('milestones.achieved_at', 'desc')
            ->select('milestones.*', DB::raw("CONCAT_WS(' ', users.first_name, users.last_name) as client_name"), 'users.profile_image as client_avatar')
            ->limit($limit)
            ->get();
    }

    /**
     * Get comprehensive client analytics
     */
    public function getClientAnalytics(Request $request, $clientId)
    {
        try {
            $coachId = Auth::id();
            $dateRange = $request->query('date_range', '30'); // days

            $hasAccess = DB::table('coach_client_relationships')
                ->where('coach_id', $coachId)
                ->where('client_id', $clientId)
                ->exists();

            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this client'
                ], 403);
            }

            $client = User::find($clientId);
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found'
                ], 404);
            }

            $startDate = Carbon::now()->subDays((int)$dateRange);
            $endDate = Carbon::now();

            $workoutAnalytics = $this->getClientWorkoutAnalytics($clientId, $startDate, $endDate);

            $nutritionAnalytics = $this->getClientNutritionAnalytics($clientId, $startDate, $endDate);

            $bodyMeasurements = $this->getClientBodyMeasurements($clientId);

            $bodyPoints = $this->getClientBodyPoints($clientId);

            $progressionPhotos = DB::table('progression_photos')
                ->where('user_id', $clientId)
                ->orderBy('created_at', 'desc')
                ->select('id', 'url', 'created_at as date', 'weight', 'notes')
                ->get();

            $analytics = [
                'client' => [
                    'id' => $client->id,
                    'first_name' => $client->first_name,
                    'last_name' => $client->last_name,
                    'email' => $client->email,
                    'profile_image' => $client->profile_image
                ],
                'bodyPoints' => $bodyPoints,
                'progressionPhotos' => $progressionPhotos,
                'workoutAnalytics' => $workoutAnalytics,
                'nutritionAnalytics' => $nutritionAnalytics,
                'bodyMeasurements' => $bodyMeasurements
            ];

            return response()->json([
                'success' => true,
                'message' => 'Client analytics retrieved successfully',
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve client analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getClientWorkoutAnalytics($clientId, $startDate, $endDate)
    {
        $totalWorkouts = DB::table('user_workouts')
            ->where('user_id', $clientId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $completedWorkouts = DB::table('user_completed_workouts')
            ->where('user_id', $clientId)
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->count();

        $completionRate = $totalWorkouts > 0 ? round(($completedWorkouts / $totalWorkouts) * 100, 1) : 0;

        $weeklyAverage = round($completedWorkouts / (((int)$endDate->diffInDays($startDate)) / 7), 1);

        $currentStreak = $this->getClientStreak($clientId);

        $longestStreak = DB::table('user_stats')
            ->where('user_id', $clientId)
            ->value('longest_workout_streak') ?? 0;

        $totalDuration = DB::table('user_completed_workouts')
            ->where('user_id', $clientId)
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->sum('duration') ?? 0;

        $caloriesBurned = DB::table('user_completed_workouts')
            ->where('user_id', $clientId)
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->sum('calories_burned') ?? 0;

        return [
            'completionRate' => $completionRate,
            'totalWorkouts' => $totalWorkouts,
            'completedWorkouts' => $completedWorkouts,
            'weeklyAverage' => $weeklyAverage,
            'currentStreak' => $currentStreak,
            'longestStreak' => $longestStreak,
            'caloriesBurned' => round($caloriesBurned),
            'totalDuration' => round($totalDuration)
        ];
    }

    private function getClientNutritionAnalytics($clientId, $startDate, $endDate)
    {
        $nutritionLogs = DB::table('nutrition_logs')
            ->where('user_id', $clientId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $averageCalories = $nutritionLogs->avg('total_calories') ?? 0;
        $targetCalories = DB::table('user_nutrition_goals')
            ->where('user_id', $clientId)
            ->value('daily_calorie_target') ?? 2000;

        $complianceRate = $targetCalories > 0
            ? round(($averageCalories / $targetCalories) * 100, 1)
            : 0;

        $averageProtein = $nutritionLogs->avg('protein_g') ?? 0;
        $averageCarbs = $nutritionLogs->avg('carbs_g') ?? 0;
        $averageFat = $nutritionLogs->avg('fat_g') ?? 0;

        $mealsLogged = DB::table('meal_logs')
            ->where('user_id', $clientId)
            ->whereBetween('date', [$startDate, $endDate])
            ->count();

        return [
            'averageCalories' => round($averageCalories),
            'targetCalories' => $targetCalories,
            'complianceRate' => $complianceRate,
            'macroBreakdown' => [
                'protein' => round($averageProtein, 1),
                'carbs' => round($averageCarbs, 1),
                'fat' => round($averageFat, 1)
            ],
            'mealsLogged' => $mealsLogged
        ];
    }

    private function getClientBodyMeasurements($clientId)
    {
        $current = DB::table('body_measurements')
            ->where('user_id', $clientId)
            ->orderBy('created_at', 'desc')
            ->first();

        $history = DB::table('body_measurements')
            ->where('user_id', $clientId)
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->select('weight', 'body_fat', 'muscle_mass', 'created_at as date')
            ->get();

        return [
            'current' => $current,
            'history' => $history
        ];
    }

    private function getClientBodyPoints($clientId)
    {
        $total = DB::table('user_body_points')
            ->where('user_id', $clientId)
            ->sum('points') ?? 0;

        $history = DB::table('user_body_points')
            ->where('user_id', $clientId)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->select('points', 'reason', 'type', 'created_at as date')
            ->get();

        $rank = DB::table(DB::raw('(SELECT user_id, SUM(points) as total_points FROM user_body_points GROUP BY user_id) as rankings'))
            ->where('total_points', '>', $total)
            ->count() + 1;

        return [
            'total' => $total,
            'history' => $history,
            'rank' => $rank
        ];
    }

    /**
     * Upload progression photo for client
     */
    public function uploadProgressionPhoto(Request $request, $clientId)
    {
        try {
            $validated = $request->validate([
                'photo' => 'required|image|max:10240', // 10MB max
                'date' => 'nullable|date',
                'weight' => 'nullable|numeric',
                'notes' => 'nullable|string|max:500'
            ]);

            $coachId = Auth::id();

            $hasAccess = DB::table('coach_client_relationships')
                ->where('coach_id', $coachId)
                ->where('client_id', $clientId)
                ->exists();

            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this client'
                ], 403);
            }

            $file = $request->file('photo');
            $filename = time() . '_' . $clientId . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('progression_photos', $filename, 'public');
            $url = url('storage/' . $path);

            $photoId = DB::table('progression_photos')->insertGetId([
                'user_id' => $clientId,
                'uploaded_by' => $coachId,
                'url' => $url,
                'filename' => $filename,
                'weight' => $validated['weight'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_at' => $validated['date'] ?? now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Progression photo uploaded successfully',
                'photo' => [
                    'id' => $photoId,
                    'url' => $url,
                    'date' => $validated['date'] ?? now(),
                    'weight' => $validated['weight'] ?? null
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload progression photo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get progression photos for client
     */
    public function getProgressionPhotos($clientId)
    {
        try {
            $coachId = Auth::id();

            $hasAccess = DB::table('coach_client_relationships')
                ->where('coach_id', $coachId)
                ->where('client_id', $clientId)
                ->exists();

            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this client'
                ], 403);
            }

            $photos = DB::table('progression_photos')
                ->where('user_id', $clientId)
                ->orderBy('created_at', 'desc')
                ->select('id', 'url', 'created_at as date', 'weight', 'notes')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Progression photos retrieved successfully',
                'data' => $photos
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve progression photos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete progression photo
     */
    public function deleteProgressionPhoto($clientId, $photoId)
    {
        try {
            $coachId = Auth::id();

            $hasAccess = DB::table('coach_client_relationships')
                ->where('coach_id', $coachId)
                ->where('client_id', $clientId)
                ->exists();

            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this client'
                ], 403);
            }

            $photo = DB::table('progression_photos')
                ->where('id', $photoId)
                ->where('user_id', $clientId)
                ->first();

            if (!$photo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Photo not found'
                ], 404);
            }

            if ($photo->filename) {
                \Storage::disk('public')->delete('progression_photos/' . $photo->filename);
            }

            DB::table('progression_photos')->where('id', $photoId)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Progression photo deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete progression photo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send notification to clients
     */
    public function sendNotificationToClients(Request $request)
    {
        try {
            $validated = $request->validate([
                'client_ids' => 'required|array',
                'client_ids.*' => 'integer|exists:users,id',
                'type' => 'required|string',
                'title' => 'required|string|max:100',
                'message' => 'required|string|max:500',
                'priority' => 'nullable|string|in:low,normal,high,urgent',
                'schedule_type' => 'nullable|string|in:immediate,scheduled',
                'schedule_date' => 'nullable|date',
                'schedule_time' => 'nullable|date_format:H:i'
            ]);

            $coachId = Auth::id();

            foreach ($validated['client_ids'] as $clientId) {
                $hasAccess = DB::table('coach_client_relationships')
                    ->where('coach_id', $coachId)
                    ->where('client_id', $clientId)
                    ->exists();

                if (!$hasAccess) {
                    throw new \Exception("You don't have access to client ID: {$clientId}");
                }
            }

            $notifications = [];
            foreach ($validated['client_ids'] as $clientId) {
                $scheduledFor = null;
                if (isset($validated['schedule_type']) && $validated['schedule_type'] === 'scheduled') {
                    $scheduledFor = Carbon::parse($validated['schedule_date'] . ' ' . $validated['schedule_time']);
                }

                $notificationId = DB::table('app_notifications')->insertGetId([
                    'user_id' => $clientId,
                    'title' => $validated['title'],
                    'message' => $validated['message'],
                    'type' => $validated['type'],
                    'priority' => $validated['priority'] ?? 'normal',
                    'scheduled_for' => $scheduledFor,
                    'sent_by' => $coachId,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $notifications[] = $notificationId;

                if (!$scheduledFor) {
                    \App\Helpers\Helper::sendPush(
                        $validated['title'],
                        $validated['message'],
                        null,
                        null,
                        $validated['type'],
                        null,
                        [$clientId]
                    );
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification sent successfully to ' . count($validated['client_ids']) . ' clients',
                'notification_ids' => $notifications
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get fitness dashboard data
     */
    public function getFitnessDashboard(Request $request)
    {
        try {
            $coachId = Auth::id();

            $clients = DB::table('coach_client_relationships')
                ->where('coach_id', $coachId)
                ->where('status', 'active')
                ->pluck('client_id');

            $stats = [
                'total_workouts_created' => Workout::where('uploaded_by', $coachId)->count(),
                'total_plans_created' => Plan::where('uploaded_by', $coachId)->count(),
                'workouts_completed_this_week' => UserCompletedWorkout::whereIn('user_id', $clients)
                    ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                    ->count(),
                'average_client_adherence' => $this->getAverageCompliance($clients),
                'total_video_library_items' => DB::table('videos')->where('uploaded_by', $coachId)->count()
            ];

            $recentWorkouts = DB::table('user_completed_workouts')
                ->join('users', 'user_completed_workouts.user_id', '=', 'users.id')
                ->whereIn('user_completed_workouts.user_id', $clients)
                ->orderBy('user_completed_workouts.created_at', 'desc')
                ->limit(10)
                ->select(
                    'user_completed_workouts.*',
                    DB::raw("CONCAT_WS(' ', users.first_name, users.last_name) as client_name"),
                    'users.profile_image as client_avatar'
                )
                ->get();

            $popularWorkouts = Workout::where('uploaded_by', $coachId)
                ->limit(5)
                ->select('id', 'title')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'recent_workouts' => $recentWorkouts,
                    'popular_workouts' => $popularWorkouts
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch fitness dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get nutrition dashboard data
     */
    public function getNutritionDashboard(Request $request)
    {
        try {
            $coachId = Auth::id();

            $clients = DB::table('coach_client_relationships')
                ->where('coach_id', $coachId)
                ->where('status', 'active')
                ->pluck('client_id');

            $mealsThisWeek = DB::table('nutrition_logs')
                ->whereIn('user_id', $clients)
                ->whereBetween('logged_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                ->count();

            $totalMeals = DB::table('nutrition_logs')
                ->whereIn('user_id', $clients)
                ->count();

            $totalMealPlans = DB::table('meal_plan_templates')
                ->where('coach_id', $coachId)
                ->count();

            $activeMealPlans = DB::table('meal_plan_assignments')
                ->where('coach_id', $coachId)
                ->where('status', 'active')
                ->count();

            $stats = [
                'total_meal_plans_created' => $totalMealPlans,
                'active_nutrition_plans' => $activeMealPlans,
                'meals_logged_this_week' => $mealsThisWeek,
                'total_meals_logged' => $totalMeals,
                'average_nutrition_compliance' => $totalMeals > 0 ? round(($mealsThisWeek / $clients->count()) / 7 * 100, 1) : 0
            ];

            $recentLogs = DB::table('nutrition_logs')
                ->join('users', 'nutrition_logs.user_id', '=', 'users.id')
                ->whereIn('nutrition_logs.user_id', $clients)
                ->orderBy('nutrition_logs.logged_at', 'desc')
                ->limit(10)
                ->select(
                    'nutrition_logs.*',
                    DB::raw("CONCAT_WS(' ', users.first_name, users.last_name) as client_name"),
                    'users.profile_image as client_avatar'
                )
                ->get();

            $popularMealPlans = DB::table('meal_plan_templates')
                ->where('coach_id', $coachId)
                ->orderBy('usage_count', 'desc')
                ->limit(5)
                ->select('id', 'name', 'goal_type', 'usage_count')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'recent_logs' => $recentLogs,
                    'popular_meal_plans' => $popularMealPlans
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch nutrition dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get CBT (Cognitive Behavioral Therapy) dashboard data
     */
    public function getCBTDashboard(Request $request)
    {
        try {
            $coachId = Auth::id();

            $clients = DB::table('coach_client_relationships')
                ->where('coach_id', $coachId)
                ->where('status', 'active')
                ->pluck('client_id');

            $totalSessions = DB::table('cbt_sessions')
                ->where('coach_id', $coachId)
                ->count();

            $activePlans = DB::table('cbt_plans')
                ->where('coach_id', $coachId)
                ->where('is_public', false)
                ->count();

            $completedExercises = DB::table('cbt_exercises')
                ->whereIn('user_id', $clients)
                ->where('status', 'completed')
                ->whereBetween('completed_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                ->count();

            $stats = [
                'total_cbt_sessions' => $totalSessions,
                'active_cbt_plans' => $activePlans,
                'completed_exercises_this_week' => $completedExercises,
                'average_engagement_score' => $completedExercises > 0 ? round(($completedExercises / $clients->count()) * 10, 1) : 0
            ];

            $recentActivities = DB::table('cbt_sessions')
                ->join('users', 'cbt_sessions.user_id', '=', 'users.id')
                ->where('cbt_sessions.coach_id', $coachId)
                ->orderBy('cbt_sessions.session_date', 'desc')
                ->limit(10)
                ->select(
                    'cbt_sessions.*',
                    DB::raw("CONCAT_WS(' ', users.first_name, users.last_name) as client_name"),
                    'users.profile_image as client_avatar'
                )
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'recent_activities' => $recentActivities
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch CBT dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notifications dashboard data
     */
    public function getNotificationsDashboard(Request $request)
    {
        try {
            $coachId = Auth::id();

            $clients = DB::table('coach_client_relationships')
                ->where('coach_id', $coachId)
                ->where('status', 'active')
                ->pluck('client_id');

            $totalSent = DB::table('app_notifications')
                ->whereIn('user_id', $clients)
                ->count();

            $scheduled = DB::table('app_notifications')
                ->whereIn('user_id', $clients)
                ->whereNotNull('scheduled_for')
                ->where('scheduled_for', '>', now())
                ->count();

            $totalReads = DB::table('app_notification_reads')
                ->join('app_notifications', 'app_notification_reads.notification_id', '=', 'app_notifications.id')
                ->whereIn('app_notifications.user_id', $clients)
                ->count();

            $readRate = $totalSent > 0 ? round(($totalReads / $totalSent) * 100, 1) : 0;

            $stats = [
                'total_sent' => $totalSent,
                'sent_this_week' => DB::table('app_notifications')
                    ->whereIn('user_id', $clients)
                    ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                    ->count(),
                'scheduled' => $scheduled,
                'read_rate' => $readRate
            ];

            $recentNotifications = DB::table('app_notifications')
                ->join('users', 'app_notifications.user_id', '=', 'users.id')
                ->whereIn('app_notifications.user_id', $clients)
                ->orderBy('app_notifications.created_at', 'desc')
                ->limit(20)
                ->select(
                    'app_notifications.*',
                    DB::raw("CONCAT_WS(' ', users.first_name, users.last_name) as recipient_name"),
                    'users.profile_image as recipient_avatar'
                )
                ->get();

            $scheduledNotifications = DB::table('app_notifications')
                ->join('users', 'app_notifications.user_id', '=', 'users.id')
                ->whereIn('app_notifications.user_id', $clients)
                ->whereNotNull('app_notifications.scheduled_for')
                ->where('app_notifications.scheduled_for', '>', now())
                ->orderBy('app_notifications.scheduled_for', 'asc')
                ->limit(10)
                ->select(
                    'app_notifications.*',
                    DB::raw("CONCAT_WS(' ', users.first_name, users.last_name) as recipient_name"),
                    'users.profile_image as recipient_avatar'
                )
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'recent_notifications' => $recentNotifications,
                    'scheduled_notifications' => $scheduledNotifications
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getAverageNutritionCompliance($clients)
    {
        if ($clients->isEmpty()) return 0;

        $totalCompliance = 0;
        $count = 0;

        foreach ($clients as $clientId) {
            $targetCalories = DB::table('user_nutrition_goals')
                ->where('user_id', $clientId)
                ->value('daily_calorie_target');

            if ($targetCalories) {
                $avgCalories = DB::table('nutrition_logs')
                    ->where('user_id', $clientId)
                    ->where('date', '>=', Carbon::now()->subDays(30))
                    ->avg('total_calories') ?? 0;

                if ($targetCalories > 0) {
                    $compliance = ($avgCalories / $targetCalories) * 100;
                    $totalCompliance += min($compliance, 100); // Cap at 100%
                    $count++;
                }
            }
        }

        return $count > 0 ? round($totalCompliance / $count, 2) : 0;
    }

    private function getNotificationReadRate($coachId)
    {
        $total = DB::table('app_notifications')
            ->where('sent_by', $coachId)
            ->whereNotNull('sent_at')
            ->count();

        if ($total == 0) return 0;

        $read = DB::table('app_notifications')
            ->where('sent_by', $coachId)
            ->whereNotNull('sent_at')
            ->whereNotNull('read_at')
            ->count();

        return round(($read / $total) * 100, 2);
    }

    /**
     * Workout Plan Management Methods
     */

    public function createWorkoutPlan(Request $request)
    {
        try {
            $coachId = Auth::guard('admin')->check() ? Auth::guard('admin')->id() : Auth::id();

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'clientId' => 'nullable|integer',
                'difficulty' => 'required|string',
                'target' => 'nullable|string',
                'duration' => 'required|integer',
                'frequency' => 'required|integer',
                'exercises' => 'required|array',
                'exercises.*.exerciseId' => 'required|integer',
                'exercises.*.sets' => 'required|integer|min:1',
                'exercises.*.reps' => 'required|integer|min:1',
                'exercises.*.weight' => 'nullable|numeric',
                'exercises.*.duration' => 'nullable|integer',
                'exercises.*.restTime' => 'required|integer|min:0',
                'exercises.*.notes' => 'nullable|string',
                'exercises.*.order' => 'required|integer'
            ]);

            $plan = Plan::create([
                'title' => $validated['name'],
                'uploaded_by' => $coachId,
                'type' => $validated['difficulty'],
                'phase' => $validated['target'] ?? 'general',
                'week' => $validated['frequency']
            ]);

            foreach ($validated['exercises'] as $exercise) {
                DB::table('plan_workouts')->insert([
                    'plan_id' => $plan->id,
                    'workout_id' => $exercise['exerciseId'],
                    'sets' => $exercise['sets'],
                    'reps' => $exercise['reps'],
                    'weight' => $exercise['weight'] ?? null,
                    'rest_time' => $exercise['restTime'],
                    'notes' => $exercise['notes'] ?? null,
                    'order' => $exercise['order'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            if (isset($validated['clientId'])) {
                DB::table('assign_plans')->insert([
                    'user_id' => $validated['clientId'],
                    'plan_id' => $plan->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Workout plan created successfully',
                'plan' => $plan
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create workout plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getWorkoutPlan(Request $request, $id)
    {
        try {
            $coachId = Auth::guard('admin')->check() ? Auth::guard('admin')->id() : Auth::id();

            $plan = Plan::where('id', $id)
                ->where('uploaded_by', $coachId)
                ->first();

            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workout plan not found'
                ], 404);
            }

            $exercises = DB::table('plan_workouts')
                ->join('workouts', 'plan_workouts.workout_id', '=', 'workouts.id')
                ->where('plan_workouts.plan_id', $id)
                ->select(
                    'plan_workouts.*',
                    'workouts.title as name',
                    'workouts.id as exerciseId'
                )
                ->orderBy('plan_workouts.order')
                ->get();

            return response()->json([
                'success' => true,
                'id' => $plan->id,
                'name' => $plan->title,
                'description' => '',
                'difficulty' => $plan->type,
                'target' => $plan->phase,
                'duration' => 60,
                'frequency' => $plan->week,
                'exercises' => $exercises
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch workout plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateWorkoutPlan(Request $request, $id)
    {
        try {
            $coachId = Auth::guard('admin')->check() ? Auth::guard('admin')->id() : Auth::id();

            $plan = Plan::where('id', $id)
                ->where('uploaded_by', $coachId)
                ->first();

            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workout plan not found'
                ], 404);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'difficulty' => 'required|string',
                'target' => 'nullable|string',
                'duration' => 'required|integer',
                'frequency' => 'required|integer',
                'exercises' => 'required|array'
            ]);

            $plan->update([
                'title' => $validated['name'],
                'type' => $validated['difficulty'],
                'phase' => $validated['target'] ?? 'general',
                'week' => $validated['frequency']
            ]);

            DB::table('plan_workouts')->where('plan_id', $id)->delete();

            foreach ($validated['exercises'] as $exercise) {
                DB::table('plan_workouts')->insert([
                    'plan_id' => $plan->id,
                    'workout_id' => $exercise['exerciseId'],
                    'sets' => $exercise['sets'],
                    'reps' => $exercise['reps'],
                    'weight' => $exercise['weight'] ?? null,
                    'rest_time' => $exercise['restTime'],
                    'notes' => $exercise['notes'] ?? null,
                    'order' => $exercise['order'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Workout plan updated successfully',
                'plan' => $plan
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update workout plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteWorkoutPlan(Request $request, $id)
    {
        try {
            $coachId = Auth::guard('admin')->check() ? Auth::guard('admin')->id() : Auth::id();

            $plan = Plan::where('id', $id)
                ->where('uploaded_by', $coachId)
                ->first();

            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workout plan not found'
                ], 404);
            }

            DB::table('plan_workouts')->where('plan_id', $id)->delete();
            DB::table('assign_plans')->where('plan_id', $id)->delete();

            $plan->delete();

            return response()->json([
                'success' => true,
                'message' => 'Workout plan deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete workout plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getClientWorkoutPlans(Request $request, $clientId)
    {
        try {
            $coachId = Auth::guard('admin')->check() ? Auth::guard('admin')->id() : Auth::id();

            $hasAccess = DB::table('coach_client_relationships')
                ->where('coach_id', $coachId)
                ->where('client_id', $clientId)
                ->exists();

            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied'
                ], 403);
            }

            $plans = Plan::join('assign_plans', 'plans.id', '=', 'assign_plans.plan_id')
                ->where('assign_plans.user_id', $clientId)
                ->where('plans.uploaded_by', $coachId)
                ->select('plans.*')
                ->get();

            return response()->json([
                'success' => true,
                'plans' => $plans
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch client workout plans',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dashboard layout configuration for authenticated coach
     */
    public function getLayoutConfig(Request $request)
    {
        try {
            $coach = Auth::guard('admin')->user() ?? Auth::user();
            if (!$coach) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $dashboardType = $request->get('type', 'main');

            $layout = DB::table('coach_dashboard_layouts')
                ->where('coach_id', $coach->id)
                ->where('dashboard_type', $dashboardType)
                ->first();

            if (!$layout) {
                // Return default layout if not found
                return response()->json([
                    'success' => true,
                    'layout' => $this->getDefaultLayout($dashboardType),
                    'is_default' => true
                ]);
            }

            return response()->json([
                'success' => true,
                'layout' => json_decode($layout->widgets, true),
                'is_default' => false
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch layout',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save dashboard layout configuration
     */
    public function saveLayoutConfig(Request $request)
    {
        try {
            $coach = Auth::guard('admin')->user() ?? Auth::user();
            if (!$coach) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $validated = $request->validate([
                'widgets' => 'required|array',
                'type' => 'string|nullable'
            ]);

            $dashboardType = $validated['type'] ?? 'main';

            DB::table('coach_dashboard_layouts')->updateOrInsert(
                [
                    'coach_id' => $coach->id,
                    'dashboard_type' => $dashboardType
                ],
                [
                    'widgets' => json_encode($validated['widgets']),
                    'updated_at' => now()
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Layout saved successfully',
                'layout' => $validated['widgets']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save layout',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset dashboard layout to default
     */
    public function resetLayoutConfig(Request $request)
    {
        try {
            $coach = Auth::guard('admin')->user() ?? Auth::user();
            if (!$coach) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $dashboardType = $request->get('type', 'main');

            DB::table('coach_dashboard_layouts')
                ->where('coach_id', $coach->id)
                ->where('dashboard_type', $dashboardType)
                ->delete();

            $defaultLayout = $this->getDefaultLayout($dashboardType);

            return response()->json([
                'success' => true,
                'message' => 'Layout reset to default',
                'layout' => $defaultLayout
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset layout',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get default layout configuration
     */
    private function getDefaultLayout($type = 'main')
    {
        return [
            'widgets' => [
                ['id' => 'stats', 'row' => 0, 'col' => 0, 'sizex' => 2, 'sizey' => 1, 'visible' => true],
                ['id' => 'activity', 'row' => 0, 'col' => 2, 'sizex' => 1, 'sizey' => 2, 'visible' => true],
                ['id' => 'nutrition', 'row' => 1, 'col' => 0, 'sizex' => 2, 'sizey' => 1, 'visible' => true],
                ['id' => 'workouts', 'row' => 2, 'col' => 0, 'sizex' => 1, 'sizey' => 1, 'visible' => true],
                ['id' => 'sessions', 'row' => 2, 'col' => 1, 'sizex' => 1, 'sizey' => 1, 'visible' => true],
                ['id' => 'charts', 'row' => 2, 'col' => 2, 'sizex' => 1, 'sizey' => 1, 'visible' => true]
            ]
        ];
    }

    /**
     * Get coach dashboard clients tab
     */
    public function getCoachDashboardClients(Request $request)
    {
        // Reuse existing getClients method
        if (method_exists($this, 'getClients')) {
            return $this->getClients($request);
        }

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Coach Dashboard Clients Retrieved Successfully',
            'data' => []
        ]);
    }

    /**
     * Get coach dashboard calendar tab
     */
    public function getCoachDashboardCalendar(Request $request)
    {
        // Reuse existing getCalendarDashboard method
        if (method_exists($this, 'getCalendarDashboard')) {
            return $this->getCalendarDashboard($request);
        }

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Coach Dashboard Calendar Retrieved Successfully',
            'data' => [
                'events' => [],
                'appointments' => []
            ]
        ]);
    }

    /**
     * Get coach dashboard analytics tab
     */
    public function getCoachDashboardAnalytics(Request $request)
    {
        // Reuse existing getAnalytics method
        if (method_exists($this, 'getAnalytics')) {
            return $this->getAnalytics($request);
        }

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Coach Dashboard Analytics Retrieved Successfully',
            'data' => [
                'total_clients' => 0,
                'active_clients' => 0,
                'completed_sessions' => 0,
                'revenue' => 0
            ]
        ]);
    }

    /**
     * Get coach dashboard earnings tab
     */
    public function getCoachDashboardEarnings(Request $request)
    {
        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Coach Dashboard Earnings Retrieved Successfully',
            'data' => [
                'total_earnings' => 0,
                'monthly_earnings' => 0,
                'pending_payments' => 0,
                'payment_history' => []
            ]
        ]);
    }
}