<?php

namespace App\Http\Controllers;

use App\Services\AI\FitnessAiService;
use App\Services\AI\NutritionAiService;
use App\Services\AI\ClientAnalyticsAiService;
use App\Services\AI\SchedulingAiService;
use App\Services\AI\MessagingAiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * AI Assistant Controller
 * Unified AI interface for BodyF1rst platform (Web + Mobile)
 *
 * Integrates with:
 * - Workout Builder
 * - Nutrition Plans
 * - Client Analytics
 * - Scheduling/Calendar
 * - Messaging
 */
class AiAssistantController extends Controller
{
    protected $fitnessAi;
    protected $nutritionAi;
    protected $analyticsAi;
    protected $schedulingAi;
    protected $messagingAi;

    public function __construct(
        FitnessAiService $fitnessAi,
        NutritionAiService $nutritionAi,
        ClientAnalyticsAiService $analyticsAi,
        SchedulingAiService $schedulingAi,
        MessagingAiService $messagingAi
    ) {
        $this->middleware('auth:api');
        $this->fitnessAi = $fitnessAi;
        $this->nutritionAi = $nutritionAi;
        $this->analyticsAi = $analyticsAi;
        $this->schedulingAi = $schedulingAi;
        $this->messagingAi = $messagingAi;
    }

