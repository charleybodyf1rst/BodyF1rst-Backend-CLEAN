<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\MealPlan;
use App\Models\NutritionLog;
use App\Models\User;
use App\Services\PassioClient;

class PassioNutritionController extends Controller
{
    private PassioClient $passioClient;

    public function __construct(PassioClient $passioClient)
    {
        $this->passioClient = $passioClient;
    }

    /**
     * Test connectivity to Passio API
     */
    public function ping(): JsonResponse
    {
        $result = $this->passioClient->ping();
        
        return response()->json($result, $result['status'] === 'success' ? 200 : 500);
    }

    /**
     * Preview meal plan generation (testing endpoint)
     */
    public function preview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'dietType' => 'nullable|string|in:balanced,low-carb,high-protein,vegetarian,vegan',
            'calorieTarget' => 'nullable|integer|min:1200|max:5000',
            'allergies' => 'nullable|array',
            'dislikes' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $parameters = [
            'diet_type' => $request->input('dietType', 'balanced'),
            'calorie_target' => $request->input('calorieTarget', 2000),
            'allergies' => $request->input('allergies', []),
            'dislikes' => $request->input('dislikes', [])
        ];

        $mealPlan = $this->passioClient->fetchMealPlan($parameters);

        if (!$mealPlan) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate meal plan preview'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $mealPlan
        ]);
    }

    /**
     * Fetch meal plan from Passio Hub API
     */
    public function fetchMealPlan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'userId' => 'required|string|exists:users,id',
            'dietType' => 'nullable|string|in:balanced,low-carb,high-protein,vegetarian,vegan',
            'calorieTarget' => 'nullable|integer|min:1200|max:5000',
            'allergies' => 'nullable|array',
            'allergies.*' => 'string',
            'dislikes' => 'nullable|array',
            'dislikes.*' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::findOrFail($request->input('userId'));
            
            $passioParams = [
                'user_id' => $user->id,
                'diet_type' => $request->input('dietType', 'balanced'),
                'calorie_target' => $request->input('calorieTarget', $this->calculateCalorieTarget($user)),
                'allergies' => $request->input('allergies', []),
                'dislikes' => $request->input('dislikes', [])
            ];

            $mealPlanData = $this->passioClient->fetchMealPlan($passioParams);

            if (!$mealPlanData) {
                Log::error('Failed to fetch meal plan from Passio API');
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch meal plan from Passio'
                ], 500);
            }
            
            // Store meal plan in database
            $mealPlan = MealPlan::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'date' => now()->toDateString()
                ],
                [
                    'passio_plan_id' => $mealPlanData['id'],
                    'name' => $mealPlanData['name'],
                    'description' => $mealPlanData['description'] ?? '',
                    'total_calories' => $mealPlanData['totalCalories'],
                    'macros' => $mealPlanData['macros'],
                    'meals' => $mealPlanData['meals'],
                    'preferences' => $passioParams
                ]
            );

            return response()->json([
                'success' => true,
                'data' => $this->formatMealPlanResponse($mealPlan, $mealPlanData)
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching meal plan', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Sync nutrition data with Passio API
     */
    public function syncNutrition(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'userId' => 'required|string|exists:users,id',
            'date' => 'required|date',
            'meals' => 'required|array',
            'meals.*.id' => 'required|string',
            'meals.*.name' => 'required|string',
            'meals.*.type' => 'required|string|in:breakfast,lunch,dinner,snack',
            'meals.*.calories' => 'required|numeric|min:0',
            'meals.*.macros' => 'required|array',
            'meals.*.foods' => 'required|array',
            'totalCalories' => 'required|numeric|min:0',
            'macros' => 'required|array',
            'waterIntake' => 'nullable|numeric|min:0',
            'exerciseCaloriesBurned' => 'nullable|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::findOrFail($request->input('userId'));
            
            $nutritionLog = NutritionLog::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'date' => $request->input('date')
                ],
                [
                    'meals' => $request->input('meals'),
                    'total_calories' => $request->input('totalCalories'),
                    'macros' => $request->input('macros'),
                    'water_intake' => $request->input('waterIntake', 0),
                    'exercise_calories_burned' => $request->input('exerciseCaloriesBurned', 0),
                    'synced_at' => now()
                ]
            );

            // Sync with Passio Hub if needed
            $this->syncWithPassioHub($user, $nutritionLog);

            return response()->json([
                'success' => true,
                'message' => 'Nutrition data synced successfully',
                'data' => [
                    'id' => $nutritionLog->id,
                    'syncedAt' => $nutritionLog->synced_at
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error syncing nutrition data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Search foods in Passio database
     */
    public function searchFoods(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2',
            'limit' => 'nullable|integer|min:1|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $searchResults = $this->passioClient->searchFood($request->input('query'));

            if (!$searchResults) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to search foods'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => $searchResults
            ]);

        } catch (\Exception $e) {
            Log::error('Error searching foods', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get food by barcode
     */
    public function getFoodByBarcode(string $barcode): JsonResponse
    {
        try {
            $foodData = $this->passioClient->getNutritionData($barcode);

            if (!$foodData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Food not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $foodData
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching food by barcode', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get daily nutrition summary
     */
    public function getDailyNutrition(string $userId, string $date): JsonResponse
    {
        try {
            $user = User::findOrFail($userId);
            $nutritionLog = NutritionLog::where('user_id', $user->id)
                ->where('date', $date)
                ->first();

            if (!$nutritionLog) {
                return response()->json([
                    'success' => false,
                    'message' => 'No nutrition data found for this date'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'userId' => $user->id,
                    'date' => $nutritionLog->date,
                    'meals' => $nutritionLog->meals,
                    'totalCalories' => $nutritionLog->total_calories,
                    'macros' => $nutritionLog->macros,
                    'waterIntake' => $nutritionLog->water_intake,
                    'exerciseCaloriesBurned' => $nutritionLog->exercise_calories_burned
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching daily nutrition', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Calculate calorie target based on user profile
     */
    private function calculateCalorieTarget(User $user): int
    {
        // Basic BMR calculation (Harris-Benedict equation)
        $age = $user->age ?? 30;
        $weight = $user->weight ?? 70; // kg
        $height = $user->height ?? 170; // cm
        $gender = $user->gender ?? 'male';

        if ($gender === 'male') {
            $bmr = 88.362 + (13.397 * $weight) + (4.799 * $height) - (5.677 * $age);
        } else {
            $bmr = 447.593 + (9.247 * $weight) + (3.098 * $height) - (4.330 * $age);
        }

        // Apply activity factor (assuming moderate activity)
        $activityFactor = 1.55;
        
        return (int) round($bmr * $activityFactor);
    }

    /**
     * Format meal plan response
     */
    private function formatMealPlanResponse(MealPlan $mealPlan, array $passioData): array
    {
        return [
            'id' => $passioData['id'],
            'name' => $mealPlan->name,
            'description' => $mealPlan->description,
            'meals' => $mealPlan->meals,
            'totalCalories' => $mealPlan->total_calories,
            'macros' => $mealPlan->macros,
            'createdDate' => $mealPlan->created_at
        ];
    }

    /**
     * Sync nutrition data with Passio Hub
     */
    private function syncWithPassioHub(User $user, NutritionLog $nutritionLog): void
    {
        try {
            $this->passioClient->request('POST', '/v1/nutrition-logs', [
                'user_id' => $user->id,
                'date' => $nutritionLog->date,
                'meals' => $nutritionLog->meals,
                'total_calories' => $nutritionLog->total_calories,
                'macros' => $nutritionLog->macros
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to sync with Passio Hub', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Voice log food using speech-to-text and Passio AI
     * POST /api/passio/voice-log-food
     */
    public function voiceLogFood(Request $request)
    {
        $validated = $request->validate([
            'audio' => 'required|file|mimes:wav,mp3,m4a,ogg|max:10240', // 10MB max
            'user_id' => 'nullable|exists:users,id'
        ]);

        $userId = $validated['user_id'] ?? Auth::id();

        try {
            // In a real implementation, you would:
            // 1. Convert audio to text using a speech-to-text service (AWS Transcribe, Google Speech-to-Text, etc.)
            // 2. Parse the text to extract food items
            // 3. Use Passio AI to search for those food items
            // 4. Log the nutrition data

            // For now, return a mock response that indicates the endpoint is ready for integration
            return response()->json([
                'success' => true,
                'message' => 'Voice logging endpoint ready',
                'data' => [
                    'note' => 'Speech-to-text integration pending. Configure AWS Transcribe or Google Speech-to-Text.',
                    'next_steps' => [
                        '1. Transcribe audio to text',
                        '2. Parse food items from transcription',
                        '3. Search Passio AI for food items',
                        '4. Log nutrition data to user profile'
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process voice input: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recognize food from image using Passio AI
     * POST /api/passio/recognize-food
     *
     * Legacy compatibility endpoint for frontend calls to /passio/recognize-food.php
     */
    public function recognizeFood(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpg,jpeg,png|max:10240', // 10MB max
            'user_id' => 'nullable|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Upload image to temporary storage
            $imagePath = $request->file('image')->store('temp/food-recognition', 'public');
            $fullPath = storage_path('app/public/' . $imagePath);

            // Use Passio AI to recognize food from image
            // This would call the Passio Nutrition AI API's image recognition endpoint
            $recognizedFoods = $this->passioClient->recognizeFoodFromImage($fullPath);

            if (!$recognizedFoods) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to recognize food from image'
                ], 500);
            }

            // Clean up temporary file
            @unlink($fullPath);

            return response()->json([
                'success' => true,
                'data' => [
                    'recognized_foods' => $recognizedFoods,
                    'count' => count($recognizedFoods),
                    'confidence_threshold' => 0.7
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error recognizing food from image', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate meal plan for user
     * POST /api/passio/generate-meal-plan
     *
     * Legacy compatibility endpoint for frontend calls to /passio/generate-meal-plan.php
     */
    public function generateMealPlan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'days' => 'nullable|integer|min:1|max:30',
            'diet_type' => 'nullable|string|in:balanced,low-carb,high-protein,vegetarian,vegan,keto,paleo',
            'calorie_target' => 'nullable|integer|min:1200|max:5000',
            'meals_per_day' => 'nullable|integer|min:3|max:6',
            'allergies' => 'nullable|array',
            'dislikes' => 'nullable|array',
            'preferences' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::findOrFail($request->input('user_id'));
            $days = $request->input('days', 7);

            $planParams = [
                'user_id' => $user->id,
                'days' => $days,
                'diet_type' => $request->input('diet_type', 'balanced'),
                'calorie_target' => $request->input('calorie_target', $this->calculateCalorieTarget($user)),
                'meals_per_day' => $request->input('meals_per_day', 3),
                'allergies' => $request->input('allergies', []),
                'dislikes' => $request->input('dislikes', []),
                'preferences' => $request->input('preferences', [])
            ];

            // Generate meal plan using Passio AI
            $mealPlanData = $this->passioClient->generateMealPlan($planParams);

            if (!$mealPlanData) {
                Log::error('Failed to generate meal plan from Passio API');

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate meal plan'
                ], 500);
            }

            // Store meal plan in database
            $mealPlan = MealPlan::create([
                'user_id' => $user->id,
                'passio_plan_id' => $mealPlanData['id'] ?? uniqid('plan_'),
                'name' => $mealPlanData['name'] ?? "{$days}-Day {$planParams['diet_type']} Plan",
                'description' => $mealPlanData['description'] ?? '',
                'total_calories' => $mealPlanData['totalCalories'] ?? $planParams['calorie_target'],
                'macros' => $mealPlanData['macros'] ?? [],
                'meals' => $mealPlanData['meals'] ?? [],
                'preferences' => $planParams,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays($days - 1)->toDateString()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Meal plan generated successfully',
                'data' => [
                    'plan_id' => $mealPlan->id,
                    'passio_plan_id' => $mealPlan->passio_plan_id,
                    'name' => $mealPlan->name,
                    'description' => $mealPlan->description,
                    'days' => $days,
                    'meals' => $mealPlan->meals,
                    'total_calories' => $mealPlan->total_calories,
                    'macros' => $mealPlan->macros,
                    'start_date' => $mealPlan->start_date,
                    'end_date' => $mealPlan->end_date
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating meal plan', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }
}
