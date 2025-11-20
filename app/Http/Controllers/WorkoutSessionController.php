<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSession;
use App\Models\ExerciseSet;
use App\Models\WorkoutHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class WorkoutSessionController extends Controller
{
    /**
     * Save a workout (quick save)
     */
    public function saveWorkout(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'exercises' => 'required|array',
                'exercises.*.exercise_id' => 'required|integer',
                'exercises.*.sets' => 'required|integer',
                'exercises.*.reps' => 'required|integer',
                'exercises.*.weight' => 'nullable|numeric',
                'exercises.*.duration' => 'nullable|integer',
                'exercises.*.rest' => 'nullable|integer',
                'type' => 'nullable|string|in:strength,cardio,hiit,flexibility,mixed',
                'difficulty' => 'nullable|string|in:beginner,intermediate,advanced',
                'duration_minutes' => 'nullable|integer',
            ]);

            DB::beginTransaction();

            // Create or update workout
            $workout = Workout::updateOrCreate(
                [
                    'user_id' => Auth::id(),
                    'name' => $validated['name']
                ],
                [
                    'description' => $validated['description'] ?? '',
                    'type' => $validated['type'] ?? 'mixed',
                    'difficulty' => $validated['difficulty'] ?? 'intermediate',
                    'duration_minutes' => $validated['duration_minutes'] ?? 60,
                    'is_active' => true
                ]
            );

            // Save exercises
            $workout->exercises()->sync([]);
            foreach ($validated['exercises'] as $index => $exercise) {
                $workout->exercises()->attach($exercise['exercise_id'], [
                    'sets' => $exercise['sets'],
                    'reps' => $exercise['reps'],
                    'weight' => $exercise['weight'] ?? null,
                    'duration' => $exercise['duration'] ?? null,
                    'rest' => $exercise['rest'] ?? null,
                    'order' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Workout saved successfully',
                'workout' => $workout->load('exercises')
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to save workout',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent workouts for the authenticated user
     */
    public function getRecentWorkouts(Request $request)
    {
        try {
            $limit = $request->get('limit', 10);
            $userId = Auth::id();

            // Get recent workout sessions
            $recentSessions = WorkoutSession::where('user_id', $userId)
                ->with(['workout', 'workout.exercises', 'exerciseSets'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            // Get recent workout templates
            $recentTemplates = Workout::where('user_id', $userId)
                ->orWhere('created_by', $userId)
                ->with('exercises')
                ->orderBy('updated_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'recent_sessions' => $recentSessions,
                'recent_templates' => $recentTemplates,
                'total_workouts' => WorkoutSession::where('user_id', $userId)->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recent workouts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save exercise sets during workout
     */
    public function saveExerciseSets(Request $request)
    {
        try {
            $validated = $request->validate([
                'session_id' => 'required|integer|exists:workout_sessions,id',
                'exercise_id' => 'required|integer|exists:exercises,id',
                'sets' => 'required|array',
                'sets.*.set_number' => 'required|integer|min:1',
                'sets.*.reps' => 'required|integer|min:0',
                'sets.*.weight' => 'nullable|numeric|min:0',
                'sets.*.duration' => 'nullable|integer|min:0',
                'sets.*.distance' => 'nullable|numeric|min:0',
                'sets.*.calories' => 'nullable|numeric|min:0',
                'sets.*.notes' => 'nullable|string|max:500',
                'sets.*.completed' => 'boolean'
            ]);

            DB::beginTransaction();

            // Verify session belongs to user
            $session = WorkoutSession::where('id', $validated['session_id'])
                ->where('user_id', Auth::id())
                ->firstOrFail();

            // Delete existing sets for this exercise in this session
            ExerciseSet::where('session_id', $validated['session_id'])
                ->where('exercise_id', $validated['exercise_id'])
                ->delete();

            // Save new sets
            $savedSets = [];
            foreach ($validated['sets'] as $setData) {
                $set = ExerciseSet::create([
                    'session_id' => $validated['session_id'],
                    'exercise_id' => $validated['exercise_id'],
                    'user_id' => Auth::id(),
                    'set_number' => $setData['set_number'],
                    'reps' => $setData['reps'],
                    'weight' => $setData['weight'] ?? null,
                    'duration' => $setData['duration'] ?? null,
                    'distance' => $setData['distance'] ?? null,
                    'calories' => $setData['calories'] ?? null,
                    'notes' => $setData['notes'] ?? null,
                    'completed' => $setData['completed'] ?? false,
                    'completed_at' => ($setData['completed'] ?? false) ? now() : null
                ]);
                $savedSets[] = $set;
            }

            // Update session progress
            $this->updateSessionProgress($session);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Exercise sets saved successfully',
                'sets' => $savedSets,
                'session_progress' => $session->fresh()->progress_percentage
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to save exercise sets',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save workout session
     */
    public function saveWorkoutSession(Request $request)
    {
        try {
            $validated = $request->validate([
                'workout_id' => 'required|integer|exists:workouts,id',
                'started_at' => 'required|date',
                'completed_at' => 'nullable|date',
                'duration_minutes' => 'nullable|integer|min:0',
                'calories_burned' => 'nullable|integer|min:0',
                'notes' => 'nullable|string|max:1000',
                'status' => 'nullable|string|in:in_progress,completed,paused,cancelled',
                'exercises' => 'nullable|array',
                'exercises.*.exercise_id' => 'required|integer',
                'exercises.*.sets' => 'required|array'
            ]);

            DB::beginTransaction();

            // Create or update session
            $session = WorkoutSession::updateOrCreate(
                [
                    'user_id' => Auth::id(),
                    'workout_id' => $validated['workout_id'],
                    'started_at' => $validated['started_at']
                ],
                [
                    'completed_at' => $validated['completed_at'] ?? null,
                    'duration_minutes' => $validated['duration_minutes'] ?? null,
                    'calories_burned' => $validated['calories_burned'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'status' => $validated['status'] ?? 'in_progress'
                ]
            );

            // Save exercise sets if provided
            if (!empty($validated['exercises'])) {
                foreach ($validated['exercises'] as $exerciseData) {
                    foreach ($exerciseData['sets'] as $setData) {
                        ExerciseSet::updateOrCreate(
                            [
                                'session_id' => $session->id,
                                'exercise_id' => $exerciseData['exercise_id'],
                                'set_number' => $setData['set_number'] ?? 1
                            ],
                            [
                                'user_id' => Auth::id(),
                                'reps' => $setData['reps'] ?? 0,
                                'weight' => $setData['weight'] ?? null,
                                'completed' => $setData['completed'] ?? false,
                                'completed_at' => ($setData['completed'] ?? false) ? now() : null
                            ]
                        );
                    }
                }
            }

            // Update session progress
            $this->updateSessionProgress($session);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Workout session saved successfully',
                'session' => $session->load(['workout', 'exerciseSets'])
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to save workout session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get workout history for the authenticated user
     */
    public function getWorkoutHistory(Request $request)
    {
        try {
            $validated = $request->validate([
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date',
                'workout_id' => 'nullable|integer',
                'status' => 'nullable|string|in:completed,in_progress,paused,cancelled',
                'limit' => 'nullable|integer|min:1|max:100',
                'page' => 'nullable|integer|min:1'
            ]);

            $query = WorkoutSession::where('user_id', Auth::id())
                ->with(['workout', 'workout.exercises', 'exerciseSets']);

            // Apply filters
            if (!empty($validated['start_date'])) {
                $query->where('started_at', '>=', $validated['start_date']);
            }

            if (!empty($validated['end_date'])) {
                $query->where('started_at', '<=', $validated['end_date']);
            }

            if (!empty($validated['workout_id'])) {
                $query->where('workout_id', $validated['workout_id']);
            }

            if (!empty($validated['status'])) {
                $query->where('status', $validated['status']);
            }

            // Order by most recent
            $query->orderBy('started_at', 'desc');

            // Paginate results
            $limit = $validated['limit'] ?? 20;
            $history = $query->paginate($limit);

            // Calculate statistics
            $stats = [
                'total_workouts' => WorkoutSession::where('user_id', Auth::id())->count(),
                'completed_workouts' => WorkoutSession::where('user_id', Auth::id())
                    ->where('status', 'completed')->count(),
                'total_duration' => WorkoutSession::where('user_id', Auth::id())
                    ->sum('duration_minutes'),
                'total_calories' => WorkoutSession::where('user_id', Auth::id())
                    ->sum('calories_burned'),
                'current_streak' => $this->calculateStreak(Auth::id()),
                'favorite_workout' => $this->getFavoriteWorkout(Auth::id())
            ];

            return response()->json([
                'success' => true,
                'history' => $history,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch workout history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark workout as complete
     */
    public function completeWorkout(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'duration_minutes' => 'nullable|integer|min:0',
                'calories_burned' => 'nullable|integer|min:0',
                'notes' => 'nullable|string|max:1000',
                'rating' => 'nullable|integer|min:1|max:5',
                'difficulty_feedback' => 'nullable|string|in:too_easy,just_right,too_hard'
            ]);

            DB::beginTransaction();

            // Find the session
            $session = WorkoutSession::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            // Update session as completed
            $session->update([
                'status' => 'completed',
                'completed_at' => now(),
                'duration_minutes' => $validated['duration_minutes'] ??
                    Carbon::parse($session->started_at)->diffInMinutes(now()),
                'calories_burned' => $validated['calories_burned'] ?? $session->calories_burned,
                'notes' => $validated['notes'] ?? $session->notes,
                'rating' => $validated['rating'] ?? null,
                'difficulty_feedback' => $validated['difficulty_feedback'] ?? null
            ]);

            // Create history entry
            WorkoutHistory::create([
                'user_id' => Auth::id(),
                'workout_id' => $session->workout_id,
                'session_id' => $session->id,
                'completed_at' => now(),
                'duration_minutes' => $session->duration_minutes,
                'calories_burned' => $session->calories_burned,
                'exercises_completed' => $session->exerciseSets()
                    ->where('completed', true)->count(),
                'total_exercises' => $session->exerciseSets()->count(),
                'rating' => $validated['rating'] ?? null
            ]);

            // Update user stats
            $this->updateUserStats(Auth::id());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Workout completed successfully',
                'session' => $session->fresh()->load(['workout', 'exerciseSets']),
                'achievements' => $this->checkAchievements(Auth::id())
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete workout',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Private helper methods
     */
    private function updateSessionProgress($session)
    {
        $totalExercises = $session->workout->exercises->count();
        $completedExercises = $session->exerciseSets()
            ->select('exercise_id')
            ->where('completed', true)
            ->distinct()
            ->count();

        $progress = $totalExercises > 0 ?
            round(($completedExercises / $totalExercises) * 100) : 0;

        $session->update(['progress_percentage' => $progress]);
    }

    private function calculateStreak($userId)
    {
        $sessions = WorkoutSession::where('user_id', $userId)
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->pluck('completed_at');

        $streak = 0;
        $lastDate = null;

        foreach ($sessions as $date) {
            $date = Carbon::parse($date)->startOfDay();

            if ($lastDate === null) {
                $streak = 1;
                $lastDate = $date;
            } elseif ($lastDate->diffInDays($date) === 1) {
                $streak++;
                $lastDate = $date;
            } else {
                break;
            }
        }

        return $streak;
    }

    private function getFavoriteWorkout($userId)
    {
        return WorkoutSession::where('user_id', $userId)
            ->where('status', 'completed')
            ->select('workout_id', DB::raw('COUNT(*) as count'))
            ->groupBy('workout_id')
            ->orderBy('count', 'desc')
            ->with('workout:id,name')
            ->first();
    }

    private function updateUserStats($userId)
    {
        // Update user's workout stats (implement based on your user model)
        $user = User::find($userId);
        if ($user) {
            $user->increment('total_workouts_completed');
            $user->update(['last_workout_at' => now()]);
        }
    }

    private function checkAchievements($userId)
    {
        $achievements = [];
        $stats = WorkoutSession::where('user_id', $userId)
            ->where('status', 'completed')
            ->selectRaw('COUNT(*) as total, SUM(duration_minutes) as total_duration')
            ->first();

        if ($stats->total == 1) {
            $achievements[] = 'First Workout Complete!';
        }
        if ($stats->total == 10) {
            $achievements[] = '10 Workouts Milestone!';
        }
        if ($stats->total == 50) {
            $achievements[] = '50 Workouts Champion!';
        }
        if ($stats->total == 100) {
            $achievements[] = '100 Workouts Legend!';
        }
        if ($stats->total_duration >= 1000) {
            $achievements[] = '1000 Minutes of Training!';
        }

        return $achievements;
    }
}