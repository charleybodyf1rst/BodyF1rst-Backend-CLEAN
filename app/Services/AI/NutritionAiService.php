<?php

namespace App\Services\AI;

use App\Models\NutritionPlan;
use App\Models\Meal;
use App\Models\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Nutrition AI Service
 * Integrates AI with meal planning, nutrition analysis, and dietary recommendations
 */
class NutritionAiService
{
    protected $apiKey;
    protected $apiEndpoint;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->apiEndpoint = 'https://api.openai.com/v1/chat/completions';
    }

    /**
     * Process general nutrition-related AI queries
     */
    public function process(string $message, array $intent, array $context): array
    {
        try {
            $action = $this->detectNutritionAction($message);

            return match($action) {
                'create_meal_plan' => $this->createMealPlan($message, $context),
                'analyze_nutrition' => $this->analyzeNutrition($message, $context),
                'suggest_meals' => $this->suggestMeals($message, $context),
                'calculate_macros' => $this->calculateMacros($message, $context),
                default => $this->handleGeneralNutritionQuery($message, $context),
            };

        } catch (\Exception $e) {
            Log::error('NutritionAiService Error', [
                'message' => $e->getMessage(),
                'context' => $context,
            ]);

            return [
                'message' => 'Failed to process nutrition request',
                'data' => null,
            ];
        }
    }

    /**
     * Create a complete meal plan with AI
     */
    public function createMealPlan(string $prompt, array $context): array
    {
        try {
            $clientId = $context['client_id'] ?? null;
            $client = $clientId ? Client::find($clientId) : null;

            // Build AI prompt with context
            $systemPrompt = $this->buildMealPlanSystemPrompt($client, $context);
            $userPrompt = "Create a meal plan based on this request: {$prompt}";

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
                'max_tokens' => 2500,
            ]);

            if (!$response->successful()) {
                throw new \Exception('OpenAI API request failed: ' . $response->body());
            }

            $aiResponse = $response->json();
            $mealPlanData = $this->parseMealPlanResponse($aiResponse['choices'][0]['message']['content']);

            // Save meal plan to database
            $mealPlan = $this->saveMealPlan($mealPlanData, $context);

            return [
                'message' => 'Meal plan created successfully with AI',
                'data' => [
                    'meal_plan' => $mealPlan,
                    'nutrition_summary' => $mealPlanData['nutrition_summary'] ?? null,
                    'recommendations' => $mealPlanData['recommendations'] ?? [],
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Create Meal Plan Error', [
                'error' => $e->getMessage(),
                'prompt' => $prompt,
            ]);

            throw $e;
        }
    }

    /**
     * Analyze nutrition for a meal or plan
     */
    public function analyzeNutrition(string $message, array $context): array
    {
        try {
            $planId = $context['plan_id'] ?? null;

            if ($planId) {
                $plan = NutritionPlan::with('meals')->find($planId);

                if (!$plan) {
                    return [
                        'message' => 'Nutrition plan not found',
                        'data' => null,
                    ];
                }

                $analysisData = $this->formatPlanForAnalysis($plan);
            } else {
                $analysisData = $message;
            }

            $systemPrompt = "You are a registered dietitian and nutrition expert. Analyze nutritional content for balance, adequacy, and health impact. Provide actionable recommendations.";

            $userPrompt = "Analyze this nutrition data:\n\n{$analysisData}\n\nProvide: 1) Nutritional adequacy, 2) Macro balance, 3) Potential deficiencies, 4) Recommendations";

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
                'message' => 'Nutrition analysis completed',
                'data' => [
                    'analysis' => $analysis,
                    'plan' => $plan ?? null,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Analyze Nutrition Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Suggest meals based on criteria
     */
    public function suggestMeals(string $prompt, array $context): array
    {
        try {
            $systemPrompt = "You are a creative nutrition coach. Suggest delicious, balanced meals based on dietary requirements. Include macros and preparation instructions. Return in JSON format.";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->apiEndpoint, [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.8,
                'max_tokens' => 1500,
            ]);

            $aiResponse = $response->json();
            $meals = $this->parseMealSuggestions($aiResponse['choices'][0]['message']['content']);

            return [
                'message' => 'Meal suggestions generated',
                'data' => [
                    'meals' => $meals,
                    'count' => count($meals),
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Suggest Meals Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Calculate macros for goals
     */
    public function calculateMacros(string $message, array $context): array
    {
        try {
            $clientId = $context['client_id'] ?? null;
            $client = $clientId ? Client::find($clientId) : null;

            $systemPrompt = "You are a nutrition calculator. Calculate optimal macronutrient targets based on client data and goals. Consider BMR, activity level, and objectives.";

            $clientInfo = $client ? $this->formatClientForMacros($client) : '';
            $userPrompt = "Client information:\n{$clientInfo}\n\nRequest: {$message}\n\nCalculate optimal daily macros (protein, carbs, fats, calories) in JSON format.";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->apiEndpoint, [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.3,
                'max_tokens' => 800,
            ]);

            $aiResponse = $response->json();
            $macros = $this->parseMacroResponse($aiResponse['choices'][0]['message']['content']);

            return [
                'message' => 'Macro targets calculated',
                'data' => [
                    'macros' => $macros,
                    'client' => $client,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Calculate Macros Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Handle general nutrition queries
     */
    protected function handleGeneralNutritionQuery(string $message, array $context): array
    {
        try {
            $systemPrompt = "You are a knowledgeable nutrition expert. Answer nutrition-related questions with evidence-based advice. Keep responses practical and actionable.";

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
            Log::error('General Nutrition Query Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Detect specific nutrition action
     */
    protected function detectNutritionAction(string $message): string
    {
        $lowerMessage = strtolower($message);

        if (preg_match('/\b(create|build|design|generate)\b.*\b(meal plan|diet|menu)\b/', $lowerMessage)) {
            return 'create_meal_plan';
        }

        if (preg_match('/\b(analyze|review|evaluate|assess)\b/', $lowerMessage)) {
            return 'analyze_nutrition';
        }

        if (preg_match('/\b(suggest|recommend|ideas?)\b.*\b(meal|food|recipe)\b/', $lowerMessage)) {
            return 'suggest_meals';
        }

        if (preg_match('/\b(calculate|compute|determine)\b.*\b(macro|calorie|protein|carb|fat)\b/', $lowerMessage)) {
            return 'calculate_macros';
        }

        return 'general';
    }

    /**
     * Build system prompt for meal plan creation
     */
    protected function buildMealPlanSystemPrompt($client, array $context): string
    {
        $prompt = "You are an expert nutritionist and meal planner. Create balanced, practical, and delicious meal plans tailored to client needs.\n\n";

        if ($client) {
            $prompt .= "Client Information:\n";
            $prompt .= "- Name: {$client->name}\n";
            if ($client->weight) $prompt .= "- Weight: {$client->weight} kg\n";
            if ($client->height) $prompt .= "- Height: {$client->height} cm\n";
            if ($client->dietary_restrictions) $prompt .= "- Dietary Restrictions: {$client->dietary_restrictions}\n";
            if ($client->goals) $prompt .= "- Goals: {$client->goals}\n";
        }

        $prompt .= "\nRequirements:\n";
        if (isset($context['goal'])) $prompt .= "- Goal: {$context['goal']}\n";
        if (isset($context['daily_calories'])) $prompt .= "- Daily Calories: {$context['daily_calories']}\n";
        if (isset($context['meal_count'])) $prompt .= "- Meals per Day: {$context['meal_count']}\n";
        if (isset($context['dietary_restrictions'])) {
            $prompt .= "- Restrictions: " . implode(', ', $context['dietary_restrictions']) . "\n";
        }

        $prompt .= "\nReturn meal plan in JSON format:\n";
        $prompt .= "{\n";
        $prompt .= "  \"name\": \"Plan name\",\n";
        $prompt .= "  \"description\": \"Brief description\",\n";
        $prompt .= "  \"daily_calories\": number,\n";
        $prompt .= "  \"meals\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"name\": \"Meal name\",\n";
        $prompt .= "      \"meal_type\": \"breakfast|lunch|dinner|snack\",\n";
        $prompt .= "      \"foods\": [\n";
        $prompt .= "        {\n";
        $prompt .= "          \"name\": \"Food item\",\n";
        $prompt .= "          \"amount\": \"quantity\",\n";
        $prompt .= "          \"calories\": number,\n";
        $prompt .= "          \"protein_g\": number,\n";
        $prompt .= "          \"carbs_g\": number,\n";
        $prompt .= "          \"fat_g\": number\n";
        $prompt .= "        }\n";
        $prompt .= "      ],\n";
        $prompt .= "      \"instructions\": \"Preparation steps\"\n";
        $prompt .= "    }\n";
        $prompt .= "  ],\n";
        $prompt .= "  \"nutrition_summary\": {\n";
        $prompt .= "    \"total_calories\": number,\n";
        $prompt .= "    \"protein_g\": number,\n";
        $prompt .= "    \"carbs_g\": number,\n";
        $prompt .= "    \"fat_g\": number\n";
        $prompt .= "  },\n";
        $prompt .= "  \"recommendations\": [\"tip1\", \"tip2\"]\n";
        $prompt .= "}";

        return $prompt;
    }

    /**
     * Parse meal plan response
     */
    protected function parseMealPlanResponse(string $response): array
    {
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            try {
                return json_decode($matches[0], true);
            } catch (\Exception $e) {
                Log::warning('Failed to parse meal plan JSON', ['response' => $response]);
            }
        }

        return [
            'name' => 'AI Generated Meal Plan',
            'description' => $response,
            'meals' => [],
        ];
    }

    /**
     * Parse meal suggestions
     */
    protected function parseMealSuggestions(string $response): array
    {
        if (preg_match('/\[.*\]/s', $response, $matches)) {
            try {
                return json_decode($matches[0], true);
            } catch (\Exception $e) {
                Log::warning('Failed to parse meal suggestions JSON');
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
     * Parse macro calculation response
     */
    protected function parseMacroResponse(string $response): array
    {
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            try {
                return json_decode($matches[0], true);
            } catch (\Exception $e) {
                Log::warning('Failed to parse macro JSON');
            }
        }

        return [];
    }

    /**
     * Extract sections from text
     */
    protected function extractSections(string $text): array
    {
        $sections = [];

        if (preg_match('/Nutritional Adequacy:(.+?)(?=Macro Balance:|$)/s', $text, $matches)) {
            $sections['adequacy'] = trim($matches[1]);
        }

        if (preg_match('/Macro Balance:(.+?)(?=Potential Deficiencies:|$)/s', $text, $matches)) {
            $sections['balance'] = trim($matches[1]);
        }

        if (preg_match('/Potential Deficiencies:(.+?)(?=Recommendations:|$)/s', $text, $matches)) {
            $sections['deficiencies'] = trim($matches[1]);
        }

        if (preg_match('/Recommendations:(.+?)$/s', $text, $matches)) {
            $sections['recommendations'] = trim($matches[1]);
        }

        return $sections;
    }

    /**
     * Format nutrition plan for analysis
     */
    protected function formatPlanForAnalysis($plan): string
    {
        $formatted = "Meal Plan: {$plan->name}\n";
        $formatted .= "Daily Calories: {$plan->daily_calories}\n\n";
        $formatted .= "Meals:\n";

        foreach ($plan->meals as $index => $meal) {
            $formatted .= ($index + 1) . ". {$meal->name} ({$meal->meal_type})\n";
            $formatted .= "   Calories: {$meal->calories}\n";
            $formatted .= "   Protein: {$meal->protein_g}g, Carbs: {$meal->carbs_g}g, Fat: {$meal->fat_g}g\n";
        }

        return $formatted;
    }

    /**
     * Format client data for macro calculation
     */
    protected function formatClientForMacros($client): string
    {
        $formatted = "Name: {$client->name}\n";
        if ($client->age) $formatted .= "Age: {$client->age}\n";
        if ($client->weight) $formatted .= "Weight: {$client->weight} kg\n";
        if ($client->height) $formatted .= "Height: {$client->height} cm\n";
        if ($client->gender) $formatted .= "Gender: {$client->gender}\n";
        if ($client->activity_level) $formatted .= "Activity Level: {$client->activity_level}\n";
        if ($client->goals) $formatted .= "Goals: {$client->goals}\n";

        return $formatted;
    }

    /**
     * Save meal plan to database
     */
    protected function saveMealPlan(array $mealPlanData, array $context): NutritionPlan
    {
        DB::beginTransaction();

        try {
            $plan = NutritionPlan::create([
                'user_id' => $context['user_id'],
                'client_id' => $context['client_id'] ?? null,
                'name' => $mealPlanData['name'] ?? 'AI Generated Meal Plan',
                'description' => $mealPlanData['description'] ?? '',
                'goal' => $context['goal'] ?? 'general',
                'daily_calories' => $mealPlanData['daily_calories'] ?? $context['daily_calories'] ?? 2000,
                'ai_generated' => true,
            ]);

            // Save meals
            if (isset($mealPlanData['meals'])) {
                foreach ($mealPlanData['meals'] as $mealData) {
                    $totalCalories = 0;
                    $totalProtein = 0;
                    $totalCarbs = 0;
                    $totalFat = 0;

                    if (isset($mealData['foods'])) {
                        foreach ($mealData['foods'] as $food) {
                            $totalCalories += $food['calories'] ?? 0;
                            $totalProtein += $food['protein_g'] ?? 0;
                            $totalCarbs += $food['carbs_g'] ?? 0;
                            $totalFat += $food['fat_g'] ?? 0;
                        }
                    }

                    $plan->meals()->create([
                        'name' => $mealData['name'],
                        'meal_type' => $mealData['meal_type'] ?? 'meal',
                        'calories' => $totalCalories,
                        'protein_g' => $totalProtein,
                        'carbs_g' => $totalCarbs,
                        'fat_g' => $totalFat,
                        'foods' => json_encode($mealData['foods'] ?? []),
                        'instructions' => $mealData['instructions'] ?? '',
                    ]);
                }
            }

            DB::commit();

            return $plan->load('meals');

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
