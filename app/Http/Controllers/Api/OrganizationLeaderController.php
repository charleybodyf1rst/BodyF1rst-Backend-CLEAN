<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Organization;
use Carbon\Carbon;

class OrganizationLeaderController extends Controller
{
    /**
     * Check if current user is an organization leader
     */
    public function isOrganizationLeader(Request $request)
    {
        try {
            $userId = Auth::id();

            $organization = DB::table('organizations')
                ->where('leader_id', $userId)
                ->first();

            return response()->json([
                'success' => true,
                'is_leader' => $organization !== null,
                'organization_id' => $organization->id ?? null,
                'organization_name' => $organization->name ?? null
            ]);

        } catch (\Exception $e) {
            Log::error('Error checking organization leader status', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to check leader status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get organization leader details
     */
    public function getOrganizationLeader(Request $request, $organizationId)
    {
        try {
            $organization = DB::table('organizations')
                ->where('id', $organizationId)
                ->first();

            if (!$organization) {
                return response()->json([
                    'success' => false,
                    'message' => 'Organization not found'
                ], 404);
            }

            $leader = User::find($organization->leader_id);

            $leaderInfo = [
                'user_id' => $leader->id,
                'user_name' => $leader->name,
                'email' => $leader->email,
                'organization_id' => $organization->id,
                'organization_name' => $organization->name,
                'can_make_payments' => true,
                'can_view_data' => true, // Aggregate data only
                'can_view_user_data' => false // NEVER true - privacy compliance
            ];

            return response()->json([
                'success' => true,
                'leader' => $leaderInfo
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching organization leader', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch organization leader',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set organization leader
     */
    public function setOrganizationLeader(Request $request, $organizationId)
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|integer|exists:users,id'
            ]);

            $organization = DB::table('organizations')
                ->where('id', $organizationId)
                ->first();

            if (!$organization) {
                return response()->json([
                    'success' => false,
                    'message' => 'Organization not found'
                ], 404);
            }

            DB::table('organizations')
                ->where('id', $organizationId)
                ->update([
                    'leader_id' => $validated['user_id'],
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Organization leader set successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error setting organization leader', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to set organization leader',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get organization AGGREGATE data only (NO individual user data)
     * Organization leaders can see overall metrics but NOT individual users
     */
    public function getOrganizationAggregateData(Request $request, $organizationId)
    {
        try {
            $userId = Auth::id();

            // Verify user is the organization leader
            $organization = DB::table('organizations')
                ->where('id', $organizationId)
                ->where('leader_id', $userId)
                ->first();

            if (!$organization) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. You must be the organization leader to view this data.'
                ], 403);
            }

            // Get member IDs (we'll use IDs but never expose user details)
            $memberIds = DB::table('users')
                ->where('organization_id', $organizationId)
                ->pluck('id');

            if ($memberIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'aggregate_data' => [
                        'member_count' => 0,
                        'fitness' => [],
                        'nutrition' => [],
                        'cbt' => []
                    ]
                ]);
            }

            // AGGREGATE FITNESS DATA (no individual user info)
            $fitnessData = [
                'total_workouts_completed' => DB::table('workout_logs')
                    ->whereIn('user_id', $memberIds)
                    ->where('status', 'completed')
                    ->count(),
                'average_workouts_per_week' => DB::table('workout_logs')
                    ->whereIn('user_id', $memberIds)
                    ->where('created_at', '>=', Carbon::now()->subWeeks(4))
                    ->count() / max($memberIds->count() * 4, 1),
                'total_active_users_this_week' => DB::table('workout_logs')
                    ->whereIn('user_id', $memberIds)
                    ->where('created_at', '>=', Carbon::now()->startOfWeek())
                    ->distinct('user_id')
                    ->count('user_id'),
                'average_workout_duration_minutes' => DB::table('workout_logs')
                    ->whereIn('user_id', $memberIds)
                    ->avg('duration_minutes') ?? 0
            ];

            // AGGREGATE NUTRITION DATA (no individual user info)
            $nutritionData = [
                'average_calories_tracked_per_day' => DB::table('nutrition_logs')
                    ->whereIn('user_id', $memberIds)
                    ->where('date', '>=', Carbon::now()->subDays(30))
                    ->avg('total_calories') ?? 0,
                'total_meals_logged' => DB::table('nutrition_logs')
                    ->whereIn('user_id', $memberIds)
                    ->count(),
                'users_tracking_nutrition' => DB::table('nutrition_logs')
                    ->whereIn('user_id', $memberIds)
                    ->where('date', '>=', Carbon::now()->subDays(7))
                    ->distinct('user_id')
                    ->count('user_id'),
                'average_protein_grams' => DB::table('nutrition_logs')
                    ->whereIn('user_id', $memberIds)
                    ->where('date', '>=', Carbon::now()->subDays(30))
                    ->avg('protein_grams') ?? 0
            ];

            // AGGREGATE FAT LOSS DATA (no individual user info)
            $fatLossData = [
                'average_weight_change_lbs' => DB::table('body_measurements')
                    ->whereIn('user_id', $memberIds)
                    ->selectRaw('user_id, MIN(weight_lbs) as start_weight, MAX(weight_lbs) as current_weight')
                    ->groupBy('user_id')
                    ->get()
                    ->avg(function ($item) {
                        return $item->current_weight - $item->start_weight;
                    }) ?? 0,
                'total_measurements_taken' => DB::table('body_measurements')
                    ->whereIn('user_id', $memberIds)
                    ->count(),
                'users_losing_weight' => DB::table('body_measurements')
                    ->whereIn('user_id', $memberIds)
                    ->selectRaw('user_id, MIN(weight_lbs) as start_weight, MAX(weight_lbs) as current_weight')
                    ->groupBy('user_id')
                    ->havingRaw('MAX(weight_lbs) < MIN(weight_lbs)')
                    ->count()
            ];

            // AGGREGATE CBT DATA (no individual user info)
            $cbtData = [
                'total_lessons_completed' => DB::table('cbt_lesson_completions')
                    ->whereIn('user_id', $memberIds)
                    ->count(),
                'average_lessons_per_user' => DB::table('cbt_lesson_completions')
                    ->whereIn('user_id', $memberIds)
                    ->selectRaw('user_id, COUNT(*) as lesson_count')
                    ->groupBy('user_id')
                    ->get()
                    ->avg('lesson_count') ?? 0,
                'users_active_in_cbt' => DB::table('cbt_lesson_completions')
                    ->whereIn('user_id', $memberIds)
                    ->where('completed_at', '>=', Carbon::now()->subDays(7))
                    ->distinct('user_id')
                    ->count('user_id'),
                'total_journal_entries' => DB::table('cbt_journal_entries')
                    ->whereIn('user_id', $memberIds)
                    ->count()
            ];

            $aggregateData = [
                'organization_name' => $organization->name,
                'member_count' => $memberIds->count(),
                'fitness' => $fitnessData,
                'nutrition' => $nutritionData,
                'fat_loss' => $fatLossData,
                'cbt' => $cbtData,
                'data_period' => [
                    'start_date' => Carbon::now()->subDays(30)->toDateString(),
                    'end_date' => Carbon::now()->toDateString()
                ],
                'privacy_notice' => 'This data is aggregated across all organization members. Individual user data is not accessible for privacy and legal compliance.'
            ];

            return response()->json([
                'success' => true,
                'aggregate_data' => $aggregateData
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching organization aggregate data', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch organization data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
