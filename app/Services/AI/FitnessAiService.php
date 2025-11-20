<?php

namespace App\Services\AI;

use App\Models\WorkoutPlan;
use App\Models\Exercise;
use App\Models\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Fitness AI Service
 * Integrates AI with workout creation, exercise selection, and program design
 */
class FitnessAiService
{
    protected $apiKey;
    protected $apiEndpoint;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->apiEndpoint = 'https://api.openai.com/v1/chat/completions';
    }

    /**
     * Process general fitness-related AI queries
     */
    public function process(string $message, array $intent, array $context): array
    {
        try {
            // Detect specific fitness action
            $action = $this->detectFitnessAction($message);

            return match($action) {
                'create_workout' => $this->createWorkout($message, $context),
                'suggest_exercises' => $this->suggestExercises($message, $context),
                'analyze_program' => $this->analyzeProgram($message, $context),
                'modify_workout' => $this->modifyWorkout($message, $context),
                default => $this->handleGeneralFitnessQuery($message, $context),
            };

        } catch (\Exception $e) {
            Log::error('FitnessAiService Error', [
                'message' => $e->getMessage(),
                'context' => $context,
            ]);

            return [
                'message' => 'Failed to process fitness request',
                'data' => null,
            ];
        }
    }

    /**
     * Create a complete workout plan with AI
     */
    public function createWorkout(string $prompt, array $context): array
    {
        try {
            $clientId = $context['client_id'] ?? null;
            $client = $clientId ? Client::find($clientId) : null;

            // Build AI prompt with context
            $systemPrompt = $this->buildWorkoutSystemPrompt($client, $context);
            $userPrompt = $this->buildWorkoutUserPrompt($prompt, $context);

            // Call OpenAI GPT-4
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($this->apiEndpoint, [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.7,
                'max_tokens' => 2000,
            ]);

            if (!$response->successful()) {
                throw new \Exception('OpenAI API request failed: ' . $response->body());
            }

            $aiResponse = $response->json();
            $workoutData = $this->parseWorkoutResponse($aiResponse['choices'][0]['message']['content']);

            // Create workout in database
            $workout = $this->saveWorkout($workoutData, $context);

            return [
                'message' => 'Workout created successfully with AI',
                'data' => [
                    'workout' => $workout,
                    'ai_reasoning' => $workoutData['reasoning'] ?? null,
                    'recommendations' => $workoutData['recommendations'] ?? [],
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Create Workout Error', [
                'error' => $e->getMessage(),
                'prompt' => $prompt,
            ]);

            throw $e;
        }
    }

    /**
     * Suggest exercises based on criteria
     */
    public function suggestExercises(string $prompt, array $context): array
    {
        try {
            $systemPrompt = "You are a fitness expert. Suggest appropriate exercises based on the user's requirements. Consider equipment availability, experience level, and fitness goals. Return exercises in JSON format with: name, muscle_groups, difficulty, equipment_needed, instructions.";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->apiEndpoint, [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
                'max_tokens' => 1500,
            ]);

            $aiResponse = $response->json();
            $exercises = $this->parseExerciseSuggestions($aiResponse['choices'][0]['message']['content']);

            return [
                'message' => 'Exercise suggestions generated',
                'data' => [
                    'exercises' => $exercises,
                    'count' => count($exercises),
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Suggest Exercises Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Analyze existing workout program
     */
    public function analyzeProgram(string $message, array $context): array
    {
        try {
            $clientId = $context['client_id'] ?? null;
            $workoutId = $context['workout_id'] ?? null;

            if (!$workoutId) {
                return [
                    'message' => 'Please specify which workout to analyze',
                    'data' => null,
                ];
            }

            $workout = WorkoutPlan::with('exercises')->find($workoutId);

            if (!$workout) {
                return [
                    'message' => 'Workout not found',
                    'data' => null,
                ];
            }

            // Build analysis prompt
            $systemPrompt = "You are a fitness program analyst. Analyze the workout program for effectiveness, balance, and safety. Provide actionable recommendations for improvement.";

            $workoutDetails = $this->formatWorkoutForAnalysis($workout);
            $userPrompt = "Analyze this workout program:\n\n{$workoutDetails}\n\nProvide: 1) Overall assessment, 2) Strengths, 3) Weaknesses, 4) Recommendations for improvement";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(45)->post($this->apiEndpoint, [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.5,
                'max_tokens' => 1500,
            ]);

            $aiResponse = $response->json();
            $analysis = $this->parseAnalysisResponse($aiResponse['choices'][0]['message']['content']);

            return [
                'message' => 'Workout program analyzed',
                'data' => [
                    'workout' => $workout,
                    'analysis' => $analysis,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Analyze Program Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Modify existing workout with AI assistance
     */
    public function modifyWorkout(string $message, array $context): array
    {
        try {
            $workoutId = $context['workout_id'] ?? null;

            if (!$workoutId) {
                return [
                    'message' => 'Please specify which workout to modify',
                    'data' => null,
                ];
            }

            $workout = WorkoutPlan::with('exercises')->find($workoutId);

            if (!$workout) {
                return [
                    'message' => 'Workout not found',
                    'data' => null,
                ];
            }

            $systemPrompt = "You are a workout modification expert. Based on the user's request, suggest specific modifications to the workout. Maintain program balance and effectiveness.";

            $workoutDetails = $this->formatWorkoutForAnalysis($workout);
            $userPrompt = "Current workout:\n\n{$workoutDetails}\n\nModification request: {$message}\n\nProvide specific modifications in JSON format.";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(45)->post($this->apiEndpoint, [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.7,
                'max_tokens' => 1500,
            ]);

            $aiResponse = $response->json();
            $modifications = $this->parseModificationResponse($aiResponse['choices'][0]['message']['content']);

            return [
                'message' => 'Workout modifications suggested',
                'data' => [
                    'original_workout' => $workout,
                    'suggested_modifications' => $modifications,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Modify Workout Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Handle general fitness queries
     */
    protected function handleGeneralFitnessQuery(string $message, array $context): array
    {
        try {
            $systemPrompt = "You are a knowledgeable fitness coach. Answer fitness-related questions with practical, evidence-based advice. Keep responses concise and actionable.";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->apiEndpoint, [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $message],
                ],
                'temperature' => 0.7,
                'max_tokens' => 800,
            ]);

            $aiResponse = $response->json();
            $answer = $aiResponse['choices'][0]['message']['content'];

            return [
                'message' => $answer,
                'data' => null,
            ];

        } catch (\Exception $e) {
            Log::error('General Fitness Query Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Detect specific fitness action from message
     */
    protected function detectFitnessAction(string $message): string
    {
        $lowerMessage = strtolower($message);

        if (preg_match('/\b(create|build|design|make)\b.*\b(workout|program|plan)\b/', $lowerMessage)) {
            return 'create_workout';
        }

        if (preg_match('/\b(suggest|recommend|find)\b.*\b(exercise|movement)\b/', $lowerMessage)) {
            return 'suggest_exercises';
        }

        if (preg_match('/\b(analyze|review|evaluate|assess)\b/', $lowerMessage)) {
            return 'analyze_program';
        }

        if (preg_match('/\b(modify|change|adjust|update)\b/', $lowerMessage)) {
            return 'modify_workout';
        }

        return 'general';
    }

    /**
     * Build system prompt for workout creation
     */
    protected function buildWorkoutSystemPrompt($client, array $context): string
    {
        $prompt = "You are an expert fitness coach and program designer. Create effective, safe, and personalized workout programs.\n\n";

        if ($client) {
            $prompt .= "Client Information:\n";
            $prompt .= "- Name: {$client->name}\n";
            if ($client->fitness_level) $prompt .= "- Fitness Level: {$client->fitness_level}\n";
            if ($client->goals) $prompt .= "- Goals: {$client->goals}\n";
            if ($client->injuries) $prompt .= "- Injuries/Limitations: {$client->injuries}\n";
        }

        $prompt .= "\nRequirements:\n";
        if (isset($context['workout_type'])) $prompt .= "- Type: {$context['workout_type']}\n";
        if (isset($context['duration_minutes'])) $prompt .= "- Duration: {$context['duration_minutes']} minutes\n";
        if (isset($context['difficulty'])) $prompt .= "- Difficulty: {$context['difficulty']}\n";
        if (isset($context['equipment'])) $prompt .= "- Available Equipment: " . implode(', ', $context['equipment']) . "\n";

        $prompt .= "\nReturn workout in JSON format with:\n";
        $prompt .= "{\n";
        $prompt .= "  \"name\": \"Workout name\",\n";
        $prompt .= "  \"description\": \"Brief description\",\n";
        $prompt .= "  \"duration_minutes\": number,\n";
        $prompt .= "  \"difficulty\": \"beginner|intermediate|advanced\",\n";
        $prompt .= "  \"exercises\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"name\": \"Exercise name\",\n";
        $prompt .= "      \"sets\": number,\n";
        $prompt .= "      \"reps\": \"number or time\",\n";
        $prompt .= "      \"rest_seconds\": number,\n";
        $prompt .= "      \"instructions\": \"How to perform\",\n";
        $prompt .= "      \"muscle_groups\": [\"group1\", \"group2\"]\n";
        $prompt .= "    }\n";
        $prompt .= "  ],\n";
        $prompt .= "  \"reasoning\": \"Why this program works\",\n";
        $prompt .= "  \"recommendations\": [\"tip1\", \"tip2\"]\n";
        $prompt .= "}";

        return $prompt;
    }

    /**
     * Build user prompt for workout creation
     */
    protected function buildWorkoutUserPrompt(string $prompt, array $context): string
    {
        return "Create a workout based on this request: {$prompt}";
    }

    /**
     * Parse AI workout response
     */
    protected function parseWorkoutResponse(string $response): array
    {
        // Try to extract JSON from response
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            try {
                return json_decode($matches[0], true);
            } catch (\Exception $e) {
                Log::warning('Failed to parse workout JSON', ['response' => $response]);
            }
        }

        // Fallback parsing
        return [
            'name' => 'AI Generated Workout',
            'description' => $response,
            'exercises' => [],
        ];
    }

    /**
     * Parse exercise suggestions
     */
    protected function parseExerciseSuggestions(string $response): array
    {
        if (preg_match('/\[.*\]/s', $response, $matches)) {
            try {
                return json_decode($matches[0], true);
            } catch (\Exception $e) {
                Log::warning('Failed to parse exercises JSON', ['response' => $response]);
            }
        }

        return [];
    }

    /**
     * Parse analysis response
     */
    protected function parseAnalysisResponse(string $response): array
    {
        return [
            'full_analysis' => $response,
            'parsed' => $this->extractSections($response),
        ];
    }

    /**
     * Parse modification response
     */
    protected function parseModificationResponse(string $response): array
    {
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            try {
                return json_decode($matches[0], true);
            } catch (\Exception $e) {
                Log::warning('Failed to parse modifications JSON');
            }
        }

        return ['suggestions' => $response];
    }

    /**
     * Extract sections from text
     */
    protected function extractSections(string $text): array
    {
        $sections = [];

        if (preg_match('/Overall Assessment:(.+?)(?=Strengths:|$)/s', $text, $matches)) {
            $sections['assessment'] = trim($matches[1]);
        }

        if (preg_match('/Strengths:(.+?)(?=Weaknesses:|$)/s', $text, $matches)) {
            $sections['strengths'] = trim($matches[1]);
        }

        if (preg_match('/Weaknesses:(.+?)(?=Recommendations:|$)/s', $text, $matches)) {
            $sections['weaknesses'] = trim($matches[1]);
        }

        if (preg_match('/Recommendations:(.+?)$/s', $text, $matches)) {
            $sections['recommendations'] = trim($matches[1]);
        }

        return $sections;
    }

    /**
     * Format workout for analysis
     */
    protected function formatWorkoutForAnalysis($workout): string
    {
        $formatted = "Workout: {$workout->name}\n";
        $formatted .= "Type: {$workout->type}\n";
        $formatted .= "Duration: {$workout->duration_minutes} minutes\n\n";
        $formatted .= "Exercises:\n";

        foreach ($workout->exercises as $index => $exercise) {
            $formatted .= ($index + 1) . ". {$exercise->name}\n";
            $formatted .= "   Sets: {$exercise->sets}, Reps: {$exercise->reps}\n";
            if ($exercise->rest_seconds) {
                $formatted .= "   Rest: {$exercise->rest_seconds}s\n";
            }
        }

        return $formatted;
    }

    /**
     * Save workout to database
     */
    protected function saveWorkout(array $workoutData, array $context): WorkoutPlan
    {
        DB::beginTransaction();

        try {
            $workout = WorkoutPlan::create([
                'user_id' => $context['user_id'],
                'client_id' => $context['client_id'] ?? null,
                'name' => $workoutData['name'] ?? 'AI Generated Workout',
                'description' => $workoutData['description'] ?? '',
                'type' => $context['workout_type'] ?? 'custom',
                'duration_minutes' => $workoutData['duration_minutes'] ?? $context['duration_minutes'] ?? 45,
                'difficulty' => $workoutData['difficulty'] ?? $context['difficulty'] ?? 'intermediate',
                'ai_generated' => true,
            ]);

            // Save exercises
            if (isset($workoutData['exercises'])) {
                foreach ($workoutData['exercises'] as $order => $exerciseData) {
                    $workout->exercises()->create([
                        'name' => $exerciseData['name'],
                        'sets' => $exerciseData['sets'] ?? 3,
                        'reps' => $exerciseData['reps'] ?? 10,
                        'rest_seconds' => $exerciseData['rest_seconds'] ?? 60,
                        'instructions' => $exerciseData['instructions'] ?? '',
                        'order' => $order + 1,
                    ]);
                }
            }

            DB::commit();

            return $workout->load('exercises');

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