    /**
     * Main AI chat endpoint
     * POST /api/ai/chat
     *
     * Handles natural language queries and routes to appropriate AI service
     */
    public function chat(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|min:1|max:1000',
            'conversation_id' => 'nullable|string',
            'context' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $message = $request->input('message');
            $context = $request->input('context', []);
            $context['user_id'] = Auth::id();
            $context['user_type'] = Auth::user()->role ?? 'coach';

            // Detect intent and route to appropriate AI service
            $intent = $this->detectIntent($message);

            Log::info('AI Chat Request', [
                'message' => $message,
                'intent' => $intent,
                'user_id' => $context['user_id'],
            ]);

            $response = match($intent['category']) {
                'workout' => $this->fitnessAi->process($message, $intent, $context),
                'nutrition' => $this->nutritionAi->process($message, $intent, $context),
                'analytics' => $this->analyticsAi->process($message, $intent, $context),
                'scheduling' => $this->schedulingAi->process($message, $intent, $context),
                'messaging' => $this->messagingAi->process($message, $intent, $context),
                default => $this->handleGeneralQuery($message, $context),
            };

            return response()->json([
                'success' => true,
                'response' => $response['message'] ?? 'Request processed',
                'data' => $response['data'] ?? null,
                'conversation_id' => $request->input('conversation_id') ?? uniqid('conv_'),
                'intent' => $intent,
            ]);

        } catch (\Exception $e) {
            Log::error('AI Chat Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process AI request',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Create workout with AI
     * POST /api/ai/workout/create
     */
    public function createWorkout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'prompt' => 'required|string',
            'client_id' => 'nullable|integer',
            'workout_type' => 'nullable|string|in:strength,cardio,hiit,crossfit,yoga,custom',
            'duration_minutes' => 'nullable|integer|min:5|max:180',
            'difficulty' => 'nullable|string|in:beginner,intermediate,advanced',
            'equipment' => 'nullable|array',
            'goals' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $context = [
                'user_id' => Auth::id(),
                'client_id' => $request->input('client_id'),
                'workout_type' => $request->input('workout_type'),
                'duration_minutes' => $request->input('duration_minutes'),
                'difficulty' => $request->input('difficulty'),
                'equipment' => $request->input('equipment', []),
                'goals' => $request->input('goals', []),
            ];

            $workout = $this->fitnessAi->createWorkout($request->input('prompt'), $context);

            return response()->json([
                'success' => true,
                'message' => 'Workout created successfully',
                'workout' => $workout,
            ]);

        } catch (\Exception $e) {
            Log::error('AI Workout Creation Error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create workout',
            ], 500);
        }
    }

    /**
     * Create nutrition plan with AI
     * POST /api/ai/nutrition/create
     */
    public function createNutritionPlan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'prompt' => 'required|string',
            'client_id' => 'nullable|integer',
            'goal' => 'nullable|string|in:weight_loss,muscle_gain,maintenance,performance',
            'dietary_restrictions' => 'nullable|array',
            'daily_calories' => 'nullable|integer|min:1000|max:5000',
            'meal_count' => 'nullable|integer|min:3|max:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $context = [
                'user_id' => Auth::id(),
                'client_id' => $request->input('client_id'),
                'goal' => $request->input('goal'),
                'dietary_restrictions' => $request->input('dietary_restrictions', []),
                'daily_calories' => $request->input('daily_calories'),
                'meal_count' => $request->input('meal_count', 3),
            ];

            $plan = $this->nutritionAi->createMealPlan($request->input('prompt'), $context);

            return response()->json([
                'success' => true,
                'message' => 'Nutrition plan created successfully',
                'plan' => $plan,
            ]);

        } catch (\Exception $e) {
            Log::error('AI Nutrition Plan Error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create nutrition plan',
            ], 500);
        }
    }

    /**
     * Get client analytics with AI insights
     * GET /api/ai/analytics/client/{clientId}
     */
    public function getClientAnalytics($clientId)
    {
        try {
            $analytics = $this->analyticsAi->analyzeClient($clientId, Auth::id());

            return response()->json([
                'success' => true,
                'analytics' => $analytics,
            ]);

        } catch (\Exception $e) {
            Log::error('AI Analytics Error', [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate analytics',
            ], 500);
        }
    }

    /**
     * AI-powered scheduling assistant
     * POST /api/ai/schedule/book
     */
    public function scheduleAppointment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'prompt' => 'required|string',
            'client_id' => 'nullable|integer',
            'preferred_date' => 'nullable|date',
            'duration_minutes' => 'nullable|integer|min:15|max:180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $context = [
                'user_id' => Auth::id(),
                'client_id' => $request->input('client_id'),
                'preferred_date' => $request->input('preferred_date'),
                'duration_minutes' => $request->input('duration_minutes', 60),
            ];

            $appointment = $this->schedulingAi->scheduleAppointment($request->input('prompt'), $context);

            return response()->json([
                'success' => true,
                'message' => 'Appointment scheduled successfully',
                'appointment' => $appointment,
            ]);

        } catch (\Exception $e) {
            Log::error('AI Scheduling Error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to schedule appointment',
            ], 500);
        }
    }

    /**
     * AI message drafting and reply suggestions
     * POST /api/ai/messages/draft
     */
    public function draftMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'prompt' => 'required|string',
            'client_id' => 'required|integer',
            'message_type' => 'nullable|string|in:check_in,motivation,feedback,reminder',
            'context_messages' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $context = [
                'user_id' => Auth::id(),
                'client_id' => $request->input('client_id'),
                'message_type' => $request->input('message_type'),
                'context_messages' => $request->input('context_messages', []),
            ];

            $draft = $this->messagingAi->draftMessage($request->input('prompt'), $context);

            return response()->json([
                'success' => true,
                'draft' => $draft,
            ]);

        } catch (\Exception $e) {
            Log::error('AI Message Draft Error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to draft message',
            ], 500);
        }
    }

    /**
     * Get AI capabilities and examples
     * GET /api/ai/capabilities
     */
    public function getCapabilities()
    {
        return response()->json([
            'capabilities' => [
                'workout_creation' => [
                    'description' => 'Create custom workouts with AI',
                    'examples' => [
                        'Create a 30-minute HIIT workout for weight loss',
                        'Design a strength training program for beginners',
                        'Build a CrossFit WOD with kettlebells',
                    ],
                    'endpoint' => '/api/ai/workout/create',
                ],
                'nutrition_planning' => [
                    'description' => 'Generate personalized meal plans',
                    'examples' => [
                        'Create a vegetarian meal plan for muscle gain',
                        'Design a 2000 calorie cutting diet',
                        'Generate meal prep ideas for busy clients',
                    ],
                    'endpoint' => '/api/ai/nutrition/create',
                ],
                'client_analytics' => [
                    'description' => 'AI-powered progress insights',
                    'examples' => [
                        'Analyze client progress over last month',
                        'Identify workout patterns and trends',
                        'Suggest program adjustments based on data',
                    ],
                    'endpoint' => '/api/ai/analytics/client/{id}',
                ],
                'scheduling' => [
                    'description' => 'Smart appointment booking',
                    'examples' => [
                        'Find the best time for a client session this week',
                        'Schedule recurring training sessions',
                        'Book a nutrition consultation',
                    ],
                    'endpoint' => '/api/ai/schedule/book',
                ],
                'messaging' => [
                    'description' => 'AI-assisted communication',
                    'examples' => [
                        'Draft a motivational message for struggling client',
                        'Write feedback on recent workout performance',
                        'Create a check-in message',
                    ],
                    'endpoint' => '/api/ai/messages/draft',
                ],
            ],
        ]);
    }

    /**
     * Voice command endpoint for mobile app
     * POST /api/ai/voice
     */
    public function processVoiceCommand(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transcript' => 'required|string',
            'audio_url' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $transcript = $request->input('transcript');
            $context = [
                'user_id' => Auth::id(),
                'input_mode' => 'voice',
            ];

            // Process as regular chat
            $request->merge(['message' => $transcript]);
            return $this->chat($request);

        } catch (\Exception $e) {
            Log::error('Voice Command Error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process voice command',
            ], 500);
        }
    }

    /**
     * Detect intent from user message
     */
    protected function detectIntent(string $message): array
    {
        $lowerMessage = strtolower($message);

        // Workout keywords
        if (preg_match('/\b(workout|exercise|training|wod|hiit|strength|cardio|crossfit)\b/', $lowerMessage)) {
            return [
                'category' => 'workout',
                'confidence' => 0.9,
                'keywords' => ['workout', 'exercise', 'training'],
            ];
        }

        // Nutrition keywords
        if (preg_match('/\b(nutrition|meal|diet|calories|macro|food|eating)\b/', $lowerMessage)) {
            return [
                'category' => 'nutrition',
                'confidence' => 0.9,
                'keywords' => ['nutrition', 'meal', 'diet'],
            ];
        }

        // Analytics keywords
        if (preg_match('/\b(progress|analytics|stats|performance|results|data)\b/', $lowerMessage)) {
            return [
                'category' => 'analytics',
                'confidence' => 0.85,
                'keywords' => ['progress', 'analytics'],
            ];
        }

        // Scheduling keywords
        if (preg_match('/\b(schedule|appointment|book|calendar|session|meeting)\b/', $lowerMessage)) {
            return [
                'category' => 'scheduling',
                'confidence' => 0.85,
                'keywords' => ['schedule', 'appointment'],
            ];
        }

        // Messaging keywords
        if (preg_match('/\b(message|text|email|send|reply|draft)\b/', $lowerMessage)) {
            return [
                'category' => 'messaging',
                'confidence' => 0.8,
                'keywords' => ['message', 'text'],
            ];
        }

        return [
            'category' => 'general',
            'confidence' => 0.5,
            'keywords' => [],
        ];
    }

    /**
     * Handle general queries
     */
    protected function handleGeneralQuery(string $message, array $context): array
    {
        return [
            'message' => "I can help you with workouts, nutrition plans, client analytics, scheduling, and messaging. What would you like to do?",
            'data' => [
                'suggestions' => [
                    'Create a workout',
                    'Design a meal plan',
                    'View client progress',
                    'Schedule an appointment',
                    'Draft a message',
                ],
            ],
        ];
    }

    /**
     * Process AI message (legacy endpoint for compatibility)
     * POST /api/ai/process-message
     */
    public function processMessage(Request $request)
    {
        // Route to chat endpoint
        return $this->chat($request);
    }

    /**
     * Save conversation history
     * POST /api/ai/save-conversation
     */
    public function saveConversation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'sender' => 'required|in:user,coach',
            'message' => 'required|string',
            'message_type' => 'nullable|in:text,action,confirmation',
            'metadata' => 'nullable|json',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $conversation = \DB::table('ai_conversations')->insert([
                'user_id' => $request->user_id,
                'sender' => $request->sender,
                'message' => $request->message,
                'message_type' => $request->message_type ?? 'text',
                'metadata' => $request->metadata,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Conversation saved successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Save Conversation Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to save conversation',
            ], 500);
        }
    }

    /**
     * Get chat history
     * GET /api/ai/get-chat-history
     */
    public function getChatHistory(Request $request)
    {
        $limit = $request->get('limit', 50);
        $userId = $request->get('user_id', Auth::id());

        try {
            $messages = \DB::table('ai_conversations')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'messages' => $messages,
                'count' => $messages->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Get Chat History Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve chat history',
            ], 500);
        }
    }

    /**
     * Schedule workout via AI
     * POST /api/ai/schedule-workout
     */
    public function scheduleWorkout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'workout_type' => 'required|string',
            'datetime' => 'required|date',
            'duration' => 'required|integer|min:5|max:300',
            'user_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $workout = \DB::table('scheduled_workouts')->insertGetId([
                'user_id' => Auth::id(),
                'workout_type' => $request->workout_type,
                'scheduled_at' => $request->datetime,
                'duration_minutes' => $request->duration,
                'status' => 'scheduled',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Workout scheduled successfully',
                'workout' => [
                    'id' => $workout,
                    'workout_type' => $request->workout_type,
                    'scheduled_at' => $request->datetime,
                    'duration_minutes' => $request->duration,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Schedule Workout Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to schedule workout',
            ], 500);
        }
    }

    /**
     * Schedule meal via AI
     * POST /api/ai/schedule-meal
     */
    public function scheduleMeal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'meal_type' => 'required|in:breakfast,lunch,dinner,snack',
            'datetime' => 'required|date',
            'calories' => 'nullable|integer',
            'user_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $meal = \DB::table('scheduled_meals')->insertGetId([
                'user_id' => Auth::id(),
                'meal_type' => $request->meal_type,
                'scheduled_at' => $request->datetime,
                'calories' => $request->calories,
                'status' => 'scheduled',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Meal scheduled successfully',
                'meal' => [
                    'id' => $meal,
                    'meal_type' => $request->meal_type,
                    'scheduled_at' => $request->datetime,
                    'calories' => $request->calories,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Schedule Meal Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to schedule meal',
            ], 500);
        }
    }

    /**
     * Schedule task via AI
     * POST /api/ai/schedule-task
     */
    public function scheduleTask(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'task_name' => 'required|string',
            'datetime' => 'required|date',
            'priority' => 'nullable|in:low,medium,high',
            'user_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try{
            $task = \DB::table('scheduled_tasks')->insertGetId([
                'user_id' => Auth::id(),
                'task_name' => $request->task_name,
                'scheduled_at' => $request->datetime,
                'priority' => $request->priority ?? 'medium',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Task scheduled successfully',
                'task' => [
                    'id' => $task,
                    'task_name' => $request->task_name,
                    'scheduled_at' => $request->datetime,
                    'priority' => $request->priority ?? 'medium',
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Schedule Task Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to schedule task',
            ], 500);
        }
    }

    /**
     * Parse natural language scheduling command
     * POST /api/ai/parse-scheduling-command
     */
    public function parseSchedulingCommand(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'command' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $command = strtolower($request->command);
            $parsed = [
                'type' => 'unknown',
                'datetime' => null,
                'details' => [],
            ];

            // Detect scheduling type
            if (preg_match('/\b(workout|exercise|training)\b/', $command)) {
                $parsed['type'] = 'workout';
            } elseif (preg_match('/\b(meal|eat|food|dinner|lunch|breakfast)\b/', $command)) {
                $parsed['type'] = 'meal';
            } elseif (preg_match('/\b(task|remind|todo)\b/', $command)) {
                $parsed['type'] = 'task';
            }

            // Extract time/date
            if (preg_match('/\b(tomorrow|today|tonight)\b/', $command, $matches)) {
                $when = $matches[1];
                if ($when === 'tomorrow') {
                    $parsed['datetime'] = now()->addDay()->setTime(9, 0)->toDateTimeString();
                } elseif ($when === 'today') {
                    $parsed['datetime'] = now()->setTime(14, 0)->toDateTimeString();
                } elseif ($when === 'tonight') {
                    $parsed['datetime'] = now()->setTime(19, 0)->toDateTimeString();
                }
            }

            return response()->json([
                'success' => true,
                'parsed' => $parsed,
            ]);

        } catch (\Exception $e) {
            Log::error('Parse Scheduling Command Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to parse command',
            ], 500);
        }
    }

    /**
     * Process workout command
     * POST /api/ai/process-workout-command
     */
    public function processWorkoutCommand(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'command' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Route to chat for processing
        $request->merge(['message' => $request->command]);
        return $this->chat($request);
    }

    /**
     * Sync with Apple Calendar
     * POST /api/ai/sync-apple-calendar
     */
    public function syncAppleCalendar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'calendar_token' => 'required|string',
            'user_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Store calendar sync info
            \DB::table('calendar_integrations')->updateOrInsert(
                ['user_id' => Auth::id()],
                [
                    'provider' => 'apple',
                    'token' => $request->calendar_token,
                    'last_synced_at' => now(),
                    'updated_at' => now(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Apple Calendar synced successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Apple Calendar Sync Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync Apple Calendar',
            ], 500);
        }
    }

    /**
     * Get scheduled events
     * GET /api/ai/get-scheduled-events
     */
    public function getScheduledEvents(Request $request)
    {
        $userId = $request->get('user_id', Auth::id());
        $startDate = $request->get('start_date', now()->toDateString());
        $endDate = $request->get('end_date', now()->addDays(7)->toDateString());

        try {
            $events = [
                'workouts' => \DB::table('scheduled_workouts')
                    ->where('user_id', $userId)
                    ->whereBetween('scheduled_at', [$startDate, $endDate])
                    ->get(),
                'meals' => \DB::table('scheduled_meals')
                    ->where('user_id', $userId)
                    ->whereBetween('scheduled_at', [$startDate, $endDate])
                    ->get(),
                'tasks' => \DB::table('scheduled_tasks')
                    ->where('user_id', $userId)
                    ->whereBetween('scheduled_at', [$startDate, $endDate])
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'events' => $events,
                'count' => count($events['workouts']) + count($events['meals']) + count($events['tasks']),
            ]);

        } catch (\Exception $e) {
            Log::error('Get Scheduled Events Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve events',
            ], 500);
        }
    }
}
