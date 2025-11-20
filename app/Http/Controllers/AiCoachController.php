<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

class AiCoachController extends Controller
{
    /**
     * Process AI Coach message
     */
    public function processMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'context' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = auth()->id();

        // Save user message
        $userMessageId = DB::table('ai_coach_messages')->insertGetId([
            'user_id' => $userId,
            'sender' => 'user',
            'message' => $request->message,
            'message_type' => 'text',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            // Process message with AI (placeholder - integrate with your AI service)
            $aiResponse = $this->getAIResponse($request->message, $userId, $request->context);

            // Save AI response
            $aiMessageId = DB::table('ai_coach_messages')->insertGetId([
                'user_id' => $userId,
                'sender' => 'coach',
                'message' => $aiResponse['message'],
                'message_type' => $aiResponse['type'] ?? 'text',
                'metadata' => json_encode($aiResponse['metadata'] ?? []),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => $aiResponse['message'],
                'type' => $aiResponse['type'] ?? 'text',
                'metadata' => $aiResponse['metadata'] ?? [],
                'message_id' => $aiMessageId
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get chat history
     */
    public function getChatHistory(Request $request)
    {
        $userId = auth()->id();
        $limit = $request->query('limit', 50);
        $offset = $request->query('offset', 0);

        $messages = DB::table('ai_coach_messages')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'success' => true,
            'messages' => $messages,
            'count' => $messages->count()
        ]);
    }

    /**
     * Clear chat history
     */
    public function clearHistory()
    {
        $userId = auth()->id();

        DB::table('ai_coach_messages')
            ->where('user_id', $userId)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Chat history cleared successfully'
        ]);
    }

    /**
     * Schedule workout via AI Coach
     */
    public function scheduleWorkout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'workout_type' => 'required|string',
            'datetime' => 'required|date',
            'duration' => 'nullable|integer|min:15',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = auth()->id();

        // Create workout calendar entry
        $workoutId = DB::table('workout_calendar')->insertGetId([
            'user_id' => $userId,
            'workout_type' => $request->workout_type,
            'scheduled_at' => $request->datetime,
            'duration_minutes' => $request->duration ?? 60,
            'status' => 'scheduled',
            'created_by' => 'ai_coach',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Workout scheduled successfully',
            'workout' => [
                'id' => $workoutId,
                'type' => $request->workout_type,
                'datetime' => $request->datetime,
                'duration' => $request->duration ?? 60
            ]
        ]);
    }

    /**
     * Get AI response (placeholder method - integrate with your AI service)
     */
    private function getAIResponse(string $message, int $userId, ?array $context = null)
    {
        // Get user profile for context
        $user = DB::table('users')->find($userId);

        // Simple keyword-based responses (replace with actual AI integration)
        $messageLower = strtolower($message);

        if (str_contains($messageLower, 'workout') || str_contains($messageLower, 'exercise')) {
            return [
                'message' => "I'd be happy to help you with your workout! Based on your profile, I recommend starting with a balanced routine. Would you like me to schedule a workout for you?",
                'type' => 'text',
                'metadata' => [
                    'suggested_actions' => ['schedule_workout', 'view_plans']
                ]
            ];
        }

        if (str_contains($messageLower, 'nutrition') || str_contains($messageLower, 'meal') || str_contains($messageLower, 'diet')) {
            return [
                'message' => "Let's talk about nutrition! Proper nutrition is key to reaching your fitness goals. What aspect of nutrition would you like to focus on?",
                'type' => 'text',
                'metadata' => [
                    'suggested_actions' => ['view_meal_plan', 'log_meal']
                ]
            ];
        }

        if (str_contains($messageLower, 'progress') || str_contains($messageLower, 'stats')) {
            $stats = DB::table('gamification_stats')->where('user_id', $userId)->first();
            return [
                'message' => "You're doing great! Your current streak is " . ($stats->current_streak ?? 0) . " days and you've earned " . ($stats->body_points ?? 0) . " body points. Keep up the excellent work!",
                'type' => 'text',
                'metadata' => [
                    'stats' => [
                        'streak' => $stats->current_streak ?? 0,
                        'points' => $stats->body_points ?? 0,
                        'level' => $stats->level ?? 1
                    ]
                ]
            ];
        }

        // Default response
        return [
            'message' => "I'm here to help you with your fitness journey! I can assist with workouts, nutrition, tracking progress, and answering questions. What would you like to know?",
            'type' => 'text',
            'metadata' => [
                'suggestions' => [
                    'Schedule a workout',
                    'Check my progress',
                    'Get nutrition advice'
                ]
            ]
        ];
    }

    /**
     * Get user context for AI
     */
    public function getUserContext()
    {
        $userId = auth()->id();

        $context = [
            'user' => DB::table('users')->find($userId),
            'stats' => DB::table('gamification_stats')->where('user_id', $userId)->first(),
            'recent_workouts' => DB::table('workout_logs')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
            'recent_meals' => DB::table('nutrition_logs')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'context' => $context
        ]);
    }
}
