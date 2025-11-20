<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WeeklyCheckin;
use App\Models\Workout;
use App\Models\UserMeal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ProgressReportController extends Controller
{
    /**
     * Generate a progress report PDF for a client
     */
    public function generateProgressReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'report_type' => 'nullable|in:comprehensive,summary,weekly,monthly',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $coach = $request->user();
        $clientId = $request->client_id;
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $reportType = $request->report_type ?? 'comprehensive';

        // Get client
        $client = User::find($clientId);

        // Verify authorization (coach can only generate reports for their clients)
        if ($client->coach_id !== $coach->id && !$coach->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - You can only generate reports for your own clients'
            ], 403);
        }

        // Gather report data
        $reportData = $this->gatherReportData($client, $startDate, $endDate);

        // Generate PDF
        $pdf = $this->generatePDF($client, $coach, $reportData, $startDate, $endDate, $reportType);

        // Return PDF for download
        $filename = sprintf(
            'Progress_Report_%s_%s_to_%s.pdf',
            str_replace(' ', '_', $client->first_name . '_' . $client->last_name),
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        return $pdf->download($filename);
    }

    /**
     * Stream progress report PDF (view in browser)
     */
    public function streamProgressReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'report_type' => 'nullable|in:comprehensive,summary,weekly,monthly',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $coach = $request->user();
        $clientId = $request->client_id;
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $reportType = $request->report_type ?? 'comprehensive';

        $client = User::find($clientId);

        if ($client->coach_id !== $coach->id && !$coach->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $reportData = $this->gatherReportData($client, $startDate, $endDate);
        $pdf = $this->generatePDF($client, $coach, $reportData, $startDate, $endDate, $reportType);

        return $pdf->stream();
    }

    /**
     * Gather all data needed for the progress report
     */
    private function gatherReportData(User $client, Carbon $startDate, Carbon $endDate): array
    {
        // Get weekly check-ins
        $checkins = WeeklyCheckin::where('user_id', $client->id)
            ->whereBetween('checkin_date', [$startDate, $endDate])
            ->orderBy('checkin_date', 'asc')
            ->get();

        // Calculate weight progress
        $weightProgress = $this->calculateWeightProgress($checkins);

        // Calculate body composition progress
        $bodyComposition = $this->calculateBodyCompositionProgress($checkins);

        // Calculate wellness trends
        $wellnessTrends = $this->calculateWellnessTrends($checkins);

        // Calculate compliance metrics
        $complianceMetrics = $this->calculateComplianceMetrics($checkins);

        // Get workout statistics
        $workoutStats = $this->getWorkoutStatistics($client, $startDate, $endDate);

        // Get nutrition statistics
        $nutritionStats = $this->getNutritionStatistics($client, $startDate, $endDate);

        // Calculate overall progress score
        $progressScore = $this->calculateProgressScore($checkins, $workoutStats, $nutritionStats);

        return [
            'checkins' => $checkins,
            'weight_progress' => $weightProgress,
            'body_composition' => $bodyComposition,
            'wellness_trends' => $wellnessTrends,
            'compliance_metrics' => $complianceMetrics,
            'workout_stats' => $workoutStats,
            'nutrition_stats' => $nutritionStats,
            'progress_score' => $progressScore,
            'period_summary' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_days' => $startDate->diffInDays($endDate),
                'total_weeks' => $checkins->count(),
            ]
        ];
    }

    /**
     * Calculate weight progress from check-ins
     */
    private function calculateWeightProgress($checkins): array
    {
        if ($checkins->isEmpty()) {
            return [
                'starting_weight' => null,
                'ending_weight' => null,
                'total_change' => 0,
                'avg_weekly_change' => 0,
                'trend' => 'stable'
            ];
        }

        $startingWeight = $checkins->first()->current_weight;
        $endingWeight = $checkins->last()->current_weight;
        $totalChange = $endingWeight - $startingWeight;
        $avgWeeklyChange = $checkins->count() > 1 ? $totalChange / $checkins->count() : 0;

        return [
            'starting_weight' => $startingWeight,
            'ending_weight' => $endingWeight,
            'total_change' => round($totalChange, 2),
            'avg_weekly_change' => round($avgWeeklyChange, 2),
            'trend' => $totalChange < -1 ? 'losing' : ($totalChange > 1 ? 'gaining' : 'stable'),
            'weight_data' => $checkins->map(function ($checkin) {
                return [
                    'date' => $checkin->checkin_date->format('Y-m-d'),
                    'weight' => $checkin->current_weight
                ];
            })->toArray()
        ];
    }

    /**
     * Calculate body composition progress
     */
    private function calculateBodyCompositionProgress($checkins): array
    {
        if ($checkins->isEmpty()) {
            return ['body_fat_change' => 0, 'measurements' => []];
        }

        $startingBF = $checkins->first()->body_fat_percentage;
        $endingBF = $checkins->last()->body_fat_percentage;

        return [
            'starting_body_fat' => $startingBF,
            'ending_body_fat' => $endingBF,
            'body_fat_change' => $startingBF && $endingBF ? round($endingBF - $startingBF, 2) : null,
            'measurements' => $checkins->last()->measurements ?? []
        ];
    }

    /**
     * Calculate wellness trends
     */
    private function calculateWellnessTrends($checkins): array
    {
        if ($checkins->isEmpty()) {
            return [];
        }

        return [
            'avg_energy' => round($checkins->avg('energy_level'), 1),
            'avg_mood' => round($checkins->avg('mood'), 1),
            'avg_sleep_quality' => round($checkins->avg('sleep_quality'), 1),
            'avg_sleep_hours' => round($checkins->avg('sleep_hours'), 1),
            'avg_stress' => round($checkins->avg('stress_level'), 1),
        ];
    }

    /**
     * Calculate compliance metrics
     */
    private function calculateComplianceMetrics($checkins): array
    {
        if ($checkins->isEmpty()) {
            return ['workout_compliance' => 0, 'nutrition_compliance' => 0];
        }

        $totalWorkoutsPlanned = $checkins->sum('workouts_planned');
        $totalWorkoutsCompleted = $checkins->sum('workouts_completed');

        return [
            'workout_compliance' => $totalWorkoutsPlanned > 0
                ? round(($totalWorkoutsCompleted / $totalWorkoutsPlanned) * 100, 1)
                : 0,
            'total_workouts_planned' => $totalWorkoutsPlanned,
            'total_workouts_completed' => $totalWorkoutsCompleted,
            'avg_meals_logged' => round($checkins->avg('meals_logged'), 1),
            'avg_water_intake' => round($checkins->avg('water_intake_oz'), 1),
        ];
    }

    /**
     * Get workout statistics
     */
    private function getWorkoutStatistics(User $client, Carbon $startDate, Carbon $endDate): array
    {
        // This would integrate with your workout tracking system
        // Placeholder implementation
        return [
            'total_workouts' => 0,
            'total_duration_minutes' => 0,
            'avg_workout_duration' => 0,
            'favorite_exercises' => []
        ];
    }

    /**
     * Get nutrition statistics
     */
    private function getNutritionStatistics(User $client, Carbon $startDate, Carbon $endDate): array
    {
        // This would integrate with your nutrition tracking system
        // Placeholder implementation
        return [
            'avg_calories' => 0,
            'avg_protein' => 0,
            'avg_carbs' => 0,
            'avg_fats' => 0,
            'meals_logged' => 0
        ];
    }

    /**
     * Calculate overall progress score
     */
    private function calculateProgressScore($checkins, $workoutStats, $nutritionStats): int
    {
        if ($checkins->isEmpty()) {
            return 0;
        }

        // Weight progress score (30%)
        $weightScore = 0;
        $firstCheckin = $checkins->first();
        $lastCheckin = $checkins->last();
        if ($firstCheckin->current_weight && $lastCheckin->current_weight) {
            $weightChange = $firstCheckin->current_weight - $lastCheckin->current_weight;
            $weightScore = min(30, max(0, $weightChange * 3)); // Losing weight increases score
        }

        // Compliance score (40%)
        $complianceScore = $checkins->avg('compliance_rate') * 0.4;

        // Wellness score (30%)
        $wellnessScore = $checkins->avg('wellness_score') * 3;

        return round($weightScore + $complianceScore + $wellnessScore);
    }

    /**
     * Generate PDF document
     */
    private function generatePDF(User $client, User $coach, array $data, Carbon $startDate, Carbon $endDate, string $reportType)
    {
        $viewData = [
            'client' => $client,
            'coach' => $coach,
            'data' => $data,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'report_type' => $reportType,
            'generated_at' => now(),
        ];

        // Choose template based on report type
        $template = $reportType === 'summary' ? 'pdf.progress-report-summary' : 'pdf.progress-report-comprehensive';

        $pdf = Pdf::loadView($template, $viewData);

        // Set PDF options
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => 'sans-serif'
        ]);

        return $pdf;
    }
}
