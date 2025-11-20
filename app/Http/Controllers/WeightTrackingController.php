<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class WeightTrackingController extends Controller
{
    /**
     * Get user's weight history
     */
    public function getWeightHistory(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $limit = $request->input('limit', 30); // Default last 30 entries

            $weightHistory = DB::table('weight_logs')
                ->where('user_id', $user->id)
                ->orderBy('log_date', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($entry) {
                    return [
                        'id' => $entry->id,
                        'weight' => (float) $entry->weight,
                        'unit' => $entry->unit ?? 'lbs',
                        'date' => $entry->log_date,
                        'notes' => $entry->notes,
                        'created_at' => $entry->created_at
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'history' => $weightHistory,
                    'total_entries' => $weightHistory->count()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching weight history', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch weight history',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Log new weight entry
     */
    public function logWeight(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'weight' => 'required|numeric|min:0|max:1000',
            'unit' => 'required|in:lbs,kg',
            'date' => 'nullable|date',
            'notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            $weight = $request->input('weight');
            $unit = $request->input('unit');
            $date = $request->input('date', now()->toDateString());
            $notes = $request->input('notes');

            // Check if entry exists for this date
            $existingEntry = DB::table('weight_logs')
                ->where('user_id', $user->id)
                ->where('log_date', $date)
                ->first();

            if ($existingEntry) {
                // Update existing entry
                DB::table('weight_logs')
                    ->where('id', $existingEntry->id)
                    ->update([
                        'weight' => $weight,
                        'unit' => $unit,
                        'notes' => $notes,
                        'updated_at' => now()
                    ]);

                $entryId = $existingEntry->id;
                $message = 'Weight entry updated successfully';
            } else {
                // Create new entry
                $entryId = DB::table('weight_logs')->insertGetId([
                    'user_id' => $user->id,
                    'weight' => $weight,
                    'unit' => $unit,
                    'log_date' => $date,
                    'notes' => $notes,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $message = 'Weight logged successfully';
            }

            // Award body points for logging weight
            $this->awardBodyPoints($user->id, 5, 'weight_log');

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'entry_id' => $entryId,
                    'weight' => (float) $weight,
                    'unit' => $unit,
                    'date' => $date,
                    'points_earned' => 5
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error logging weight', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to log weight',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get weight statistics and trends
     */
    public function getWeightStats(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $period = $request->input('period', 30); // Default 30 days

            $startDate = now()->subDays($period);

            // Get weight entries for period
            $entries = DB::table('weight_logs')
                ->where('user_id', $user->id)
                ->where('log_date', '>=', $startDate)
                ->orderBy('log_date', 'asc')
                ->get();

            if ($entries->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'stats' => null,
                        'message' => 'No weight data available for this period'
                    ]
                ]);
            }

            // Calculate statistics
            $weights = $entries->pluck('weight')->map(fn($w) => (float) $w);
            $currentWeight = (float) $entries->last()->weight;
            $startingWeight = (float) $entries->first()->weight;
            $highestWeight = $weights->max();
            $lowestWeight = $weights->min();
            $averageWeight = round($weights->average(), 2);
            $weightChange = round($currentWeight - $startingWeight, 2);
            $percentChange = $startingWeight > 0
                ? round(($weightChange / $startingWeight) * 100, 2)
                : 0;

            // Calculate trend (simple linear regression)
            $trend = $this->calculateTrend($entries);

            // Get goal data
            $goal = DB::table('weight_goals')
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => [
                        'current_weight' => $currentWeight,
                        'starting_weight' => $startingWeight,
                        'highest_weight' => $highestWeight,
                        'lowest_weight' => $lowestWeight,
                        'average_weight' => $averageWeight,
                        'weight_change' => $weightChange,
                        'percent_change' => $percentChange,
                        'trend' => $trend,
                        'total_logs' => $entries->count(),
                        'period_days' => $period
                    ],
                    'goal' => $goal ? [
                        'target_weight' => (float) $goal->target_weight,
                        'start_weight' => (float) $goal->start_weight,
                        'start_date' => $goal->start_date,
                        'target_date' => $goal->target_date,
                        'progress_percentage' => $this->calculateGoalProgress($currentWeight, $goal)
                    ] : null
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error calculating weight stats', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate weight statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get user's weight goals
     */
    public function getWeightGoals(): JsonResponse
    {
        try {
            $user = Auth::user();

            $activeGoal = DB::table('weight_goals')
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->first();

            $goalHistory = DB::table('weight_goals')
                ->where('user_id', $user->id)
                ->where('is_active', false)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'active_goal' => $activeGoal ? [
                        'id' => $activeGoal->id,
                        'target_weight' => (float) $activeGoal->target_weight,
                        'start_weight' => (float) $activeGoal->start_weight,
                        'current_weight' => $this->getCurrentWeight($user->id),
                        'unit' => $activeGoal->unit ?? 'lbs',
                        'start_date' => $activeGoal->start_date,
                        'target_date' => $activeGoal->target_date,
                        'goal_type' => $activeGoal->goal_type, // lose, gain, maintain
                        'weekly_target' => (float) ($activeGoal->weekly_target ?? 0),
                        'notes' => $activeGoal->notes
                    ] : null,
                    'history' => $goalHistory
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching weight goals', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch weight goals',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Create or update weight goal
     */
    public function updateWeightGoal(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'target_weight' => 'required|numeric|min:0|max:1000',
            'unit' => 'required|in:lbs,kg',
            'target_date' => 'required|date|after:today',
            'goal_type' => 'required|in:lose,gain,maintain',
            'weekly_target' => 'nullable|numeric|min:0|max:10',
            'notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();

            // Get current weight
            $currentWeight = $this->getCurrentWeight($user->id);

            if (!$currentWeight) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please log your current weight before setting a goal'
                ], 400);
            }

            // Deactivate any existing active goals
            DB::table('weight_goals')
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'updated_at' => now()
                ]);

            // Create new goal
            $goalId = DB::table('weight_goals')->insertGetId([
                'user_id' => $user->id,
                'start_weight' => $currentWeight,
                'target_weight' => $request->input('target_weight'),
                'unit' => $request->input('unit'),
                'start_date' => now()->toDateString(),
                'target_date' => $request->input('target_date'),
                'goal_type' => $request->input('goal_type'),
                'weekly_target' => $request->input('weekly_target', 0),
                'notes' => $request->input('notes'),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Award body points for setting goal
            $this->awardBodyPoints($user->id, 50, 'weight_goal_set');

            return response()->json([
                'success' => true,
                'message' => 'Weight goal set successfully',
                'data' => [
                    'goal_id' => $goalId,
                    'points_earned' => 50
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating weight goal', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update weight goal',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Delete weight log entry
     */
    public function deleteWeightLog(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $entry = DB::table('weight_logs')
                ->where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$entry) {
                return response()->json([
                    'success' => false,
                    'message' => 'Weight log entry not found'
                ], 404);
            }

            DB::table('weight_logs')->where('id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Weight log entry deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting weight log', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete weight log',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get current weight (most recent log)
     */
    private function getCurrentWeight(int $userId): ?float
    {
        $latestLog = DB::table('weight_logs')
            ->where('user_id', $userId)
            ->orderBy('log_date', 'desc')
            ->first();

        return $latestLog ? (float) $latestLog->weight : null;
    }

    /**
     * Calculate trend from weight entries
     */
    private function calculateTrend($entries): string
    {
        if ($entries->count() < 2) {
            return 'stable';
        }

        $weights = $entries->pluck('weight')->map(fn($w) => (float) $w)->toArray();
        $n = count($weights);

        // Simple moving average comparison
        $firstHalf = array_slice($weights, 0, (int) ceil($n / 2));
        $secondHalf = array_slice($weights, (int) floor($n / 2));

        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);

        $difference = $secondAvg - $firstAvg;

        if (abs($difference) < 0.5) {
            return 'stable';
        }

        return $difference > 0 ? 'increasing' : 'decreasing';
    }

    /**
     * Calculate goal progress percentage
     */
    private function calculateGoalProgress(float $currentWeight, object $goal): int
    {
        $startWeight = (float) $goal->start_weight;
        $targetWeight = (float) $goal->target_weight;

        if ($startWeight === $targetWeight) {
            return 100;
        }

        $totalChange = $targetWeight - $startWeight;
        $currentChange = $currentWeight - $startWeight;

        $progress = ($currentChange / $totalChange) * 100;

        return (int) max(0, min(100, round($progress)));
    }

    /**
     * Award body points to user
     */
    private function awardBodyPoints(int $userId, int $points, string $reason): void
    {
        try {
            // Check if users table has body_points column
            DB::table('users')
                ->where('id', $userId)
                ->increment('body_points', $points);

            // Log the points transaction
            DB::table('body_points_transactions')->insert([
                'user_id' => $userId,
                'points' => $points,
                'reason' => $reason,
                'created_at' => now()
            ]);

            Log::info('Body points awarded', [
                'user_id' => $userId,
                'points' => $points,
                'reason' => $reason
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the main operation
            Log::warning('Failed to award body points', [
                'user_id' => $userId,
                'points' => $points,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Upload progression photo (Client-facing)
     */
    public function uploadProgressionPhoto(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:10240', // Max 10MB
            'type' => 'required|in:front,back,side',
            'weight' => 'nullable|numeric|min:0|max:1000',
            'date' => 'nullable|date',
            'notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            $userId = $user->id;

            $file = $request->file('photo');
            $type = $request->input('type');
            $timestamp = time();
            $filename = "{$userId}_{$type}_{$timestamp}." . $file->getClientOriginalExtension();

            // Store in public storage
            $path = $file->storeAs('progression_photos', $filename, 'public');
            $url = url('storage/' . $path);

            // Insert photo record
            $photoId = DB::table('progression_photos')->insertGetId([
                'user_id' => $userId,
                'uploaded_by' => $userId, // Self-uploaded
                'url' => $url,
                'filename' => $filename,
                'type' => $type, // front, back, side
                'weight' => $request->input('weight'),
                'notes' => $request->input('notes'),
                'created_at' => $request->input('date') ? Carbon::parse($request->input('date')) : now(),
                'updated_at' => now()
            ]);

            // Award body points for uploading progress photo
            $gamification = app(\App\Services\GamificationService::class);
            $points = $gamification->awardMeasurementPoints($userId, 'progress_photo', [
                'type' => $type
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Progress photo uploaded successfully',
                'data' => [
                    'photo_id' => $photoId,
                    'url' => $url,
                    'type' => $type,
                    'date' => $request->input('date') ?? now()->toDateTimeString(),
                    'points_earned' => $points
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error uploading progression photo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload progress photo',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get user's progression photos (Client-facing)
     */
    public function getProgressionPhotos(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $userId = $user->id;

            $photos = DB::table('progression_photos')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->select('id', 'url', 'type', 'weight', 'notes', 'created_at as date')
                ->get()
                ->groupBy('type'); // Group by front, back, side for easy display

            // Get timeline view (all photos chronologically)
            $timeline = DB::table('progression_photos')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->select('id', 'url', 'type', 'weight', 'notes', 'created_at as date')
                ->get();

            // Get comparison data (first vs latest)
            $firstPhoto = DB::table('progression_photos')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'asc')
                ->first();

            $latestPhoto = DB::table('progression_photos')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'by_type' => [
                        'front' => $photos->get('front', collect()),
                        'back' => $photos->get('back', collect()),
                        'side' => $photos->get('side', collect())
                    ],
                    'timeline' => $timeline,
                    'comparison' => [
                        'first' => $firstPhoto ? [
                            'id' => $firstPhoto->id,
                            'url' => $firstPhoto->url,
                            'type' => $firstPhoto->type,
                            'weight' => $firstPhoto->weight,
                            'date' => $firstPhoto->created_at
                        ] : null,
                        'latest' => $latestPhoto ? [
                            'id' => $latestPhoto->id,
                            'url' => $latestPhoto->url,
                            'type' => $latestPhoto->type,
                            'weight' => $latestPhoto->weight,
                            'date' => $latestPhoto->created_at
                        ] : null
                    ],
                    'total_photos' => $timeline->count()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching progression photos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch progression photos',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Delete progression photo (Client-facing)
     */
    public function deleteProgressionPhoto(Request $request, int $photoId): JsonResponse
    {
        try {
            $user = Auth::user();
            $userId = $user->id;

            $photo = DB::table('progression_photos')
                ->where('id', $photoId)
                ->where('user_id', $userId)
                ->first();

            if (!$photo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Photo not found or you do not have permission to delete it'
                ], 404);
            }

            // Delete file from storage
            if (\Storage::disk('public')->exists('progression_photos/' . $photo->filename)) {
                \Storage::disk('public')->delete('progression_photos/' . $photo->filename);
            }

            // Delete database record
            DB::table('progression_photos')->where('id', $photoId)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Progress photo deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting progression photo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete progress photo',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
