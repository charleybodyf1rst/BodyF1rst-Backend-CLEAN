<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Challenge;
use App\Models\ChallengeParticipant;
use App\Models\ChallengeProgress;
use App\Models\User;
use Carbon\Carbon;

class ChallengeManagementController extends Controller
{
    /**
     * Get available challenges
     */
    public function getAvailableChallenges(Request $request)
    {
        try {
            $validated = $request->validate([
                'type' => 'nullable|string|in:fitness,nutrition,wellness,weight_loss,strength,endurance',
                'difficulty' => 'nullable|string|in:beginner,intermediate,advanced',
                'duration' => 'nullable|string|in:weekly,monthly,custom',
                'status' => 'nullable|string|in:upcoming,active,completed',
                'sort_by' => 'nullable|string|in:popularity,start_date,difficulty,reward',
                'page' => 'nullable|integer|min:1',
                'limit' => 'nullable|integer|min:1|max:50'
            ]);

            $query = Challenge::where('is_active', true);

            // Apply filters
            if (!empty($validated['type'])) {
                $query->where('type', $validated['type']);
            }

            if (!empty($validated['difficulty'])) {
                $query->where('difficulty', $validated['difficulty']);
            }

            if (!empty($validated['duration'])) {
                $query->where('duration_type', $validated['duration']);
            }

            // Status filter
            if (!empty($validated['status'])) {
                $now = Carbon::now();
                switch ($validated['status']) {
                    case 'upcoming':
                        $query->where('start_date', '>', $now);
                        break;
                    case 'active':
                        $query->where('start_date', '<=', $now)
                              ->where('end_date', '>=', $now);
                        break;
                    case 'completed':
                        $query->where('end_date', '<', $now);
                        break;
                }
            } else {
                // Default to active and upcoming
                $query->where('end_date', '>=', Carbon::now());
            }

            // Apply sorting
            switch ($validated['sort_by'] ?? 'start_date') {
                case 'popularity':
                    $query->withCount('participants')
                          ->orderBy('participants_count', 'desc');
                    break;
                case 'difficulty':
                    $query->orderByRaw("FIELD(difficulty, 'beginner', 'intermediate', 'advanced')");
                    break;
                case 'reward':
                    $query->orderBy('reward_points', 'desc');
                    break;
                default:
                    $query->orderBy('start_date', 'asc');
            }

            $limit = $validated['limit'] ?? 20;
            $challenges = $query->paginate($limit);

            // Add participation info for each challenge
            foreach ($challenges->items() as $challenge) {
                $challenge->participants_count = ChallengeParticipant::where('challenge_id', $challenge->id)->count();
                $challenge->user_participating = ChallengeParticipant::where('challenge_id', $challenge->id)
                    ->where('user_id', Auth::id())
                    ->exists();
                $challenge->days_remaining = Carbon::parse($challenge->end_date)->diffInDays(Carbon::now());
            }

            return response()->json([
                'success' => true,
                'challenges' => $challenges
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching challenges', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch challenges',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Join a challenge
     */
    public function joinChallenge(Request $request)
    {
        try {
            $validated = $request->validate([
                'challenge_id' => 'required|integer|exists:challenges,id',
                'goal_value' => 'nullable|numeric',
                'commitment_level' => 'nullable|string|in:casual,regular,serious'
            ]);

            DB::beginTransaction();

            $challenge = Challenge::findOrFail($validated['challenge_id']);
            $userId = Auth::id();

            // Check if already participating
            $existingParticipant = ChallengeParticipant::where('challenge_id', $challenge->id)
                ->where('user_id', $userId)
                ->first();

            if ($existingParticipant) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are already participating in this challenge'
                ], 400);
            }

            // Check if challenge has started or ended
            if (Carbon::parse($challenge->end_date)->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This challenge has already ended'
                ], 400);
            }

            // Check participant limit
            if ($challenge->max_participants) {
                $currentCount = ChallengeParticipant::where('challenge_id', $challenge->id)->count();
                if ($currentCount >= $challenge->max_participants) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Challenge is full'
                    ], 400);
                }
            }

            // Create participant entry
            $participant = ChallengeParticipant::create([
                'challenge_id' => $challenge->id,
                'user_id' => $userId,
                'joined_at' => now(),
                'goal_value' => $validated['goal_value'] ?? $challenge->default_goal,
                'commitment_level' => $validated['commitment_level'] ?? 'regular',
                'status' => 'active'
            ]);

            // Initialize progress tracking
            ChallengeProgress::create([
                'challenge_id' => $challenge->id,
                'user_id' => $userId,
                'current_value' => 0,
                'target_value' => $participant->goal_value,
                'percentage_complete' => 0,
                'last_updated' => now()
            ]);

            // Award joining points
            $this->awardPoints($userId, 'challenge_joined', 10);

            // Send notification
            $this->notifyUser($userId, 'challenge_joined', $challenge);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Successfully joined the challenge',
                'participant' => $participant,
                'challenge' => $challenge
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error joining challenge', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to join challenge',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update challenge progress
     */
    public function updateProgress(Request $request)
    {
        try {
            $validated = $request->validate([
                'challenge_id' => 'required|integer|exists:challenges,id',
                'progress_value' => 'required|numeric|min:0',
                'progress_type' => 'required|string|in:increment,absolute',
                'activity_data' => 'nullable|array',
                'notes' => 'nullable|string|max:500'
            ]);

            DB::beginTransaction();

            $userId = Auth::id();

            // Verify participation
            $participant = ChallengeParticipant::where('challenge_id', $validated['challenge_id'])
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->firstOrFail();

            // Get or create progress record
            $progress = ChallengeProgress::firstOrCreate(
                [
                    'challenge_id' => $validated['challenge_id'],
                    'user_id' => $userId
                ],
                [
                    'current_value' => 0,
                    'target_value' => $participant->goal_value,
                    'percentage_complete' => 0
                ]
            );

            // Update progress value
            if ($validated['progress_type'] === 'increment') {
                $newValue = $progress->current_value + $validated['progress_value'];
            } else {
                $newValue = $validated['progress_value'];
            }

            $progress->current_value = $newValue;
            $progress->percentage_complete = min(100, round(($newValue / $progress->target_value) * 100, 2));
            $progress->last_updated = now();
            $progress->save();

            // Log activity
            DB::table('challenge_activities')->insert([
                'challenge_id' => $validated['challenge_id'],
                'user_id' => $userId,
                'activity_type' => 'progress_update',
                'value' => $validated['progress_value'],
                'data' => json_encode($validated['activity_data'] ?? []),
                'notes' => $validated['notes'] ?? null,
                'created_at' => now()
            ]);

            // Check for milestones
            $milestones = $this->checkMilestones($progress);

            // Check if challenge completed
            if ($progress->percentage_complete >= 100 && $participant->status !== 'completed') {
                $this->completeChallenge($participant, $progress);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Progress updated successfully',
                'progress' => $progress,
                'milestones' => $milestones
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error updating progress', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update progress',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's active challenges
     */
    public function getMyActiveChallenges(Request $request)
    {
        try {
            $userId = Auth::id();

            $challenges = ChallengeParticipant::where('user_id', $userId)
                ->where('status', 'active')
                ->with(['challenge', 'progress'])
                ->get();

            $activeChallenges = [];
            foreach ($challenges as $participant) {
                $challenge = $participant->challenge;
                $progress = ChallengeProgress::where('challenge_id', $challenge->id)
                    ->where('user_id', $userId)
                    ->first();

                $activeChallenges[] = [
                    'challenge' => $challenge,
                    'participation' => [
                        'joined_at' => $participant->joined_at,
                        'goal_value' => $participant->goal_value,
                        'commitment_level' => $participant->commitment_level
                    ],
                    'progress' => [
                        'current_value' => $progress->current_value ?? 0,
                        'target_value' => $progress->target_value ?? $participant->goal_value,
                        'percentage_complete' => $progress->percentage_complete ?? 0,
                        'last_updated' => $progress->last_updated ?? null
                    ],
                    'ranking' => $this->getUserRanking($challenge->id, $userId),
                    'days_remaining' => Carbon::parse($challenge->end_date)->diffInDays(Carbon::now())
                ];
            }

            return response()->json([
                'success' => true,
                'active_challenges' => $activeChallenges,
                'total_active' => count($activeChallenges)
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching active challenges', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch active challenges',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get challenge leaderboard
     */
    public function getChallengeLeaderboard($challengeId)
    {
        try {
            $challenge = Challenge::findOrFail($challengeId);

            // Get all participants with progress
            $leaderboard = ChallengeProgress::where('challenge_id', $challengeId)
                ->join('users', 'challenge_progress.user_id', '=', 'users.id')
                ->select(
                    'challenge_progress.*',
                    'users.name',
                    'users.profile_picture'
                )
                ->orderBy('current_value', 'desc')
                ->limit(100)
                ->get();

            $rank = 1;
            $formattedLeaderboard = [];
            $userRank = null;

            foreach ($leaderboard as $entry) {
                $data = [
                    'rank' => $rank,
                    'user' => [
                        'id' => $entry->user_id,
                        'name' => $entry->name,
                        'profile_picture' => $entry->profile_picture
                    ],
                    'progress' => [
                        'current_value' => $entry->current_value,
                        'target_value' => $entry->target_value,
                        'percentage_complete' => $entry->percentage_complete
                    ],
                    'last_updated' => $entry->last_updated
                ];

                if ($entry->user_id == Auth::id()) {
                    $userRank = $rank;
                }

                $formattedLeaderboard[] = $data;
                $rank++;
            }

            return response()->json([
                'success' => true,
                'challenge' => [
                    'id' => $challenge->id,
                    'name' => $challenge->name,
                    'type' => $challenge->type
                ],
                'leaderboard' => $formattedLeaderboard,
                'user_rank' => $userRank,
                'total_participants' => ChallengeParticipant::where('challenge_id', $challengeId)->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching leaderboard', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch leaderboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Leave a challenge
     */
    public function leaveChallenge(Request $request)
    {
        try {
            $validated = $request->validate([
                'challenge_id' => 'required|integer|exists:challenges,id',
                'reason' => 'nullable|string|max:500'
            ]);

            DB::beginTransaction();

            $userId = Auth::id();

            $participant = ChallengeParticipant::where('challenge_id', $validated['challenge_id'])
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->firstOrFail();

            // Update participant status
            $participant->status = 'withdrawn';
            $participant->withdrawn_at = now();
            $participant->withdrawal_reason = $validated['reason'] ?? null;
            $participant->save();

            // Log activity
            DB::table('challenge_activities')->insert([
                'challenge_id' => $validated['challenge_id'],
                'user_id' => $userId,
                'activity_type' => 'withdrawn',
                'notes' => $validated['reason'] ?? null,
                'created_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'You have left the challenge'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error leaving challenge', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to leave challenge',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get challenge history
     */
    public function getChallengeHistory(Request $request)
    {
        try {
            $validated = $request->validate([
                'status' => 'nullable|string|in:completed,withdrawn,all',
                'type' => 'nullable|string',
                'page' => 'nullable|integer|min:1',
                'limit' => 'nullable|integer|min:1|max:50'
            ]);

            $userId = Auth::id();
            $query = ChallengeParticipant::where('user_id', $userId)
                ->with(['challenge', 'progress']);

            // Apply filters
            if (!empty($validated['status']) && $validated['status'] !== 'all') {
                $query->where('status', $validated['status']);
            } else {
                $query->whereIn('status', ['completed', 'withdrawn']);
            }

            if (!empty($validated['type'])) {
                $query->whereHas('challenge', function($q) use ($validated) {
                    $q->where('type', $validated['type']);
                });
            }

            $query->orderBy('joined_at', 'desc');

            $limit = $validated['limit'] ?? 20;
            $history = $query->paginate($limit);

            // Format history data
            $formattedHistory = [];
            foreach ($history->items() as $participant) {
                $progress = ChallengeProgress::where('challenge_id', $participant->challenge_id)
                    ->where('user_id', $userId)
                    ->first();

                $formattedHistory[] = [
                    'challenge' => $participant->challenge,
                    'participation' => [
                        'joined_at' => $participant->joined_at,
                        'completed_at' => $participant->completed_at,
                        'withdrawn_at' => $participant->withdrawn_at,
                        'status' => $participant->status,
                        'goal_value' => $participant->goal_value
                    ],
                    'final_progress' => [
                        'current_value' => $progress->current_value ?? 0,
                        'target_value' => $progress->target_value ?? $participant->goal_value,
                        'percentage_complete' => $progress->percentage_complete ?? 0
                    ],
                    'rewards_earned' => $participant->status === 'completed' ? $participant->rewards_earned : 0,
                    'final_rank' => $participant->final_rank ?? null
                ];
            }

            // Calculate stats
            $stats = [
                'total_challenges' => ChallengeParticipant::where('user_id', $userId)->count(),
                'completed_challenges' => ChallengeParticipant::where('user_id', $userId)
                    ->where('status', 'completed')->count(),
                'total_points_earned' => ChallengeParticipant::where('user_id', $userId)
                    ->where('status', 'completed')
                    ->sum('rewards_earned'),
                'success_rate' => $this->calculateSuccessRate($userId)
            ];

            return response()->json([
                'success' => true,
                'history' => $formattedHistory,
                'pagination' => [
                    'current_page' => $history->currentPage(),
                    'total_pages' => $history->lastPage(),
                    'total_items' => $history->total()
                ],
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching challenge history', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch challenge history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create custom challenge
     */
    public function createCustomChallenge(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:200',
                'description' => 'required|string|max:1000',
                'type' => 'required|string|in:fitness,nutrition,wellness,weight_loss,strength,endurance',
                'difficulty' => 'required|string|in:beginner,intermediate,advanced',
                'start_date' => 'required|date|after:today',
                'end_date' => 'required|date|after:start_date',
                'goal_type' => 'required|string|in:count,duration,distance,weight',
                'goal_value' => 'required|numeric|min:1',
                'goal_unit' => 'required|string|max:20',
                'is_public' => 'nullable|boolean',
                'max_participants' => 'nullable|integer|min:2|max:1000',
                'reward_points' => 'nullable|integer|min:0|max:1000'
            ]);

            DB::beginTransaction();

            // Create challenge
            $challenge = Challenge::create([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'type' => $validated['type'],
                'difficulty' => $validated['difficulty'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'goal_type' => $validated['goal_type'],
                'default_goal' => $validated['goal_value'],
                'goal_unit' => $validated['goal_unit'],
                'is_public' => $validated['is_public'] ?? true,
                'max_participants' => $validated['max_participants'] ?? null,
                'reward_points' => $validated['reward_points'] ?? 100,
                'created_by' => Auth::id(),
                'is_active' => true,
                'duration_type' => 'custom'
            ]);

            // Auto-join creator
            ChallengeParticipant::create([
                'challenge_id' => $challenge->id,
                'user_id' => Auth::id(),
                'joined_at' => now(),
                'goal_value' => $validated['goal_value'],
                'commitment_level' => 'serious',
                'status' => 'active',
                'is_organizer' => true
            ]);

            // Initialize progress
            ChallengeProgress::create([
                'challenge_id' => $challenge->id,
                'user_id' => Auth::id(),
                'current_value' => 0,
                'target_value' => $validated['goal_value'],
                'percentage_complete' => 0,
                'last_updated' => now()
            ]);

            // Award points for creating challenge
            $this->awardPoints(Auth::id(), 'challenge_created', 25);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Custom challenge created successfully',
                'challenge' => $challenge
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error creating custom challenge', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create custom challenge',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Private helper methods
     */
    private function checkMilestones($progress)
    {
        $milestones = [];
        $percentages = [25, 50, 75, 100];

        foreach ($percentages as $percent) {
            if ($progress->percentage_complete >= $percent &&
                $progress->percentage_complete - $progress->last_milestone < $percent) {
                $milestones[] = "{$percent}% Complete!";

                // Award milestone points
                $this->awardPoints($progress->user_id, "milestone_{$percent}", $percent / 4);
            }
        }

        return $milestones;
    }

    private function completeChallenge($participant, $progress)
    {
        $participant->status = 'completed';
        $participant->completed_at = now();
        $participant->final_rank = $this->getUserRanking($participant->challenge_id, $participant->user_id);
        $participant->rewards_earned = $participant->challenge->reward_points;
        $participant->save();

        // Award completion points
        $this->awardPoints($participant->user_id, 'challenge_completed', $participant->challenge->reward_points);

        // Send notification
        $this->notifyUser($participant->user_id, 'challenge_completed', $participant->challenge);
    }

    private function getUserRanking($challengeId, $userId)
    {
        $rank = ChallengeProgress::where('challenge_id', $challengeId)
            ->where('current_value', '>', function($query) use ($challengeId, $userId) {
                $query->select('current_value')
                    ->from('challenge_progress')
                    ->where('challenge_id', $challengeId)
                    ->where('user_id', $userId);
            })
            ->count() + 1;

        return $rank;
    }

    private function calculateSuccessRate($userId)
    {
        $total = ChallengeParticipant::where('user_id', $userId)
            ->whereIn('status', ['completed', 'withdrawn'])
            ->count();

        if ($total == 0) return 0;

        $completed = ChallengeParticipant::where('user_id', $userId)
            ->where('status', 'completed')
            ->count();

        return round(($completed / $total) * 100, 2);
    }

    private function awardPoints($userId, $action, $points)
    {
        try {
            $user = User::find($userId);
            if ($user) {
                $user->increment('body_points', $points);

                DB::table('point_logs')->insert([
                    'user_id' => $userId,
                    'action' => $action,
                    'points' => $points,
                    'created_at' => now()
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to award points', ['error' => $e->getMessage()]);
        }
    }

    private function notifyUser($userId, $type, $data)
    {
        try {
            DB::table('notifications')->insert([
                'user_id' => $userId,
                'type' => $type,
                'data' => json_encode($data),
                'created_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to send notification', ['error' => $e->getMessage()]);
        }

    /**
     * Invite a friend to join a challenge
     * POST /api/challenges/{challengeId}/invite
     */
    public function inviteFriend($challengeId, Request $request)
    {
        $validated = $request->validate([
            'friend_id' => 'required|exists:users,id',
            'message' => 'nullable|string|max:500'
        ]);

        $user = Auth::user();

        try {
            // Check if challenge exists
            $challenge = DB::table('challenges')->find($challengeId);
            if (!$challenge) {
                return response()->json([
                    'success' => false,
                    'message' => 'Challenge not found'
                ], 404);
            }

            // Check if user is participating in the challenge
            $isParticipant = DB::table('challenge_participants')
                ->where('challenge_id', $challengeId)
                ->where('user_id', $user->id)
                ->exists();

            if (!$isParticipant) {
                return response()->json([
                    'success' => false,
                    'message' => 'You must be participating in this challenge to invite others'
                ], 403);
            }

            // Check if friend already invited or participating
            $alreadyInvited = DB::table('challenge_invites')
                ->where('challenge_id', $challengeId)
                ->where('invited_user_id', $validated['friend_id'])
                ->where('status', 'pending')
                ->exists();

            if ($alreadyInvited) {
                return response()->json([
                    'success' => false,
                    'message' => 'Friend already invited to this challenge'
                ], 409);
            }

            $alreadyParticipating = DB::table('challenge_participants')
                ->where('challenge_id', $challengeId)
                ->where('user_id', $validated['friend_id'])
                ->exists();

            if ($alreadyParticipating) {
                return response()->json([
                    'success' => false,
                    'message' => 'Friend is already participating in this challenge'
                ], 409);
            }

            // Create invitation
            DB::table('challenge_invites')->insert([
                'challenge_id' => $challengeId,
                'inviter_id' => $user->id,
                'invited_user_id' => $validated['friend_id'],
                'message' => $validated['message'] ?? null,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Invitation sent successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send invitation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recommended challenges based on user level, interests, and activity
     * GET /api/challenges/recommended
     */
    public function getRecommended()
    {
        $user = Auth::user();

        try {
            // Get user's fitness level and interests
            $userProfile = DB::table('user_profiles')->where('user_id', $user->id)->first();
            $fitnessLevel = $userProfile->fitness_level ?? 'beginner';

            // Get challenges user is already participating in
            $participatingChallengeIds = DB::table('challenge_participants')
                ->where('user_id', $user->id)
                ->pluck('challenge_id')
                ->toArray();

            // Get recommended challenges
            $recommendations = DB::table('challenges')
                ->select('challenges.*',
                    DB::raw('(SELECT COUNT(*) FROM challenge_participants WHERE challenge_id = challenges.id) as participant_count'))
                ->where('status', 'active')
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->whereNotIn('id', $participatingChallengeIds)
                ->where(function($query) use ($fitnessLevel) {
                    $query->where('difficulty_level', $fitnessLevel)
                          ->orWhere('difficulty_level', 'all_levels');
                })
                ->orderByRaw('
                    CASE
                        WHEN difficulty_level = ? THEN 1
                        ELSE 2
                    END', [$fitnessLevel])
                ->orderBy('participant_count', 'desc')
                ->limit(10)
                ->get()
                ->map(function($challenge) use ($fitnessLevel) {
                    return [
                        'id' => $challenge->id,
                        'title' => $challenge->title,
                        'description' => $challenge->description,
                        'difficulty_level' => $challenge->difficulty_level,
                        'start_date' => $challenge->start_date,
                        'end_date' => $challenge->end_date,
                        'participant_count' => $challenge->participant_count,
                        'reason' => $challenge->difficulty_level == $fitnessLevel
                            ? 'Matches your fitness level'
                            : 'Popular challenge',
                        'image' => $challenge->image_url ?? null
                    ];
                });

            return response()->json([
                'success' => true,
                'recommendations' => $recommendations,
                'count' => $recommendations->count(),
                'user_level' => $fitnessLevel
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get recommendations: ' . $e->getMessage()
            ], 500);
        }
    }
}
