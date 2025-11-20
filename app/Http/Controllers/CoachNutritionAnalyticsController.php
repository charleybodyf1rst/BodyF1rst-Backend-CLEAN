<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\NutritionLog;
use App\Models\NutritionPlan;
use App\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Coach Nutrition Analytics Controller
 * Handles nutrition analytics for coaches to track client nutrition compliance and progress
 */
class CoachNutritionAnalyticsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Get Nutrition Analytics Overview
     * GET /api/coaches/analytics/nutrition/overview
     */
    public function getOverview(Request $request)
    {
        try {
            $coachId = Auth::id();
            $clientId = $request->input('client_id');
            $period = $request->input('period', 'week'); // week, month, quarter

            [$startDate, $endDate] = $this->getDateRange($period);

            // Get all clients or specific client
            if ($clientId) {
                $clients = [$clientId];
            } else {
                $clients = $this->getCoachClients($coachId);
            }

            // Overall nutrition compliance
            $compliance = $this->calculateNutritionCompliance($clients, $startDate, $endDate);

            // Macro adherence across all clients
            $macroAdherence = $this->calculateMacroAdherence($clients, $startDate, $endDate);

            // Top performing clients (nutrition-wise)
            $topClients = $this->getTopNutritionClients($clients, $startDate, $endDate, 5);

            // Clients needing attention
            $attentionNeeded = $this->getClientsNeedingAttention($clients, $startDate, $endDate, 5);

            // Nutrition trends
            $trends = $this->getNutritionTrends($clients, $startDate, $endDate);

            // Meal plan completion rate
            $planCompletion = $this->getMealPlanCompletionRate($clients, $startDate, $endDate);

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => $period,
                    'dateRange' => [
                        'start' => $startDate->toDateString(),
                        'end' => $endDate->toDateString(),
                    ],
                    'totalClients' => count($clients),
                    'compliance' => $compliance,
                    'macroAdherence' => $macroAdherence,
                    'planCompletion' => $planCompletion,
                    'topClients' => $topClients,
                    'attentionNeeded' => $attentionNeeded,
                    'trends' => $trends,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load nutrition analytics overview',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get Compliance Rate Analytics
     * GET /api/coaches/analytics/nutrition/compliance
     */
    public function getComplianceAnalytics(Request $request)
    {
        try {
            $coachId = Auth::id();
            $clientId = $request->input('client_id');
            $startDate = $request->input('start_date', now()->subDays(30));
            $endDate = $request->input('end_date', now());

            if ($clientId) {
                // Single client compliance
                $compliance = $this->getClientComplianceDetail($clientId, $startDate, $endDate);
            } else {
                // All clients compliance
                $clients = $this->getCoachClients($coachId);
                $compliance = $this->getAllClientsCompliance($clients, $startDate, $endDate);
            }

            return response()->json([
                'success' => true,
                'data' => $compliance,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load compliance analytics',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get Macro Distribution Analytics
     * GET /api/coaches/analytics/nutrition/macros
     */
    public function getMacroAnalytics(Request $request)
    {
        try {
            $coachId = Auth::id();
            $clientId = $request->input('client_id');
            $startDate = $request->input('start_date', now()->subDays(30));
            $endDate = $request->input('end_date', now());

            if ($clientId) {
                // Single client macro analysis
                $macros = $this->getClientMacroDetail($clientId, $startDate, $endDate);
            } else {
                // All clients macro distribution
                $clients = $this->getCoachClients($coachId);
                $macros = $this->getAllClientsMacros($clients, $startDate, $endDate);
            }

            return response()->json([
                'success' => true,
                'data' => $macros,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load macro analytics',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // Helper Methods

    protected function getCoachClients($coachId)
    {
        return User::where('coach_id', $coachId)
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();
    }

    protected function getDateRange($period)
    {
        return match($period) {
            'week' => [now()->startOfWeek(), now()],
            'month' => [now()->startOfMonth(), now()],
            'quarter' => [now()->startOfQuarter(), now()],
            'year' => [now()->startOfYear(), now()],
            default => [now()->startOfWeek(), now()],
        };
    }

    protected function calculateNutritionCompliance($clients, $startDate, $endDate)
    {
        $totalDays = $startDate->diffInDays($endDate);
        $expectedLogs = count($clients) * $totalDays * 3; // 3 meals per day

        $actualLogs = NutritionLog::whereIn('user_id', $clients)
            ->whereBetween('logged_at', [$startDate, $endDate])
            ->count();

        $complianceRate = $expectedLogs > 0 ? ($actualLogs / $expectedLogs) * 100 : 0;

        // Get calorie goal compliance
        $logsWithGoals = NutritionLog::whereIn('user_id', $clients)
            ->whereBetween('logged_at', [$startDate, $endDate])
            ->join('users', 'nutrition_logs.user_id', '=', 'users.id')
            ->select('nutrition_logs.*', 'users.calorie_goal')
            ->get();

        $withinGoal = $logsWithGoals->filter(function ($log) {
            if (!$log->calorie_goal) return false;
            $diff = abs($log->calories - $log->calorie_goal);
            return $diff <= ($log->calorie_goal * 0.15); // Within 15% of goal
        })->count();

        $calorieCompliance = $logsWithGoals->count() > 0
            ? ($withinGoal / $logsWithGoals->count()) * 100
            : 0;

        return [
            'overall' => round($complianceRate, 1),
            'calorieGoal' => round($calorieCompliance, 1),
            'expectedLogs' => $expectedLogs,
            'actualLogs' => $actualLogs,
            'missedLogs' => $expectedLogs - $actualLogs,
        ];
    }

    protected function calculateMacroAdherence($clients, $startDate, $endDate)
    {
        $logs = NutritionLog::whereIn('user_id', $clients)
            ->whereBetween('logged_at', [$startDate, $endDate])
            ->join('users', 'nutrition_logs.user_id', '=', 'users.id')
            ->select(
                'nutrition_logs.*',
                'users.protein_goal',
                'users.carbs_goal',
                'users.fat_goal'
            )
            ->get();

        if ($logs->isEmpty()) {
            return [
                'protein' => 0,
                'carbs' => 0,
                'fat' => 0,
                'overall' => 0,
            ];
        }

        $proteinCompliant = $logs->filter(function ($log) {
            if (!$log->protein_goal) return false;
            $diff = abs($log->protein_g - $log->protein_goal);
            return $diff <= ($log->protein_goal * 0.15);
        })->count();

        $carbsCompliant = $logs->filter(function ($log) {
            if (!$log->carbs_goal) return false;
            $diff = abs($log->carbs_g - $log->carbs_goal);
            return $diff <= ($log->carbs_goal * 0.15);
        })->count();

        $fatCompliant = $logs->filter(function ($log) {
            if (!$log->fat_goal) return false;
            $diff = abs($log->fat_g - $log->fat_goal);
            return $diff <= ($log->fat_goal * 0.15);
        })->count();

        $total = $logs->count();

        return [
            'protein' => round(($proteinCompliant / $total) * 100, 1),
            'carbs' => round(($carbsCompliant / $total) * 100, 1),
            'fat' => round(($fatCompliant / $total) * 100, 1),
            'overall' => round((($proteinCompliant + $carbsCompliant + $fatCompliant) / ($total * 3)) * 100, 1),
        ];
    }

    protected function getTopNutritionClients($clients, $startDate, $endDate, $limit = 5)
    {
        $clientStats = [];

        foreach ($clients as $clientId) {
            $compliance = $this->getClientComplianceScore($clientId, $startDate, $endDate);
            $user = User::find($clientId);

            if ($user) {
                $clientStats[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'complianceScore' => $compliance,
                    'avatar' => $user->profile_photo_url ?? null,
                ];
            }
        }

        // Sort by compliance score
        usort($clientStats, function ($a, $b) {
            return $b['complianceScore'] <=> $a['complianceScore'];
        });

        return array_slice($clientStats, 0, $limit);
    }

    protected function getClientsNeedingAttention($clients, $startDate, $endDate, $limit = 5)
    {
        $clientStats = [];

        foreach ($clients as $clientId) {
            $compliance = $this->getClientComplianceScore($clientId, $startDate, $endDate);
            $user = User::find($clientId);

            if ($user && $compliance < 50) { // Only clients with <50% compliance
                $clientStats[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'complianceScore' => $compliance,
                    'avatar' => $user->profile_photo_url ?? null,
                    'issueType' => $this->identifyNutritionIssue($clientId, $startDate, $endDate),
                ];
            }
        }

        // Sort by compliance score (lowest first)
        usort($clientStats, function ($a, $b) {
            return $a['complianceScore'] <=> $b['complianceScore'];
        });

        return array_slice($clientStats, 0, $limit);
    }

    protected function getClientComplianceScore($clientId, $startDate, $endDate)
    {
        $totalDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
        $expectedLogs = $totalDays * 3; // 3 meals per day

        $actualLogs = NutritionLog::where('user_id', $clientId)
            ->whereBetween('logged_at', [$startDate, $endDate])
            ->count();

        return $expectedLogs > 0 ? round(($actualLogs / $expectedLogs) * 100, 1) : 0;
    }

    protected function identifyNutritionIssue($clientId, $startDate, $endDate)
    {
        $logs = NutritionLog::where('user_id', $clientId)
            ->whereBetween('logged_at', [$startDate, $endDate])
            ->get();

        if ($logs->count() < 3) {
            return 'not_logging';
        }

        $user = User::find($clientId);
        if (!$user->calorie_goal) {
            return 'no_goals_set';
        }

        $avgCalories = $logs->avg('calories');
        if ($avgCalories < ($user->calorie_goal * 0.7)) {
            return 'under_eating';
        }

        if ($avgCalories > ($user->calorie_goal * 1.3)) {
            return 'over_eating';
        }

        return 'macro_imbalance';
    }

    protected function getNutritionTrends($clients, $startDate, $endDate)
    {
        $dailyData = NutritionLog::whereIn('user_id', $clients)
            ->whereBetween('logged_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(logged_at) as date'),
                DB::raw('AVG(calories) as avg_calories'),
                DB::raw('AVG(protein_g) as avg_protein'),
                DB::raw('AVG(carbs_g) as avg_carbs'),
                DB::raw('AVG(fat_g) as avg_fat'),
                DB::raw('COUNT(*) as log_count')
            )
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        return [
            'calories' => $dailyData->map(fn($d) => ['date' => $d->date, 'value' => round($d->avg_calories, 1)]),
            'protein' => $dailyData->map(fn($d) => ['date' => $d->date, 'value' => round($d->avg_protein, 1)]),
            'carbs' => $dailyData->map(fn($d) => ['date' => $d->date, 'value' => round($d->avg_carbs, 1)]),
            'fat' => $dailyData->map(fn($d) => ['date' => $d->date, 'value' => round($d->avg_fat, 1)]),
            'logFrequency' => $dailyData->map(fn($d) => ['date' => $d->date, 'value' => $d->log_count]),
        ];
    }

    protected function getMealPlanCompletionRate($clients, $startDate, $endDate)
    {
        $assignments = Assignment::whereIn('user_id', $clients)
            ->where('type', 'nutrition_plan')
            ->whereBetween('assigned_at', [$startDate, $endDate])
            ->get();

        if ($assignments->isEmpty()) {
            return [
                'rate' => 0,
                'totalAssigned' => 0,
                'completed' => 0,
                'inProgress' => 0,
                'notStarted' => 0,
            ];
        }

        $completed = $assignments->where('completion_status', 'completed')->count();
        $inProgress = $assignments->where('completion_status', 'in_progress')->count();
        $notStarted = $assignments->where('completion_status', 'not_started')->count();

        return [
            'rate' => round(($completed / $assignments->count()) * 100, 1),
            'totalAssigned' => $assignments->count(),
            'completed' => $completed,
            'inProgress' => $inProgress,
            'notStarted' => $notStarted,
        ];
    }

    protected function getClientComplianceDetail($clientId, $startDate, $endDate)
    {
        $user = User::find($clientId);
        $logs = NutritionLog::where('user_id', $clientId)
            ->whereBetween('logged_at', [$startDate, $endDate])
            ->orderBy('logged_at', 'desc')
            ->get();

        $totalDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
        $expectedLogs = $totalDays * 3;

        $dailyCompliance = [];
        $currentDate = Carbon::parse($startDate);

        while ($currentDate <= Carbon::parse($endDate)) {
            $dayLogs = $logs->filter(function ($log) use ($currentDate) {
                return Carbon::parse($log->logged_at)->isSameDay($currentDate);
            });

            $dailyCompliance[] = [
                'date' => $currentDate->toDateString(),
                'logsCount' => $dayLogs->count(),
                'totalCalories' => $dayLogs->sum('calories'),
                'calorieGoal' => $user->calorie_goal ?? 0,
                'compliant' => $this->isDayCompliant($dayLogs, $user),
            ];

            $currentDate->addDay();
        }

        return [
            'client' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'summary' => [
                'totalLogs' => $logs->count(),
                'expectedLogs' => $expectedLogs,
                'complianceRate' => round(($logs->count() / $expectedLogs) * 100, 1),
                'averageDailyCalories' => round($logs->avg('calories'), 1),
                'calorieGoal' => $user->calorie_goal ?? 0,
            ],
            'dailyCompliance' => $dailyCompliance,
            'weeklyTrends' => $this->getWeeklyTrends($logs),
        ];
    }

    protected function getAllClientsCompliance($clients, $startDate, $endDate)
    {
        $complianceData = [];

        foreach ($clients as $clientId) {
            $user = User::find($clientId);
            if (!$user) continue;

            $compliance = $this->getClientComplianceScore($clientId, $startDate, $endDate);

            $complianceData[] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'complianceRate' => $compliance,
                'status' => $this->getComplianceStatus($compliance),
                'avatar' => $user->profile_photo_url ?? null,
            ];
        }

        // Sort by compliance rate
        usort($complianceData, function ($a, $b) {
            return $b['complianceRate'] <=> $a['complianceRate'];
        });

        return [
            'clients' => $complianceData,
            'summary' => [
                'averageCompliance' => round(collect($complianceData)->avg('complianceRate'), 1),
                'highCompliance' => collect($complianceData)->where('status', 'high')->count(),
                'mediumCompliance' => collect($complianceData)->where('status', 'medium')->count(),
                'lowCompliance' => collect($complianceData)->where('status', 'low')->count(),
            ],
        ];
    }

    protected function getComplianceStatus($rate)
    {
        if ($rate >= 80) return 'high';
        if ($rate >= 50) return 'medium';
        return 'low';
    }

    protected function isDayCompliant($dayLogs, $user)
    {
        if ($dayLogs->count() < 2) return false;

        if ($user->calorie_goal) {
            $totalCalories = $dayLogs->sum('calories');
            $diff = abs($totalCalories - $user->calorie_goal);
            return $diff <= ($user->calorie_goal * 0.15);
        }

        return true;
    }

    protected function getWeeklyTrends($logs)
    {
        $weeklyData = $logs->groupBy(function ($log) {
            return Carbon::parse($log->logged_at)->startOfWeek()->toDateString();
        })->map(function ($weekLogs, $weekStart) {
            return [
                'weekStart' => $weekStart,
                'logsCount' => $weekLogs->count(),
                'averageCalories' => round($weekLogs->avg('calories'), 1),
                'averageProtein' => round($weekLogs->avg('protein_g'), 1),
                'averageCarbs' => round($weekLogs->avg('carbs_g'), 1),
                'averageFat' => round($weekLogs->avg('fat_g'), 1),
            ];
        })->values();

        return $weeklyData;
    }

    protected function getClientMacroDetail($clientId, $startDate, $endDate)
    {
        $user = User::find($clientId);
        $logs = NutritionLog::where('user_id', $clientId)
            ->whereBetween('logged_at', [$startDate, $endDate])
            ->get();

        if ($logs->isEmpty()) {
            return [
                'client' => [
                    'id' => $user->id,
                    'name' => $user->name,
                ],
                'message' => 'No nutrition logs found for this period',
            ];
        }

        return [
            'client' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'macroSummary' => [
                'protein' => [
                    'average' => round($logs->avg('protein_g'), 1),
                    'goal' => $user->protein_goal ?? 0,
                    'min' => round($logs->min('protein_g'), 1),
                    'max' => round($logs->max('protein_g'), 1),
                    'adherenceRate' => $this->calculateMacroAdherenceRate($logs, 'protein_g', $user->protein_goal),
                ],
                'carbs' => [
                    'average' => round($logs->avg('carbs_g'), 1),
                    'goal' => $user->carbs_goal ?? 0,
                    'min' => round($logs->min('carbs_g'), 1),
                    'max' => round($logs->max('carbs_g'), 1),
                    'adherenceRate' => $this->calculateMacroAdherenceRate($logs, 'carbs_g', $user->carbs_goal),
                ],
                'fat' => [
                    'average' => round($logs->avg('fat_g'), 1),
                    'goal' => $user->fat_goal ?? 0,
                    'min' => round($logs->min('fat_g'), 1),
                    'max' => round($logs->max('fat_g'), 1),
                    'adherenceRate' => $this->calculateMacroAdherenceRate($logs, 'fat_g', $user->fat_goal),
                ],
            ],
            'dailyMacroTrends' => $this->getDailyMacroTrends($logs),
            'macroDistribution' => $this->getMacroDistribution($logs),
        ];
    }

    protected function getAllClientsMacros($clients, $startDate, $endDate)
    {
        $macroData = [];

        foreach ($clients as $clientId) {
            $user = User::find($clientId);
            if (!$user) continue;

            $logs = NutritionLog::where('user_id', $clientId)
                ->whereBetween('logged_at', [$startDate, $endDate])
                ->get();

            if ($logs->isEmpty()) continue;

            $macroData[] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'macros' => [
                    'protein' => [
                        'average' => round($logs->avg('protein_g'), 1),
                        'goal' => $user->protein_goal ?? 0,
                        'adherence' => $this->calculateMacroAdherenceRate($logs, 'protein_g', $user->protein_goal),
                    ],
                    'carbs' => [
                        'average' => round($logs->avg('carbs_g'), 1),
                        'goal' => $user->carbs_goal ?? 0,
                        'adherence' => $this->calculateMacroAdherenceRate($logs, 'carbs_g', $user->carbs_goal),
                    ],
                    'fat' => [
                        'average' => round($logs->avg('fat_g'), 1),
                        'goal' => $user->fat_goal ?? 0,
                        'adherence' => $this->calculateMacroAdherenceRate($logs, 'fat_g', $user->fat_goal),
                    ],
                ],
            ];
        }

        return [
            'clients' => $macroData,
            'aggregateStats' => $this->getAggregateMacroStats($macroData),
        ];
    }

    protected function calculateMacroAdherenceRate($logs, $macroField, $goal)
    {
        if (!$goal || $logs->isEmpty()) return 0;

        $compliant = $logs->filter(function ($log) use ($macroField, $goal) {
            $diff = abs($log->$macroField - $goal);
            return $diff <= ($goal * 0.15);
        })->count();

        return round(($compliant / $logs->count()) * 100, 1);
    }

    protected function getDailyMacroTrends($logs)
    {
        return $logs->groupBy(function ($log) {
            return Carbon::parse($log->logged_at)->toDateString();
        })->map(function ($dayLogs, $date) {
            return [
                'date' => $date,
                'protein' => round($dayLogs->sum('protein_g'), 1),
                'carbs' => round($dayLogs->sum('carbs_g'), 1),
                'fat' => round($dayLogs->sum('fat_g'), 1),
                'calories' => round($dayLogs->sum('calories'), 1),
            ];
        })->values();
    }

    protected function getMacroDistribution($logs)
    {
        $totalProtein = $logs->sum('protein_g') * 4; // 4 cal per gram
        $totalCarbs = $logs->sum('carbs_g') * 4;
        $totalFat = $logs->sum('fat_g') * 9; // 9 cal per gram
        $totalCalories = $totalProtein + $totalCarbs + $totalFat;

        if ($totalCalories == 0) {
            return [
                'protein' => 0,
                'carbs' => 0,
                'fat' => 0,
            ];
        }

        return [
            'protein' => round(($totalProtein / $totalCalories) * 100, 1),
            'carbs' => round(($totalCarbs / $totalCalories) * 100, 1),
            'fat' => round(($totalFat / $totalCalories) * 100, 1),
        ];
    }

    protected function getAggregateMacroStats($macroData)
    {
        if (empty($macroData)) {
            return [
                'averageProteinAdherence' => 0,
                'averageCarbsAdherence' => 0,
                'averageFatAdherence' => 0,
                'overallMacroAdherence' => 0,
            ];
        }

        $proteinAdherence = collect($macroData)->avg('macros.protein.adherence');
        $carbsAdherence = collect($macroData)->avg('macros.carbs.adherence');
        $fatAdherence = collect($macroData)->avg('macros.fat.adherence');

        return [
            'averageProteinAdherence' => round($proteinAdherence, 1),
            'averageCarbsAdherence' => round($carbsAdherence, 1),
            'averageFatAdherence' => round($fatAdherence, 1),
            'overallMacroAdherence' => round(($proteinAdherence + $carbsAdherence + $fatAdherence) / 3, 1),
        ];
    }
}
