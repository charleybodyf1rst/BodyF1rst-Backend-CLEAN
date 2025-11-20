<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\NutritionLog;
use App\Models\NutritionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * Coach Client Nutrition Controller
 * Handles client nutrition tracking for coaches
 */
class CoachClientNutritionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Get Client Daily Nutrition Logs
     * GET /api/coaches/clients/{id}/nutrition/daily
     */
    public function getDailyLogs(Request $request, $clientId)
    {
        try {
            $coachId = Auth::id();

            // Verify coach has access to this client
            if (!$this->verifyCoachAccess($coachId, $clientId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to client data',
                ], 403);
            }

            $date = $request->input('date', now()->toDateString());
            $targetDate = Carbon::parse($date);

            $client = User::find($clientId);
            $logs = NutritionLog::where('user_id', $clientId)
                ->whereDate('logged_at', $targetDate)
                ->orderBy('logged_at', 'asc')
                ->get();

            $dailySummary = [
                'totalCalories' => $logs->sum('calories'),
                'totalProtein' => $logs->sum('protein_g'),
                'totalCarbs' => $logs->sum('carbs_g'),
                'totalFat' => $logs->sum('fat_g'),
                'totalFiber' => $logs->sum('fiber_g'),
                'mealCount' => $logs->count(),
                'waterIntake' => $this->getWaterIntake($clientId, $targetDate),
            ];

            $goals = [
                'calories' => $client->calorie_goal ?? 2000,
                'protein' => $client->protein_goal ?? 150,
                'carbs' => $client->carbs_goal ?? 200,
                'fat' => $client->fat_goal ?? 65,
            ];

            $progress = [
                'calories' => $goals['calories'] > 0 ? round(($dailySummary['totalCalories'] / $goals['calories']) * 100, 1) : 0,
                'protein' => $goals['protein'] > 0 ? round(($dailySummary['totalProtein'] / $goals['protein']) * 100, 1) : 0,
                'carbs' => $goals['carbs'] > 0 ? round(($dailySummary['totalCarbs'] / $goals['carbs']) * 100, 1) : 0,
                'fat' => $goals['fat'] > 0 ? round(($dailySummary['totalFat'] / $goals['fat']) * 100, 1) : 0,
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'client' => [
                        'id' => $client->id,
                        'name' => $client->name,
                        'email' => $client->email,
                    ],
                    'date' => $targetDate->toDateString(),
                    'summary' => $dailySummary,
                    'goals' => $goals,
                    'progress' => $progress,
                    'logs' => $logs,
                    'isCompliant' => $this->isDayCompliant($dailySummary, $goals),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load daily nutrition logs',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get Client Weekly Nutrition Summary
     * GET /api/coaches/clients/{id}/nutrition/weekly
     */
    public function getWeeklySummary(Request $request, $clientId)
    {
        try {
            $coachId = Auth::id();

            if (!$this->verifyCoachAccess($coachId, $clientId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to client data',
                ], 403);
            }

            $weekStart = $request->input('week_start', now()->startOfWeek()->toDateString());
            $startDate = Carbon::parse($weekStart)->startOfWeek();
            $endDate = $startDate->copy()->endOfWeek();

            $client = User::find($clientId);
            $logs = NutritionLog::where('user_id', $clientId)
                ->whereBetween('logged_at', [$startDate, $endDate])
                ->get();

            // Group by day
            $dailyData = [];
            $currentDate = $startDate->copy();

            while ($currentDate <= $endDate) {
                $dayLogs = $logs->filter(function ($log) use ($currentDate) {
                    return Carbon::parse($log->logged_at)->isSameDay($currentDate);
                });

                $dailyData[] = [
                    'date' => $currentDate->toDateString(),
                    'dayName' => $currentDate->format('l'),
                    'totalCalories' => $dayLogs->sum('calories'),
                    'totalProtein' => round($dayLogs->sum('protein_g'), 1),
                    'totalCarbs' => round($dayLogs->sum('carbs_g'), 1),
                    'totalFat' => round($dayLogs->sum('fat_g'), 1),
                    'mealCount' => $dayLogs->count(),
                    'isLogged' => $dayLogs->count() > 0,
                    'isCompliant' => $this->isDayCompliant([
                        'totalCalories' => $dayLogs->sum('calories'),
                        'totalProtein' => $dayLogs->sum('protein_g'),
                        'totalCarbs' => $dayLogs->sum('carbs_g'),
                        'totalFat' => $dayLogs->sum('fat_g'),
                    ], [
                        'calories' => $client->calorie_goal ?? 2000,
                        'protein' => $client->protein_goal ?? 150,
                        'carbs' => $client->carbs_goal ?? 200,
                        'fat' => $client->fat_goal ?? 65,
                    ]),
                ];

                $currentDate->addDay();
            }

            $weeklyStats = [
                'averageDailyCalories' => round($logs->avg('calories'), 1),
                'averageDailyProtein' => round($logs->avg('protein_g'), 1),
                'averageDailyCarbs' => round($logs->avg('carbs_g'), 1),
                'averageDailyFat' => round($logs->avg('fat_g'), 1),
                'totalLogsCount' => $logs->count(),
                'daysLogged' => collect($dailyData)->where('isLogged', true)->count(),
                'complianceDays' => collect($dailyData)->where('isCompliant', true)->count(),
                'complianceRate' => round((collect($dailyData)->where('isCompliant', true)->count() / 7) * 100, 1),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'client' => [
                        'id' => $client->id,
                        'name' => $client->name,
                        'email' => $client->email,
                    ],
                    'weekStart' => $startDate->toDateString(),
                    'weekEnd' => $endDate->toDateString(),
                    'dailyData' => $dailyData,
                    'weeklyStats' => $weeklyStats,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load weekly nutrition summary',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get Client Nutrition Trends
     * GET /api/coaches/clients/{id}/nutrition/trends
     */
    public function getTrends(Request $request, $clientId)
    {
        try {
            $coachId = Auth::id();

            if (!$this->verifyCoachAccess($coachId, $clientId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to client data',
                ], 403);
            }

            $period = $request->input('period', 'month'); // week, month, quarter, year
            [$startDate, $endDate] = $this->getDateRange($period);

            $client = User::find($clientId);
            $logs = NutritionLog::where('user_id', $clientId)
                ->whereBetween('logged_at', [$startDate, $endDate])
                ->orderBy('logged_at', 'asc')
                ->get();

            // Daily trends
            $dailyTrends = $logs->groupBy(function ($log) {
                return Carbon::parse($log->logged_at)->toDateString();
            })->map(function ($dayLogs, $date) {
                return [
                    'date' => $date,
                    'calories' => round($dayLogs->sum('calories'), 1),
                    'protein' => round($dayLogs->sum('protein_g'), 1),
                    'carbs' => round($dayLogs->sum('carbs_g'), 1),
                    'fat' => round($dayLogs->sum('fat_g'), 1),
                    'logCount' => $dayLogs->count(),
                ];
            })->values();

            // Weekly averages
            $weeklyAverages = $logs->groupBy(function ($log) {
                return Carbon::parse($log->logged_at)->startOfWeek()->toDateString();
            })->map(function ($weekLogs, $weekStart) {
                return [
                    'weekStart' => $weekStart,
                    'avgCalories' => round($weekLogs->avg('calories'), 1),
                    'avgProtein' => round($weekLogs->avg('protein_g'), 1),
                    'avgCarbs' => round($weekLogs->avg('carbs_g'), 1),
                    'avgFat' => round($weekLogs->avg('fat_g'), 1),
                    'logsCount' => $weekLogs->count(),
                ];
            })->values();

            // Macro distribution over time
            $macroDistribution = $this->getMacroDistributionTrend($logs);

            // Compliance trend
            $complianceTrend = $this->getComplianceTrend($clientId, $logs, $client);

            // Insights
            $insights = $this->generateTrendInsights($logs, $client);

            return response()->json([
                'success' => true,
                'data' => [
                    'client' => [
                        'id' => $client->id,
                        'name' => $client->name,
                    ],
                    'period' => $period,
                    'dateRange' => [
                        'start' => $startDate->toDateString(),
                        'end' => $endDate->toDateString(),
                    ],
                    'dailyTrends' => $dailyTrends,
                    'weeklyAverages' => $weeklyAverages,
                    'macroDistribution' => $macroDistribution,
                    'complianceTrend' => $complianceTrend,
                    'insights' => $insights,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load nutrition trends',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Add Nutrition Log for Client (Coach can log on behalf of client)
     * POST /api/coaches/clients/{id}/nutrition/log
     */
    public function addNutritionLog(Request $request, $clientId)
    {
        $validator = Validator::make($request->all(), [
            'food_name' => 'required|string|max:255',
            'calories' => 'required|numeric|min:0',
            'protein_g' => 'required|numeric|min:0',
            'carbs_g' => 'required|numeric|min:0',
            'fat_g' => 'required|numeric|min:0',
            'fiber_g' => 'nullable|numeric|min:0',
            'serving_size' => 'nullable|string|max:100',
            'meal_type' => 'nullable|string|in:breakfast,lunch,dinner,snack',
            'logged_at' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $coachId = Auth::id();

            if (!$this->verifyCoachAccess($coachId, $clientId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to client data',
                ], 403);
            }

            $nutritionLog = NutritionLog::create([
                'user_id' => $clientId,
                'food_name' => $request->food_name,
                'calories' => $request->calories,
                'protein_g' => $request->protein_g,
                'carbs_g' => $request->carbs_g,
                'fat_g' => $request->fat_g,
                'fiber_g' => $request->fiber_g ?? 0,
                'serving_size' => $request->serving_size,
                'meal_type' => $request->meal_type ?? 'snack',
                'logged_at' => $request->logged_at ?? now(),
                'logged_by_coach' => true,
                'coach_id' => $coachId,
                'notes' => $request->notes,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Nutrition log added successfully',
                'data' => $nutritionLog,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add nutrition log',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Update Client Nutrition Goals
     * PUT /api/coaches/clients/{id}/nutrition/goals
     */
    public function updateNutritionGoals(Request $request, $clientId)
    {
        $validator = Validator::make($request->all(), [
            'calorie_goal' => 'required|numeric|min:1000|max:6000',
            'protein_goal' => 'required|numeric|min:0|max:500',
            'carbs_goal' => 'required|numeric|min:0|max:1000',
            'fat_goal' => 'required|numeric|min:0|max:300',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $coachId = Auth::id();

            if (!$this->verifyCoachAccess($coachId, $clientId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to client data',
                ], 403);
            }

            $client = User::find($clientId);
            $client->update([
                'calorie_goal' => $request->calorie_goal,
                'protein_goal' => $request->protein_goal,
                'carbs_goal' => $request->carbs_goal,
                'fat_goal' => $request->fat_goal,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Nutrition goals updated successfully',
                'data' => [
                    'calorie_goal' => $client->calorie_goal,
                    'protein_goal' => $client->protein_goal,
                    'carbs_goal' => $client->carbs_goal,
                    'fat_goal' => $client->fat_goal,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update nutrition goals',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // Helper Methods

    protected function verifyCoachAccess($coachId, $clientId)
    {
        $client = User::find($clientId);
        return $client && $client->coach_id == $coachId;
    }

    protected function getWaterIntake($clientId, $date)
    {
        // This would integrate with a water_intake table if it exists
        // For now, return placeholder
        return [
            'amount' => 0,
            'goal' => 64,
            'unit' => 'oz',
        ];
    }

    protected function isDayCompliant($summary, $goals)
    {
        if (!isset($summary['totalCalories']) || !isset($goals['calories'])) {
            return false;
        }

        $calorieDiff = abs($summary['totalCalories'] - $goals['calories']);
        return $calorieDiff <= ($goals['calories'] * 0.15); // Within 15% of goal
    }

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

    protected function getMacroDistributionTrend($logs)
    {
        return $logs->groupBy(function ($log) {
            return Carbon::parse($log->logged_at)->toDateString();
        })->map(function ($dayLogs, $date) {
            $totalProteinCal = $dayLogs->sum('protein_g') * 4;
            $totalCarbsCal = $dayLogs->sum('carbs_g') * 4;
            $totalFatCal = $dayLogs->sum('fat_g') * 9;
            $totalCal = $totalProteinCal + $totalCarbsCal + $totalFatCal;

            if ($totalCal == 0) {
                return [
                    'date' => $date,
                    'proteinPercent' => 0,
                    'carbsPercent' => 0,
                    'fatPercent' => 0,
                ];
            }

            return [
                'date' => $date,
                'proteinPercent' => round(($totalProteinCal / $totalCal) * 100, 1),
                'carbsPercent' => round(($totalCarbsCal / $totalCal) * 100, 1),
                'fatPercent' => round(($totalFatCal / $totalCal) * 100, 1),
            ];
        })->values();
    }

    protected function getComplianceTrend($clientId, $logs, $client)
    {
        return $logs->groupBy(function ($log) {
            return Carbon::parse($log->logged_at)->startOfWeek()->toDateString();
        })->map(function ($weekLogs, $weekStart) use ($client) {
            $week = Carbon::parse($weekStart);
            $weekEnd = $week->copy()->endOfWeek();

            $daysLogged = $weekLogs->groupBy(function ($log) {
                return Carbon::parse($log->logged_at)->toDateString();
            })->count();

            $compliantDays = $weekLogs->groupBy(function ($log) {
                return Carbon::parse($log->logged_at)->toDateString();
            })->filter(function ($dayLogs) use ($client) {
                $dayCalories = $dayLogs->sum('calories');
                $calorieGoal = $client->calorie_goal ?? 2000;
                $diff = abs($dayCalories - $calorieGoal);
                return $diff <= ($calorieGoal * 0.15);
            })->count();

            return [
                'weekStart' => $weekStart,
                'weekEnd' => $weekEnd->toDateString(),
                'daysLogged' => $daysLogged,
                'compliantDays' => $compliantDays,
                'complianceRate' => $daysLogged > 0 ? round(($compliantDays / $daysLogged) * 100, 1) : 0,
            ];
        })->values();
    }

    protected function generateTrendInsights($logs, $client)
    {
        $insights = [];

        if ($logs->isEmpty()) {
            return ['No nutrition data available for this period'];
        }

        // Average calories trend
        $avgCalories = $logs->avg('calories');
        $calorieGoal = $client->calorie_goal ?? 2000;

        if ($avgCalories < ($calorieGoal * 0.85)) {
            $insights[] = "Client is consistently under their calorie goal by " . round($calorieGoal - $avgCalories) . " calories";
        } elseif ($avgCalories > ($calorieGoal * 1.15)) {
            $insights[] = "Client is consistently over their calorie goal by " . round($avgCalories - $calorieGoal) . " calories";
        } else {
            $insights[] = "Client is maintaining good calorie adherence";
        }

        // Protein intake
        $avgProtein = $logs->avg('protein_g');
        $proteinGoal = $client->protein_goal ?? 150;

        if ($avgProtein < ($proteinGoal * 0.8)) {
            $insights[] = "Protein intake is below target - consider increasing protein-rich foods";
        }

        // Logging consistency
        $daysLogged = $logs->groupBy(function ($log) {
            return Carbon::parse($log->logged_at)->toDateString();
        })->count();

        if ($daysLogged < 7) {
            $insights[] = "Inconsistent logging - encourage daily tracking for better results";
        }

        return $insights;
    }
}
