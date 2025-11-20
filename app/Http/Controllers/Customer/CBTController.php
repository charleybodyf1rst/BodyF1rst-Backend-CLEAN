<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CBTController extends Controller
{
    // ========== PROGRESS & DASHBOARD ==========

    /**
     * Get CBT progress for authenticated user
     * GET /api/customer/cbt/progress
     */
    public function getProgress(Request $request)
    {
        try {
            $userId = auth()->id();

            $progress = DB::table('cbt_progress')->where('user_id', $userId)->first();

            if (!$progress) {
                // Create initial progress record
                DB::table('cbt_progress')->insert([
                    'user_id' => $userId,
                    'current_week' => 1,
                    'completed_lessons' => 0,
                    'total_lessons' => 40,
                    'week_progress' => 0,
                    'overall_progress' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $progress = DB::table('cbt_progress')->where('user_id', $userId)->first();
            }

            return response()->json([
                'success' => true,
                'data' => $progress
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => [
                    'current_week' => 1,
                    'completed_lessons' => 0,
                    'total_lessons' => 40,
                    'week_progress' => 0,
                    'overall_progress' => 0
                ],
                'message' => 'CBT progress table may not exist yet'
            ]);
        }
    }

    /**
     * Get CBT dashboard
     * GET /api/customer/cbt/dashboard
     */
    public function getDashboard(Request $request)
    {
        try {
            $userId = auth()->id();

            $progress = DB::table('cbt_progress')->where('user_id', $userId)->first();
            $recentJournals = DB::table('cbt_journal_entries')
                ->where('user_id', $userId)
                ->orderBy('entry_date', 'desc')
                ->limit(5)
                ->get();
            $upcomingGoals = DB::table('cbt_goals')
                ->where('user_id', $userId)
                ->where('completed', false)
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'progress' => $progress,
                    'recent_journals' => $recentJournals,
                    'upcoming_goals' => $upcomingGoals
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => [
                    'progress' => null,
                    'recent_journals' => [],
                    'upcoming_goals' => []
                ]
            ]);
        }
    }

    /**
     * Get CBT stats
     * GET /api/customer/cbt/stats
     */
    public function getStats(Request $request)
    {
        try {
            $userId = auth()->id();

            $stats = [
                'total_lessons_completed' => DB::table('cbt_lesson_completions')
                    ->where('user_id', $userId)->count(),
                'journal_entries' => DB::table('cbt_journal_entries')
                    ->where('user_id', $userId)->count(),
                'completed_assessments' => DB::table('cbt_assessment_submissions')
                    ->where('user_id', $userId)->count(),
                'active_goals' => DB::table('cbt_goals')
                    ->where('user_id', $userId)
                    ->where('completed', false)->count(),
                'current_streak' => 0, // TODO: Calculate streak
                'videos_watched' => DB::table('cbt_video_views')
                    ->where('user_id', $userId)->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => [
                    'total_lessons_completed' => 0,
                    'journal_entries' => 0,
                    'completed_assessments' => 0,
                    'active_goals' => 0,
                    'current_streak' => 0,
                    'videos_watched' => 0
                ]
            ]);
        }
    }

    // ========== LESSONS ==========

    /**
     * Get current week's lessons
     * GET /api/customer/cbt/lessons/current-week
     */
    public function getCurrentWeekLessons(Request $request)
    {
        try {
            $userId = auth()->id();
            $progress = DB::table('cbt_progress')->where('user_id', $userId)->first();

            $currentWeek = $progress->current_week ?? 1;

            $lessons = DB::table('cbt_lessons')
                ->where('week_number', $currentWeek)
                ->orderBy('day_number')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'week' => $currentWeek,
                    'lessons' => $lessons
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => [
                    'week' => 1,
                    'lessons' => []
                ]
            ]);
        }
    }

    /**
     * Get specific lesson
     * GET /api/customer/cbt/lessons/{id}
     */
    public function getLesson($id)
    {
        try {
            $lesson = DB::table('cbt_lessons')->find($id);

            if (!$lesson) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lesson not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $lesson
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching lesson'
            ], 500);
        }
    }

    /**
     * Complete a lesson
     * POST /api/customer/cbt/lessons/{id}/complete
     */
    public function completeLesson(Request $request, $id)
    {
        try {
            $userId = auth()->id();

            // Check if lesson exists
            $lesson = DB::table('cbt_lessons')->find($id);
            if (!$lesson) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lesson not found'
                ], 404);
            }

            // Mark lesson as complete
            DB::table('cbt_lesson_completions')->updateOrInsert(
                ['user_id' => $userId, 'lesson_id' => $id],
                [
                    'completed_at' => now(),
                    'notes' => $request->input('notes'),
                    'updated_at' => now()
                ]
            );

            // Update progress
            $progress = DB::table('cbt_progress')->where('user_id', $userId)->first();
            if ($progress) {
                $completedCount = DB::table('cbt_lesson_completions')->where('user_id', $userId)->count();
                $totalLessons = $progress->total_lessons;
                $overallProgress = ($completedCount / $totalLessons) * 100;

                DB::table('cbt_progress')->where('user_id', $userId)->update([
                    'completed_lessons' => $completedCount,
                    'overall_progress' => $overallProgress,
                    'updated_at' => now()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lesson completed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error completing lesson',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get lessons for a specific week
     * GET /api/customer/cbt/lessons/week/{weekNumber}
     */
    public function getWeekLessons($weekNumber)
    {
        try {
            $lessons = DB::table('cbt_lessons')
                ->where('week_number', $weekNumber)
                ->orderBy('day_number')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'week' => $weekNumber,
                    'lessons' => $lessons
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => [
                    'week' => $weekNumber,
                    'lessons' => []
                ]
            ]);
        }
    }

    // ========== JOURNAL ==========

    /**
     * Get journal entries
     * GET /api/customer/cbt/journal/entries
     */
    public function getJournalEntries(Request $request)
    {
        try {
            $userId = auth()->id();
            $limit = $request->get('limit', 20);

            $entries = DB::table('cbt_journal_entries')
                ->where('user_id', $userId)
                ->orderBy('entry_date', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $entries
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }
    }

    /**
     * Create journal entry
     * POST /api/customer/cbt/journal/entries
     */
    public function createJournalEntry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'content' => 'required|string',
            'entry_date' => 'required|date',
            'mood' => 'nullable|string|max:50',
            'tags' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = auth()->id();

            $entryId = DB::table('cbt_journal_entries')->insertGetId([
                'user_id' => $userId,
                'title' => $request->title,
                'content' => $request->content,
                'entry_date' => $request->entry_date,
                'mood' => $request->mood,
                'tags' => json_encode($request->tags ?? []),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Journal entry created successfully',
                'data' => ['id' => $entryId]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating journal entry',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single journal entry
     * GET /api/customer/cbt/journal/entries/{id}
     */
    public function getJournalEntry($id)
    {
        try {
            $userId = auth()->id();
            $entry = DB::table('cbt_journal_entries')
                ->where('id', $id)
                ->where('user_id', $userId)
                ->first();

            if (!$entry) {
                return response()->json([
                    'success' => false,
                    'message' => 'Journal entry not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $entry
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching journal entry'
            ], 500);
        }
    }

    /**
     * Update journal entry
     * PUT /api/customer/cbt/journal/entries/{id}
     */
    public function updateJournalEntry(Request $request, $id)
    {
        try {
            $userId = auth()->id();

            DB::table('cbt_journal_entries')
                ->where('id', $id)
                ->where('user_id', $userId)
                ->update([
                    'title' => $request->title,
                    'content' => $request->content,
                    'mood' => $request->mood,
                    'tags' => json_encode($request->tags ?? []),
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Journal entry updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating journal entry'
            ], 500);
        }
    }

    /**
     * Delete journal entry
     * DELETE /api/customer/cbt/journal/entries/{id}
     */
    public function deleteJournalEntry($id)
    {
        try {
            $userId = auth()->id();

            DB::table('cbt_journal_entries')
                ->where('id', $id)
                ->where('user_id', $userId)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Journal entry deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting journal entry'
            ], 500);
        }
    }

    // ========== ASSESSMENTS ==========

    /**
     * Get assessments
     * GET /api/customer/cbt/assessments
     */
    public function getAssessments(Request $request)
    {
        try {
            $assessments = DB::table('cbt_assessments')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $assessments
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }
    }

    /**
     * Submit assessment
     * POST /api/customer/cbt/assessments
     */
    public function submitAssessment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'assessment_id' => 'required|integer',
            'answers' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = auth()->id();

            $submissionId = DB::table('cbt_assessment_submissions')->insertGetId([
                'user_id' => $userId,
                'assessment_id' => $request->assessment_id,
                'answers' => json_encode($request->answers),
                'score' => $this->calculateScore($request->answers),
                'submitted_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Assessment submitted successfully',
                'data' => ['id' => $submissionId]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting assessment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get assessment results
     * GET /api/customer/cbt/assessments/{id}/results
     */
    public function getAssessmentResults($id)
    {
        try {
            $userId = auth()->id();

            $results = DB::table('cbt_assessment_submissions')
                ->where('assessment_id', $id)
                ->where('user_id', $userId)
                ->orderBy('submitted_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }
    }

    // ========== GOALS ==========

    /**
     * Get goals
     * GET /api/customer/cbt/goals
     */
    public function getGoals(Request $request)
    {
        try {
            $userId = auth()->id();

            $goals = DB::table('cbt_goals')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $goals
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }
    }

    /**
     * Create goal
     * POST /api/customer/cbt/goals
     */
    public function createGoal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'target_date' => 'nullable|date',
            'category' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = auth()->id();

            $goalId = DB::table('cbt_goals')->insertGetId([
                'user_id' => $userId,
                'title' => $request->title,
                'description' => $request->description,
                'target_date' => $request->target_date,
                'category' => $request->category,
                'completed' => false,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Goal created successfully',
                'data' => ['id' => $goalId]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating goal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update goal
     * PUT /api/customer/cbt/goals/{id}
     */
    public function updateGoal(Request $request, $id)
    {
        try {
            $userId = auth()->id();

            DB::table('cbt_goals')
                ->where('id', $id)
                ->where('user_id', $userId)
                ->update([
                    'title' => $request->title,
                    'description' => $request->description,
                    'target_date' => $request->target_date,
                    'category' => $request->category,
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Goal updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating goal'
            ], 500);
        }
    }

    /**
     * Delete goal
     * DELETE /api/customer/cbt/goals/{id}
     */
    public function deleteGoal($id)
    {
        try {
            $userId = auth()->id();

            DB::table('cbt_goals')
                ->where('id', $id)
                ->where('user_id', $userId)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Goal deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting goal'
            ], 500);
        }
    }

    // ========== COURSE HUB & VIDEOS ==========

    /**
     * Get course hub
     * GET /api/customer/cbt/course-hub
     */
    public function getCourseHub(Request $request)
    {
        try {
            $userId = auth()->id();
            $progress = DB::table('cbt_progress')->where('user_id', $userId)->first();

            $data = [
                'progress' => $progress,
                'featured_videos' => DB::table('cbt_videos')
                    ->where('featured', true)
                    ->limit(5)
                    ->get(),
                'recent_lessons' => DB::table('cbt_lessons')
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => [
                    'progress' => null,
                    'featured_videos' => [],
                    'recent_lessons' => []
                ]
            ]);
        }
    }

    /**
     * Get course videos
     * GET /api/customer/cbt/course-hub/videos
     */
    public function getCourseVideos(Request $request)
    {
        try {
            $videos = DB::table('cbt_videos')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $videos
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }
    }

    // ========== CHECK-INS ==========

    /**
     * Get check-ins
     * GET /api/customer/cbt/check-ins
     */
    public function getCheckIns(Request $request)
    {
        try {
            $userId = auth()->id();

            $checkIns = DB::table('cbt_check_ins')
                ->where('user_id', $userId)
                ->orderBy('check_in_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $checkIns
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }
    }

    /**
     * Submit check-in
     * POST /api/customer/cbt/check-ins
     */
    public function submitCheckIn(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'check_in_date' => 'required|date',
            'mood' => 'nullable|integer|min:1|max:10',
            'notes' => 'nullable|string',
            'responses' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = auth()->id();

            $checkInId = DB::table('cbt_check_ins')->insertGetId([
                'user_id' => $userId,
                'check_in_date' => $request->check_in_date,
                'mood' => $request->mood,
                'notes' => $request->notes,
                'responses' => json_encode($request->responses ?? []),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Check-in submitted successfully',
                'data' => ['id' => $checkInId]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting check-in',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ========== HELPER METHODS ==========

    /**
     * Calculate assessment score
     */
    private function calculateScore($answers)
    {
        // TODO: Implement proper scoring logic based on assessment type
        return 0;
    }
}
