<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\CBTLesson;
use App\Models\CBTProgress;
use App\Models\CBTJournal;
use App\Models\CBTAssessment;
use App\Models\CBTGoal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class CBTController extends Controller
{
    /**
     * Get user's CBT progress
     */
    public function getCBTProgress(Request $request)
    {
        $userId = Auth::id();

        $progress = [
            'status' => 200,
            'data' => [
                'current_week' => 1,
                'total_weeks' => 12,
                'lessons_completed' => 3,
                'total_lessons' => 36,
                'current_streak' => 5,
                'best_streak' => 12,
                'last_activity' => Carbon::now()->subDays(1),
                'completion_percentage' => 25,
                'achievements' => [
                    'first_week' => true,
                    'consistent_practice' => false,
                    'journal_master' => false
                ]
            ]
        ];

        return response()->json($progress);
    }

    /**
     * Get current week's lessons
     */
    public function getCurrentWeekLessons(Request $request)
    {
        $userId = Auth::id();

        $lessons = [
            'status' => 200,
            'data' => [
                [
                    'id' => 'lesson-1',
                    'week_number' => 1,
                    'lesson_number' => 1,
                    'title' => 'Introduction to CBT',
                    'description' => 'Learn the basics of Cognitive Behavioral Therapy',
                    'duration_minutes' => 15,
                    'completed' => false,
                    'locked' => false
                ],
                [
                    'id' => 'lesson-2',
                    'week_number' => 1,
                    'lesson_number' => 2,
                    'title' => 'Identifying Thoughts',
                    'description' => 'Learn to identify automatic thoughts',
                    'duration_minutes' => 20,
                    'completed' => false,
                    'locked' => false
                ],
                [
                    'id' => 'lesson-3',
                    'week_number' => 1,
                    'lesson_number' => 3,
                    'title' => 'Thought Records',
                    'description' => 'Practice keeping thought records',
                    'duration_minutes' => 25,
                    'completed' => false,
                    'locked' => true
                ]
            ]
        ];

        return response()->json($lessons);
    }

    /**
     * Get all lessons
     */
    public function getAllLessons(Request $request)
    {
        $lessons = [
            'status' => 200,
            'data' => []
        ];

        // Generate 12 weeks of lessons (3 per week)
        for ($week = 1; $week <= 12; $week++) {
            for ($lesson = 1; $lesson <= 3; $lesson++) {
                $lessons['data'][] = [
                    'id' => "lesson-{$week}-{$lesson}",
                    'week_number' => $week,
                    'lesson_number' => $lesson,
                    'title' => "Week {$week} Lesson {$lesson}",
                    'description' => 'CBT lesson content',
                    'duration_minutes' => rand(15, 30),
                    'completed' => false,
                    'locked' => ($week > 1)
                ];
            }
        }

        return response()->json($lessons);
    }

    /**
     * Get specific lesson details
     */
    public function getLesson(Request $request, $id)
    {
        $lesson = [
            'status' => 200,
            'data' => [
                'id' => $id,
                'week_number' => 1,
                'lesson_number' => 1,
                'title' => 'Introduction to CBT',
                'description' => 'Learn the basics of Cognitive Behavioral Therapy',
                'duration_minutes' => 15,
                'content' => [
                    'sections' => [
                        [
                            'type' => 'text',
                            'content' => 'Welcome to Cognitive Behavioral Therapy...'
                        ],
                        [
                            'type' => 'video',
                            'url' => 'https://example.com/video.mp4',
                            'duration' => '5:30'
                        ],
                        [
                            'type' => 'exercise',
                            'title' => 'Identify Your Thoughts',
                            'instructions' => 'Take a moment to identify...'
                        ]
                    ]
                ],
                'exercises' => [
                    [
                        'id' => 'ex-1',
                        'title' => 'Thought Identification',
                        'type' => 'multiple_choice',
                        'required' => true
                    ],
                    [
                        'id' => 'ex-2',
                        'title' => 'Journal Entry',
                        'type' => 'text',
                        'required' => false
                    ]
                ],
                'completed' => false,
                'completion_date' => null
            ]
        ];

        return response()->json($lesson);
    }

    /**
     * Mark lesson as complete
     */
    public function completeLesson(Request $request, $id)
    {
        $userId = Auth::id();

        $response = [
            'status' => 200,
            'message' => 'Lesson marked as complete',
            'data' => [
                'lesson_id' => $id,
                'completed_at' => Carbon::now(),
                'points_earned' => 10,
                'next_lesson' => 'lesson-2'
            ]
        ];

        return response()->json($response);
    }

    /**
     * Get exercises for a lesson
     */
    public function getExercises(Request $request, $lessonId)
    {
        $exercises = [
            'status' => 200,
            'data' => [
                [
                    'id' => 'ex-1',
                    'lesson_id' => $lessonId,
                    'title' => 'Thought Identification',
                    'type' => 'multiple_choice',
                    'question' => 'Which of the following is an automatic thought?',
                    'options' => [
                        'A. I am a failure',
                        'B. The weather is nice',
                        'C. It is 3 PM',
                        'D. The door is open'
                    ],
                    'required' => true,
                    'completed' => false
                ],
                [
                    'id' => 'ex-2',
                    'lesson_id' => $lessonId,
                    'title' => 'Reflection',
                    'type' => 'text',
                    'question' => 'Describe a recent situation where you had negative automatic thoughts',
                    'required' => false,
                    'completed' => false
                ]
            ]
        ];

        return response()->json($exercises);
    }

    /**
     * Complete an exercise
     */
    public function completeExercise(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'answer' => 'required',
            'lesson_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $response = [
            'status' => 200,
            'message' => 'Exercise completed',
            'data' => [
                'exercise_id' => $id,
                'correct' => true,
                'points_earned' => 5
            ]
        ];

        return response()->json($response);
    }

    /**
     * Create journal entry
     */
    public function createJournalEntry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'situation' => 'required|string',
            'thoughts' => 'required|string',
            'emotions' => 'required|string',
            'behaviors' => 'required|string',
            'alternative_thoughts' => 'nullable|string',
            'mood_before' => 'required|integer|min:1|max:10',
            'mood_after' => 'nullable|integer|min:1|max:10'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $response = [
            'status' => 200,
            'message' => 'Journal entry created',
            'data' => [
                'id' => uniqid('journal-'),
                'created_at' => Carbon::now(),
                'situation' => $request->situation,
                'thoughts' => $request->thoughts,
                'emotions' => $request->emotions,
                'behaviors' => $request->behaviors,
                'alternative_thoughts' => $request->alternative_thoughts,
                'mood_before' => $request->mood_before,
                'mood_after' => $request->mood_after
            ]
        ];

        return response()->json($response);
    }

    /**
     * Get journal entries
     */
    public function getJournalEntries(Request $request)
    {
        $userId = Auth::id();
        $limit = $request->get('limit', 20);
        $offset = $request->get('offset', 0);

        $entries = [
            'status' => 200,
            'data' => [
                [
                    'id' => 'journal-1',
                    'created_at' => Carbon::now()->subDays(2),
                    'situation' => 'Meeting at work',
                    'thoughts' => 'Everyone thinks I am incompetent',
                    'emotions' => 'Anxious, worried',
                    'behaviors' => 'Avoided speaking up',
                    'alternative_thoughts' => 'I have valuable contributions',
                    'mood_before' => 3,
                    'mood_after' => 6
                ],
                [
                    'id' => 'journal-2',
                    'created_at' => Carbon::now()->subDays(1),
                    'situation' => 'Exercise session',
                    'thoughts' => 'I cannot do this',
                    'emotions' => 'Frustrated',
                    'behaviors' => 'Took a break and tried again',
                    'alternative_thoughts' => 'I am getting stronger each day',
                    'mood_before' => 4,
                    'mood_after' => 7
                ]
            ],
            'pagination' => [
                'total' => 2,
                'limit' => $limit,
                'offset' => $offset
            ]
        ];

        return response()->json($entries);
    }

    /**
     * Update journal entry
     */
    public function updateJournalEntry(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'situation' => 'nullable|string',
            'thoughts' => 'nullable|string',
            'emotions' => 'nullable|string',
            'behaviors' => 'nullable|string',
            'alternative_thoughts' => 'nullable|string',
            'mood_before' => 'nullable|integer|min:1|max:10',
            'mood_after' => 'nullable|integer|min:1|max:10'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $response = [
            'status' => 200,
            'message' => 'Journal entry updated',
            'data' => [
                'id' => $id,
                'updated_at' => Carbon::now()
            ]
        ];

        return response()->json($response);
    }

    /**
     * Delete journal entry
     */
    public function deleteJournalEntry(Request $request, $id)
    {
        $response = [
            'status' => 200,
            'message' => 'Journal entry deleted'
        ];

        return response()->json($response);
    }

    /**
     * Get assessments
     */
    public function getAssessments(Request $request)
    {
        $assessments = [
            'status' => 200,
            'data' => [
                [
                    'id' => 'assess-1',
                    'title' => 'Depression Assessment (PHQ-9)',
                    'description' => 'Patient Health Questionnaire',
                    'questions_count' => 9,
                    'estimated_time' => 5,
                    'last_taken' => null,
                    'available' => true
                ],
                [
                    'id' => 'assess-2',
                    'title' => 'Anxiety Assessment (GAD-7)',
                    'description' => 'Generalized Anxiety Disorder Scale',
                    'questions_count' => 7,
                    'estimated_time' => 5,
                    'last_taken' => Carbon::now()->subDays(7),
                    'available' => true
                ],
                [
                    'id' => 'assess-3',
                    'title' => 'Stress Assessment',
                    'description' => 'Perceived Stress Scale',
                    'questions_count' => 10,
                    'estimated_time' => 5,
                    'last_taken' => null,
                    'available' => true
                ]
            ]
        ];

        return response()->json($assessments);
    }

    /**
     * Submit assessment
     */
    public function submitAssessment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'answers' => 'required|array',
            'answers.*' => 'required|integer|min:0|max:4'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $score = array_sum($request->answers);
        $maxScore = count($request->answers) * 4;
        $percentage = ($score / $maxScore) * 100;

        $response = [
            'status' => 200,
            'message' => 'Assessment submitted',
            'data' => [
                'assessment_id' => $id,
                'score' => $score,
                'max_score' => $maxScore,
                'percentage' => $percentage,
                'severity' => $this->getSeverityLevel($percentage),
                'recommendations' => $this->getRecommendations($percentage),
                'submitted_at' => Carbon::now()
            ]
        ];

        return response()->json($response);
    }

    /**
     * Get goals
     */
    public function getGoals(Request $request)
    {
        $userId = Auth::id();

        $goals = [
            'status' => 200,
            'data' => [
                [
                    'id' => 'goal-1',
                    'title' => 'Complete Week 1 Lessons',
                    'description' => 'Finish all three lessons in week 1',
                    'target_date' => Carbon::now()->addDays(5),
                    'progress' => 66,
                    'status' => 'in_progress',
                    'created_at' => Carbon::now()->subDays(2)
                ],
                [
                    'id' => 'goal-2',
                    'title' => 'Daily Journal Entry',
                    'description' => 'Write in journal every day for 7 days',
                    'target_date' => Carbon::now()->addDays(7),
                    'progress' => 43,
                    'status' => 'in_progress',
                    'created_at' => Carbon::now()->subDays(3)
                ]
            ]
        ];

        return response()->json($goals);
    }

    /**
     * Create goal
     */
    public function createGoal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'target_date' => 'required|date|after:today',
            'category' => 'nullable|string|in:lessons,journal,exercise,general'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $response = [
            'status' => 200,
            'message' => 'Goal created successfully',
            'data' => [
                'id' => uniqid('goal-'),
                'title' => $request->title,
                'description' => $request->description,
                'target_date' => $request->target_date,
                'category' => $request->category ?? 'general',
                'progress' => 0,
                'status' => 'active',
                'created_at' => Carbon::now()
            ]
        ];

        return response()->json($response);
    }

    /**
     * Update goal
     */
    public function updateGoal(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'target_date' => 'nullable|date|after:today',
            'progress' => 'nullable|integer|min:0|max:100',
            'status' => 'nullable|string|in:active,completed,cancelled'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $response = [
            'status' => 200,
            'message' => 'Goal updated successfully',
            'data' => [
                'id' => $id,
                'updated_at' => Carbon::now()
            ]
        ];

        return response()->json($response);
    }

    /**
     * Get insights
     */
    public function getInsights(Request $request)
    {
        $userId = Auth::id();

        $insights = [
            'status' => 200,
            'data' => [
                'mood_trends' => [
                    'average_before' => 4.2,
                    'average_after' => 6.8,
                    'improvement' => 61.9,
                    'trend' => 'improving'
                ],
                'common_triggers' => [
                    'work' => 35,
                    'relationships' => 25,
                    'health' => 20,
                    'finances' => 15,
                    'other' => 5
                ],
                'common_emotions' => [
                    'anxious' => 40,
                    'sad' => 25,
                    'frustrated' => 20,
                    'angry' => 10,
                    'other' => 5
                ],
                'practice_stats' => [
                    'total_lessons' => 36,
                    'completed_lessons' => 9,
                    'total_journal_entries' => 15,
                    'current_streak' => 5,
                    'best_streak' => 12
                ],
                'recommendations' => [
                    'Continue daily journaling',
                    'Focus on challenging negative thoughts about work',
                    'Practice relaxation techniques before stressful meetings'
                ]
            ]
        ];

        return response()->json($insights);
    }

    /**
     * Get today's daily lesson
     */
    public function getDailyLesson(Request $request)
    {
        $userId = Auth::id();

        // Get the user's current week from CBT progress
        $progress = DB::table('cbt_progress')->where('user_id', $userId)->first();
        $currentWeek = $progress ? $progress->current_week : 1;

        // Get today's day of week (1-7, Monday-Sunday)
        $dayOfWeek = now()->dayOfWeekIso;

        // Calculate lesson number (week * 7 - 7 + day)
        $lessonNumber = ($currentWeek * 7 - 7) + $dayOfWeek;

        // Find the lesson for today
        $lesson = DB::table('cbt_lessons')
            ->where('lesson_number', $lessonNumber)
            ->first();

        if (!$lesson) {
            // Return a motivational message if no lesson for today
            return response()->json([
                'status' => 200,
                'data' => [
                    'id' => null,
                    'title' => 'Rest Day',
                    'description' => 'Take today to reflect on what you\'ve learned this week.',
                    'duration' => 0,
                    'completed' => true,
                    'is_rest_day' => true
                ]
            ]);
        }

        // Check if lesson is completed
        $completed = DB::table('cbt_lesson_completions')
            ->where('user_id', $userId)
            ->where('lesson_id', $lesson->id)
            ->exists();

        return response()->json([
            'status' => 200,
            'data' => [
                'id' => $lesson->id,
                'week_number' => $currentWeek,
                'lesson_number' => $lesson->lesson_number,
                'title' => $lesson->title,
                'description' => $lesson->description,
                'content' => $lesson->content ?? null,
                'video_url' => $lesson->video_url ?? null,
                'duration' => $lesson->duration_minutes ?? 15,
                'completed' => $completed,
                'is_rest_day' => false
            ]
        ]);
    }

    /**
     * Helper: Get severity level based on percentage
     */
    private function getSeverityLevel($percentage)
    {
        if ($percentage < 20) return 'minimal';
        if ($percentage < 40) return 'mild';
        if ($percentage < 60) return 'moderate';
        if ($percentage < 80) return 'moderately_severe';
        return 'severe';
    }

    /**
     * Helper: Get recommendations based on percentage
     */
    private function getRecommendations($percentage)
    {
        $recommendations = [];

        if ($percentage < 40) {
            $recommendations[] = 'Continue with regular CBT exercises';
            $recommendations[] = 'Maintain your journal practice';
        } elseif ($percentage < 60) {
            $recommendations[] = 'Increase frequency of CBT exercises';
            $recommendations[] = 'Consider discussing with your coach';
            $recommendations[] = 'Focus on identifying triggers';
        } else {
            $recommendations[] = 'Prioritize daily CBT practice';
            $recommendations[] = 'Schedule a session with your coach';
            $recommendations[] = 'Consider additional support resources';
        }

        return $recommendations;
    }

    /**
     * Get calendar view of CBT program (weekly view)
     */
    public function getCalendar(Request $request)
    {
        $userId = Auth::id();
        $weekOffset = $request->get('week', 0); // 0 = current week, -1 = previous, 1 = next

        $startDate = Carbon::now()->startOfWeek()->addWeeks($weekOffset);
        $endDate = Carbon::now()->endOfWeek()->addWeeks($weekOffset);

        $calendar = [
            'status' => 200,
            'data' => [
                'week_start' => $startDate->format('Y-m-d'),
                'week_end' => $endDate->format('Y-m-d'),
                'week_number' => $startDate->weekOfYear,
                'days' => []
            ]
        ];

        // Generate 7 days
        for ($i = 0; $i < 7; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dayData = [
                'date' => $date->format('Y-m-d'),
                'day_name' => $date->format('l'),
                'is_today' => $date->isToday(),
                'events' => []
            ];

            // Add daily lesson
            if ($i < 5) { // Weekdays only
                $dayData['events'][] = [
                    'type' => 'lesson',
                    'time' => '09:00',
                    'title' => 'Daily CBT Lesson',
                    'duration_minutes' => 20,
                    'completed' => $date->isPast()
                ];
            }

            // Add journal prompt
            $dayData['events'][] = [
                'type' => 'journal',
                'time' => '20:00',
                'title' => 'Evening Journal',
                'duration_minutes' => 10,
                'completed' => $date->isPast()
            ];

            // Add biweekly assessment (every 14 days)
            if ($date->dayOfYear % 14 == 0) {
                $dayData['events'][] = [
                    'type' => 'assessment',
                    'time' => '10:00',
                    'title' => 'Biweekly Progress Assessment',
                    'duration_minutes' => 15,
                    'required' => true,
                    'completed' => false
                ];
            }

            $calendar['data']['days'][] = $dayData;
        }

        return response()->json($calendar);
    }

    /**
     * Get month calendar view
     */
    public function getMonthCalendar(Request $request)
    {
        $userId = Auth::id();
        $month = $request->get('month', Carbon::now()->month);
        $year = $request->get('year', Carbon::now()->year);

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $calendar = [
            'status' => 200,
            'data' => [
                'month' => $startDate->format('F'),
                'year' => $year,
                'month_number' => $month,
                'days' => [],
                'summary' => [
                    'total_lessons' => 0,
                    'completed_lessons' => 0,
                    'journal_entries' => 0,
                    'assessments_due' => 0
                ]
            ]
        ];

        // Generate all days in month
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $dayData = [
                'date' => $currentDate->format('Y-m-d'),
                'day' => $currentDate->day,
                'is_today' => $currentDate->isToday(),
                'has_lesson' => $currentDate->isWeekday(),
                'has_journal' => true,
                'has_assessment' => ($currentDate->dayOfYear % 14 == 0),
                'lesson_completed' => $currentDate->isPast(),
                'journal_completed' => $currentDate->isPast()
            ];

            if ($dayData['has_lesson']) {
                $calendar['data']['summary']['total_lessons']++;
                if ($dayData['lesson_completed']) {
                    $calendar['data']['summary']['completed_lessons']++;
                }
            }

            if ($dayData['has_assessment'] && $currentDate->isFuture()) {
                $calendar['data']['summary']['assessments_due']++;
            }

            $calendar['data']['days'][] = $dayData;
            $currentDate->addDay();
        }

        return response()->json($calendar);
    }

    /**
     * Get video library
     */
    public function getVideoLibrary(Request $request)
    {
        $category = $request->get('category', 'all'); // all, introductions, exercises, techniques, testimonials
        $search = $request->get('search', '');

        $videos = [
            'status' => 200,
            'data' => [
                [
                    'id' => 'video-1',
                    'title' => 'Introduction to CBT',
                    'description' => 'Learn the fundamentals of Cognitive Behavioral Therapy',
                    'thumbnail' => 'https://bodyf1rst.s3.amazonaws.com/cbt/thumbnails/intro-cbt.jpg',
                    'video_url' => 'https://bodyf1rst.s3.amazonaws.com/cbt/videos/intro-cbt.mp4',
                    'duration' => '12:30',
                    'duration_seconds' => 750,
                    'category' => 'introductions',
                    'tags' => ['basics', 'overview', 'getting started'],
                    'week_number' => 1,
                    'views' => 1523,
                    'body_points' => 10,
                    'watched' => false,
                    'created_at' => Carbon::now()->subMonths(2)
                ],
                [
                    'id' => 'video-2',
                    'title' => 'Identifying Negative Thoughts',
                    'description' => 'Learn how to recognize automatic negative thoughts',
                    'thumbnail' => 'https://bodyf1rst.s3.amazonaws.com/cbt/thumbnails/negative-thoughts.jpg',
                    'video_url' => 'https://bodyf1rst.s3.amazonaws.com/cbt/videos/negative-thoughts.mp4',
                    'duration' => '8:45',
                    'duration_seconds' => 525,
                    'category' => 'techniques',
                    'tags' => ['thoughts', 'awareness', 'cognitive distortions'],
                    'week_number' => 1,
                    'views' => 1342,
                    'body_points' => 5,
                    'watched' => false,
                    'created_at' => Carbon::now()->subMonths(2)
                ],
                [
                    'id' => 'video-3',
                    'title' => 'Thought Record Exercise',
                    'description' => 'Practice keeping a thought record',
                    'thumbnail' => 'https://bodyf1rst.s3.amazonaws.com/cbt/thumbnails/thought-record.jpg',
                    'video_url' => 'https://bodyf1rst.s3.amazonaws.com/cbt/videos/thought-record.mp4',
                    'duration' => '15:20',
                    'duration_seconds' => 920,
                    'category' => 'exercises',
                    'tags' => ['practice', 'journaling', 'thought record'],
                    'week_number' => 2,
                    'views' => 1198,
                    'body_points' => 15,
                    'watched' => false,
                    'created_at' => Carbon::now()->subMonths(1)
                ],
                [
                    'id' => 'video-4',
                    'title' => 'Breathing Techniques for Anxiety',
                    'description' => 'Learn effective breathing exercises to manage anxiety',
                    'thumbnail' => 'https://bodyf1rst.s3.amazonaws.com/cbt/thumbnails/breathing.jpg',
                    'video_url' => 'https://bodyf1rst.s3.amazonaws.com/cbt/videos/breathing.mp4',
                    'duration' => '10:15',
                    'duration_seconds' => 615,
                    'category' => 'techniques',
                    'tags' => ['anxiety', 'breathing', 'relaxation'],
                    'week_number' => 3,
                    'views' => 2105,
                    'body_points' => 10,
                    'watched' => false,
                    'created_at' => Carbon::now()->subWeeks(3)
                ],
                [
                    'id' => 'video-5',
                    'title' => 'Success Story: Overcoming Depression',
                    'description' => 'Hear from someone who used CBT to overcome depression',
                    'thumbnail' => 'https://bodyf1rst.s3.amazonaws.com/cbt/thumbnails/success-story-1.jpg',
                    'video_url' => 'https://bodyf1rst.s3.amazonaws.com/cbt/videos/success-story-1.mp4',
                    'duration' => '6:40',
                    'duration_seconds' => 400,
                    'category' => 'testimonials',
                    'tags' => ['inspiration', 'depression', 'recovery'],
                    'week_number' => null,
                    'views' => 892,
                    'body_points' => 5,
                    'watched' => false,
                    'created_at' => Carbon::now()->subWeeks(2)
                ]
            ],
            'categories' => [
                ['value' => 'all', 'label' => 'All Videos', 'count' => 5],
                ['value' => 'introductions', 'label' => 'Introductions', 'count' => 1],
                ['value' => 'techniques', 'label' => 'Techniques', 'count' => 2],
                ['value' => 'exercises', 'label' => 'Exercises', 'count' => 1],
                ['value' => 'testimonials', 'label' => 'Success Stories', 'count' => 1]
            ]
        ];

        return response()->json($videos);
    }

    /**
     * Get specific video details
     */
    public function getVideo(Request $request, $id)
    {
        $video = [
            'status' => 200,
            'data' => [
                'id' => $id,
                'title' => 'Introduction to CBT',
                'description' => 'Learn the fundamentals of Cognitive Behavioral Therapy and how it can help transform your mental health.',
                'long_description' => 'In this comprehensive introduction, you will learn about the core principles of CBT, including how thoughts, feelings, and behaviors are interconnected. We will explore practical techniques you can start using immediately to improve your mental wellbeing.',
                'thumbnail' => 'https://bodyf1rst.s3.amazonaws.com/cbt/thumbnails/intro-cbt.jpg',
                'video_url' => 'https://bodyf1rst.s3.amazonaws.com/cbt/videos/intro-cbt.mp4',
                'duration' => '12:30',
                'duration_seconds' => 750,
                'category' => 'introductions',
                'tags' => ['basics', 'overview', 'getting started'],
                'week_number' => 1,
                'transcript' => 'Welcome to Cognitive Behavioral Therapy...',
                'key_takeaways' => [
                    'CBT focuses on the relationship between thoughts, feelings, and behaviors',
                    'Negative thought patterns can be identified and changed',
                    'CBT is evidence-based and highly effective',
                    'You can practice CBT techniques daily for lasting change'
                ],
                'related_videos' => ['video-2', 'video-3'],
                'body_points' => 10,
                'watched' => false,
                'watch_progress' => 0,
                'views' => 1523,
                'created_at' => Carbon::now()->subMonths(2)
            ]
        ];

        return response()->json($video);
    }

    /**
     * Get user's CBT schedule
     */
    public function getSchedule(Request $request)
    {
        $userId = Auth::id();
        $days = $request->get('days', 7); // Default to 7 days ahead

        $schedule = [
            'status' => 200,
            'data' => [
                'upcoming' => [],
                'overdue' => []
            ]
        ];

        $now = Carbon::now();

        // Generate upcoming schedule
        for ($i = 0; $i < $days; $i++) {
            $date = $now->copy()->addDays($i);

            // Daily lesson (weekdays only)
            if ($date->isWeekday()) {
                $schedule['data']['upcoming'][] = [
                    'type' => 'lesson',
                    'title' => 'Daily CBT Lesson - Day ' . ($i + 1),
                    'scheduled_date' => $date->format('Y-m-d'),
                    'scheduled_time' => '09:00',
                    'duration_minutes' => 20,
                    'body_points' => 10,
                    'status' => 'scheduled',
                    'can_complete_now' => $date->isToday()
                ];
            }

            // Evening journal
            $schedule['data']['upcoming'][] = [
                'type' => 'journal',
                'title' => 'Evening Reflection',
                'scheduled_date' => $date->format('Y-m-d'),
                'scheduled_time' => '20:00',
                'duration_minutes' => 10,
                'body_points' => 5,
                'status' => 'scheduled',
                'can_complete_now' => $date->isToday() && Carbon::now()->hour >= 18
            ];

            // Biweekly assessment
            if ($date->day % 14 == 0) {
                $schedule['data']['upcoming'][] = [
                    'type' => 'assessment',
                    'title' => 'Biweekly Progress Check',
                    'scheduled_date' => $date->format('Y-m-d'),
                    'scheduled_time' => '10:00',
                    'duration_minutes' => 15,
                    'body_points' => 20,
                    'status' => 'scheduled',
                    'required' => true,
                    'can_complete_now' => $date->isToday()
                ];
            }
        }

        // Add some overdue items as example
        $schedule['data']['overdue'][] = [
            'type' => 'lesson',
            'title' => 'Daily CBT Lesson - Day 3',
            'scheduled_date' => Carbon::now()->subDays(2)->format('Y-m-d'),
            'scheduled_time' => '09:00',
            'duration_minutes' => 20,
            'body_points' => 10,
            'status' => 'overdue',
            'can_complete_now' => true
        ];

        return response()->json($schedule);
    }

    /**
     * Schedule a biweekly assessment
     */
    public function scheduleAssessment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'assessment_id' => 'required|string',
            'scheduled_date' => 'required|date|after:today',
            'scheduled_time' => 'nullable|string',
            'recurring' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $userId = Auth::id();
        $scheduledDate = Carbon::parse($request->scheduled_date);
        $scheduledTime = $request->scheduled_time ?? '10:00';

        $response = [
            'status' => 200,
            'message' => 'Assessment scheduled successfully',
            'data' => [
                'id' => uniqid('sched-assess-'),
                'user_id' => $userId,
                'assessment_id' => $request->assessment_id,
                'scheduled_date' => $scheduledDate->format('Y-m-d'),
                'scheduled_time' => $scheduledTime,
                'recurring' => $request->recurring ?? true,
                'recurrence_interval_days' => 14,
                'next_occurrence' => $scheduledDate->copy()->addDays(14)->format('Y-m-d'),
                'status' => 'scheduled',
                'reminder_enabled' => true,
                'reminder_hours_before' => 24,
                'created_at' => Carbon::now()
            ]
        ];

        return response()->json($response);
    }

    /**
     * Get scheduled assessments
     */
    public function getScheduledAssessments(Request $request)
    {
        $userId = Auth::id();
        $includeCompleted = $request->get('include_completed', false);

        $assessments = [
            'status' => 200,
            'data' => [
                [
                    'id' => 'sched-assess-1',
                    'user_id' => $userId,
                    'assessment_id' => 'assess-1',
                    'assessment_title' => 'Depression Assessment (PHQ-9)',
                    'scheduled_date' => Carbon::now()->addDays(7)->format('Y-m-d'),
                    'scheduled_time' => '10:00',
                    'recurring' => true,
                    'recurrence_interval_days' => 14,
                    'next_occurrence' => Carbon::now()->addDays(21)->format('Y-m-d'),
                    'status' => 'scheduled',
                    'reminder_sent' => false,
                    'body_points' => 20,
                    'created_at' => Carbon::now()->subDays(7)
                ],
                [
                    'id' => 'sched-assess-2',
                    'user_id' => $userId,
                    'assessment_id' => 'assess-2',
                    'assessment_title' => 'Anxiety Assessment (GAD-7)',
                    'scheduled_date' => Carbon::now()->addDays(14)->format('Y-m-d'),
                    'scheduled_time' => '10:00',
                    'recurring' => true,
                    'recurrence_interval_days' => 14,
                    'next_occurrence' => Carbon::now()->addDays(28)->format('Y-m-d'),
                    'status' => 'scheduled',
                    'reminder_sent' => false,
                    'body_points' => 20,
                    'created_at' => Carbon::now()->subDays(7)
                ]
            ]
        ];

        return response()->json($assessments);
    }

    /**
     * Get user's CBT points balance
     */
    public function getPoints(Request $request)
    {
        $userId = Auth::id();

        $points = [
            'status' => 200,
            'data' => [
                'total_points' => 245,
                'available_points' => 180,
                'redeemed_points' => 65,
                'lifetime_points' => 350,
                'current_streak' => 5,
                'best_streak' => 12,
                'level' => 3,
                'level_name' => 'Mindful Practitioner',
                'points_to_next_level' => 55,
                'next_level' => 4,
                'next_level_name' => 'CBT Master',
                'breakdown' => [
                    'lessons_completed' => 120,
                    'exercises_completed' => 50,
                    'journal_entries' => 45,
                    'assessments_completed' => 20,
                    'videos_watched' => 10,
                    'streak_bonus' => 0
                ],
                'recent_earnings' => [
                    [
                        'activity' => 'Completed Lesson: Identifying Thoughts',
                        'points' => 10,
                        'date' => Carbon::now()->subHours(3)->toIso8601String()
                    ],
                    [
                        'activity' => 'Evening Journal Entry',
                        'points' => 5,
                        'date' => Carbon::now()->subHours(14)->toIso8601String()
                    ],
                    [
                        'activity' => 'Completed Exercise: Thought Record',
                        'points' => 5,
                        'date' => Carbon::now()->subDay()->toIso8601String()
                    ]
                ]
            ]
        ];

        return response()->json($points);
    }

    /**
     * Get points history with pagination
     */
    public function getPointsHistory(Request $request)
    {
        $userId = Auth::id();
        $limit = $request->get('limit', 20);
        $offset = $request->get('offset', 0);

        $history = [
            'status' => 200,
            'data' => [
                [
                    'id' => 'pts-1',
                    'type' => 'lesson_complete',
                    'activity' => 'Completed Lesson: Introduction to CBT',
                    'points' => 10,
                    'balance_after' => 245,
                    'date' => Carbon::now()->subHours(3)->toIso8601String()
                ],
                [
                    'id' => 'pts-2',
                    'type' => 'journal_entry',
                    'activity' => 'Created Evening Journal Entry',
                    'points' => 5,
                    'balance_after' => 235,
                    'date' => Carbon::now()->subHours(14)->toIso8601String()
                ],
                [
                    'id' => 'pts-3',
                    'type' => 'exercise_complete',
                    'activity' => 'Completed Exercise: Thought Record',
                    'points' => 5,
                    'balance_after' => 230,
                    'date' => Carbon::now()->subDay()->toIso8601String()
                ],
                [
                    'id' => 'pts-4',
                    'type' => 'assessment_complete',
                    'activity' => 'Completed Depression Assessment (PHQ-9)',
                    'points' => 20,
                    'balance_after' => 225,
                    'date' => Carbon::now()->subDays(2)->toIso8601String()
                ],
                [
                    'id' => 'pts-5',
                    'type' => 'video_watch',
                    'activity' => 'Watched: Breathing Techniques for Anxiety',
                    'points' => 10,
                    'balance_after' => 205,
                    'date' => Carbon::now()->subDays(3)->toIso8601String()
                ],
                [
                    'id' => 'pts-6',
                    'type' => 'redeem',
                    'activity' => 'Redeemed: 1-on-1 Coach Session Discount',
                    'points' => -50,
                    'balance_after' => 195,
                    'date' => Carbon::now()->subDays(5)->toIso8601String()
                ]
            ],
            'pagination' => [
                'total' => 45,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => true
            ]
        ];

        return response()->json($history);
    }
}