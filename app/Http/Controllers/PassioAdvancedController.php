<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Services\PassioClient;
use App\Models\User;
use App\Models\FoodLog;
use App\Models\NutritionLog;
use App\Models\MealPlan;
use Carbon\Carbon;

class PassioAdvancedController extends Controller
{
    private PassioClient $passioClient;

    public function __construct(PassioClient $passioClient)
    {
        $this->passioClient = $passioClient;
    }

    /**
     * CAMERA FOOD RECOGNITION - Main feature for visual food identification
     * Analyzes food from camera/image using Passio AI
     */
    public function recognizeFoodFromImage(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'image' => 'required|image|max:10240', // 10MB max
                'image_base64' => 'nullable|string', // Alternative: base64 encoded image
                'detection_type' => 'nullable|string|in:single,multiple,meal', // single food, multiple items, or full meal
                'include_alternatives' => 'nullable|boolean',
                'include_nutrition' => 'nullable|boolean',
                'user_id' => 'nullable|integer|exists:users,id'
            ]);

            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageData = base64_encode(file_get_contents($image->getRealPath()));
            } else if (!empty($validated['image_base64'])) {
                $imageData = $validated['image_base64'];
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No image provided'
                ], 400);
            }

            $recognitionData = $this->passioClient->request('POST', '/v2/vision/food-recognition', [
                'image' => $imageData,
                'detection_type' => $validated['detection_type'] ?? 'single',
                'include_alternatives' => $validated['include_alternatives'] ?? true,
                'include_nutrition' => $validated['include_nutrition'] ?? true,
                'user_preferences' => $this->getUserPreferences($validated['user_id'] ?? Auth::id())
            ]);

            if (!$recognitionData || !isset($recognitionData['foods'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not recognize food in the image'
                ], 422);
            }

            $results = [];
            foreach ($recognitionData['foods'] as $food) {
                $foodItem = [
                    'passio_id' => $food['passioID'],
                    'name' => $food['name'],
                    'confidence' => $food['confidence'] ?? 0.0,
                    'bounding_box' => $food['boundingBox'] ?? null,
                    'portion_size' => $food['portionSize'] ?? 'standard',
                    'nutrition' => $food['nutrition'] ?? null,
                    'alternatives' => $food['alternatives'] ?? [],
                    'tags' => $food['tags'] ?? [],
                    'food_type' => $food['foodType'] ?? 'unknown'
                ];

                if ($validated['include_nutrition'] ?? true) {
                    $nutritionDetails = $this->getDetailedNutrition($food['passioID']);
                    $foodItem['detailed_nutrition'] = $nutritionDetails;
                }

                if ($validated['include_alternatives'] ?? true) {
                    $alternatives = $this->getFoodAlternatives($food['passioID']);
                    $foodItem['healthier_alternatives'] = $alternatives;
                }

                $results[] = $foodItem;
            }

            if ($userId = $validated['user_id'] ?? Auth::id()) {
                $this->logFoodRecognition($userId, $results);
            }

            return response()->json([
                'success' => true,
                'message' => 'Food recognized successfully',
                'detection_type' => $validated['detection_type'] ?? 'single',
                'foods' => $results,
                'total_nutrition' => $this->calculateTotalNutrition($results),
                'meal_suggestions' => $this->getMealSuggestions($results)
            ]);

        } catch (\Exception $e) {
            Log::error('Food recognition error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to recognize food from image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * BARCODE SCANNING - Scan product barcodes for nutrition info
     */
    public function scanBarcode(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'barcode' => 'required|string',
                'barcode_type' => 'nullable|string|in:upc,ean,qr',
                'include_alternatives' => 'nullable|boolean',
                'user_id' => 'nullable|integer|exists:users,id'
            ]);

            $productData = $this->passioClient->request('GET', "/v2/barcode/{$validated['barcode']}", [
                'type' => $validated['barcode_type'] ?? 'auto',
                'include_nutrition' => true,
                'include_ingredients' => true
            ]);

            if (!$productData) {
                $productData = $this->fallbackBarcodeSearch($validated['barcode']);

                if (!$productData) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Product not found'
                    ], 404);
                }
            }

            $product = [
                'barcode' => $validated['barcode'],
                'name' => $productData['name'],
                'brand' => $productData['brand'] ?? null,
                'category' => $productData['category'] ?? null,
                'image_url' => $productData['imageUrl'] ?? null,
                'serving_size' => $productData['servingSize'] ?? null,
                'nutrition' => $productData['nutrition'] ?? null,
                'ingredients' => $productData['ingredients'] ?? [],
                'allergens' => $productData['allergens'] ?? [],
                'health_score' => $this->calculateHealthScore($productData),
                'warnings' => $this->getProductWarnings($productData)
            ];

            if ($validated['include_alternatives'] ?? true) {
                $product['alternatives'] = $this->getProductAlternatives($productData);
            }

            if ($userId = $validated['user_id'] ?? Auth::id()) {
                $this->logBarcodeScann($userId, $product);
            }

            return response()->json([
                'success' => true,
                'product' => $product,
                'recommendations' => $this->getProductRecommendations($product)
            ]);

        } catch (\Exception $e) {
            Log::error('Barcode scan error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to scan barcode',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * FOOD DATABASE SEARCH - Search Passio's comprehensive food database
     */
    public function searchFoodDatabase(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'query' => 'required|string|min:2',
                'filters' => 'nullable|array',
                'filters.category' => 'nullable|string',
                'filters.brand' => 'nullable|string',
                'filters.min_protein' => 'nullable|numeric',
                'filters.max_calories' => 'nullable|numeric',
                'filters.dietary' => 'nullable|array', // vegan, gluten-free, etc.
                'sort_by' => 'nullable|string|in:relevance,calories,protein,healthScore',
                'limit' => 'nullable|integer|min:1|max:100',
                'offset' => 'nullable|integer|min:0'
            ]);

            $searchResults = $this->passioClient->request('POST', '/v2/food-search', [
                'query' => $validated['query'],
                'filters' => $validated['filters'] ?? [],
                'sort' => $validated['sort_by'] ?? 'relevance',
                'limit' => $validated['limit'] ?? 20,
                'offset' => $validated['offset'] ?? 0,
                'include_nutrition' => true,
                'include_recipes' => true
            ]);

            if (!$searchResults || empty($searchResults['items'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'No foods found matching your search',
                    'items' => [],
                    'total' => 0
                ]);
            }

            $enhancedResults = [];
            foreach ($searchResults['items'] as $item) {
                $enhancedItem = [
                    'passio_id' => $item['passioID'],
                    'name' => $item['name'],
                    'brand' => $item['brand'] ?? null,
                    'category' => $item['category'],
                    'image_url' => $item['imageUrl'] ?? null,
                    'nutrition' => $item['nutrition'],
                    'serving_sizes' => $item['servingSizes'] ?? [],
                    'health_score' => $this->calculateHealthScore($item),
                    'tags' => $item['tags'] ?? [],
                    'is_recipe' => $item['isRecipe'] ?? false
                ];

                $enhancedResults[] = $enhancedItem;
            }

            return response()->json([
                'success' => true,
                'items' => $enhancedResults,
                'total' => $searchResults['total'] ?? count($enhancedResults),
                'query' => $validated['query'],
                'filters_applied' => $validated['filters'] ?? []
            ]);

        } catch (\Exception $e) {
            Log::error('Food search error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to search food database',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * LOG FOOD INTAKE - Track food consumption
     */
    public function logFood(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'passio_id' => 'nullable|string',
                'food_name' => 'required|string',
                'quantity' => 'required|numeric|min:0',
                'unit' => 'required|string',
                'meal_type' => 'required|string|in:breakfast,lunch,dinner,snack',
                'consumed_at' => 'nullable|datetime',
                'nutrition' => 'nullable|array',
                'barcode' => 'nullable|string',
                'image_url' => 'nullable|string',
                'notes' => 'nullable|string|max:500'
            ]);

            $userId = Auth::id();
            $consumedAt = $validated['consumed_at'] ?? now();

            if (empty($validated['nutrition']) && !empty($validated['passio_id'])) {
                $nutritionData = $this->getDetailedNutrition($validated['passio_id']);
                $validated['nutrition'] = $nutritionData;
            }

            $foodLog = FoodLog::create([
                'user_id' => $userId,
                'passio_id' => $validated['passio_id'] ?? null,
                'food_name' => $validated['food_name'],
                'quantity' => $validated['quantity'],
                'unit' => $validated['unit'],
                'meal_type' => $validated['meal_type'],
                'nutrition' => $validated['nutrition'] ?? [],
                'barcode' => $validated['barcode'] ?? null,
                'image_url' => $validated['image_url'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'consumed_at' => $consumedAt,
                'logged_at' => now()
            ]);

            $this->updateDailyNutrition($userId, Carbon::parse($consumedAt)->toDateString());

            $goalStatus = $this->checkNutritionGoals($userId);

            $this->syncFoodLogWithPassio($foodLog);

            return response()->json([
                'success' => true,
                'message' => 'Food logged successfully',
                'food_log' => $foodLog,
                'daily_totals' => $this->getDailyTotals($userId, Carbon::parse($consumedAt)->toDateString()),
                'goal_status' => $goalStatus
            ]);

        } catch (\Exception $e) {
            Log::error('Food logging error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to log food',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * RECIPE ANALYZER - Analyze recipes for nutritional content
     */
    public function analyzeRecipe(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'recipe_text' => 'nullable|string|max:5000',
                'ingredients' => 'nullable|array',
                'ingredients.*.name' => 'required|string',
                'ingredients.*.quantity' => 'required|numeric',
                'ingredients.*.unit' => 'required|string',
                'servings' => 'required|integer|min:1',
                'recipe_name' => 'nullable|string',
                'cooking_method' => 'nullable|string'
            ]);

            if (!empty($validated['recipe_text'])) {
                $parsedRecipe = $this->passioClient->request('POST', '/v2/recipe/parse', [
                    'text' => $validated['recipe_text'],
                    'extract_ingredients' => true,
                    'extract_instructions' => true
                ]);

                $ingredients = $parsedRecipe['ingredients'] ?? [];
            } else {
                $ingredients = $validated['ingredients'] ?? [];
            }

            $totalNutrition = [
                'calories' => 0,
                'protein' => 0,
                'carbs' => 0,
                'fat' => 0,
                'fiber' => 0,
                'sugar' => 0,
                'sodium' => 0,
                'vitamins' => [],
                'minerals' => []
            ];

            $analyzedIngredients = [];
            foreach ($ingredients as $ingredient) {
                $nutritionData = $this->passioClient->request('POST', '/v2/ingredient/analyze', [
                    'name' => $ingredient['name'],
                    'quantity' => $ingredient['quantity'],
                    'unit' => $ingredient['unit']
                ]);

                if ($nutritionData) {
                    $analyzedIngredients[] = [
                        'name' => $ingredient['name'],
                        'quantity' => $ingredient['quantity'],
                        'unit' => $ingredient['unit'],
                        'nutrition' => $nutritionData['nutrition'],
                        'calories' => $nutritionData['nutrition']['calories'] ?? 0
                    ];

                    foreach ($nutritionData['nutrition'] as $key => $value) {
                        if (isset($totalNutrition[$key])) {
                            $totalNutrition[$key] += $value;
                        }
                    }
                }
            }

            $servings = $validated['servings'] ?? 1;
            $perServing = array_map(function($value) use ($servings) {
                return round($value / $servings, 2);
            }, $totalNutrition);

            $healthScore = $this->calculateRecipeHealthScore($totalNutrition, $ingredients);

            $suggestions = $this->getRecipeImprovementSuggestions($analyzedIngredients, $healthScore);

            return response()->json([
                'success' => true,
                'recipe_name' => $validated['recipe_name'] ?? 'Analyzed Recipe',
                'servings' => $servings,
                'ingredients' => $analyzedIngredients,
                'total_nutrition' => $totalNutrition,
                'per_serving' => $perServing,
                'health_score' => $healthScore,
                'suggestions' => $suggestions,
                'dietary_tags' => $this->getRecipeDietaryTags($analyzedIngredients)
            ]);

        } catch (\Exception $e) {
            Log::error('Recipe analysis error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to analyze recipe',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * AI MEAL SUGGESTIONS - Get personalized meal suggestions
     */
    public function getMealSuggestionsAI(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'meal_type' => 'required|string|in:breakfast,lunch,dinner,snack',
                'dietary_preferences' => 'nullable|array',
                'allergies' => 'nullable|array',
                'calorie_target' => 'nullable|integer|min:100|max:2000',
                'protein_target' => 'nullable|integer',
                'cuisine_preferences' => 'nullable|array',
                'ingredients_on_hand' => 'nullable|array',
                'time_available' => 'nullable|integer', // minutes
                'difficulty' => 'nullable|string|in:easy,medium,hard'
            ]);

            $userId = Auth::id();
            $userPrefs = $this->getUserPreferences($userId);

            $suggestions = $this->passioClient->request('POST', '/v2/meals/suggest', [
                'meal_type' => $validated['meal_type'],
                'user_preferences' => array_merge($userPrefs, [
                    'dietary' => $validated['dietary_preferences'] ?? $userPrefs['dietary'] ?? [],
                    'allergies' => $validated['allergies'] ?? $userPrefs['allergies'] ?? [],
                    'cuisine' => $validated['cuisine_preferences'] ?? []
                ]),
                'nutritional_targets' => [
                    'calories' => $validated['calorie_target'] ?? null,
                    'protein' => $validated['protein_target'] ?? null
                ],
                'constraints' => [
                    'ingredients_available' => $validated['ingredients_on_hand'] ?? [],
                    'time_minutes' => $validated['time_available'] ?? null,
                    'difficulty' => $validated['difficulty'] ?? 'medium'
                ],
                'number_of_suggestions' => 5
            ]);

            if (!$suggestions || empty($suggestions['meals'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No meal suggestions available'
                ], 404);
            }

            $enhancedSuggestions = [];
            foreach ($suggestions['meals'] as $meal) {
                $enhancedMeal = [
                    'id' => $meal['id'],
                    'name' => $meal['name'],
                    'description' => $meal['description'] ?? '',
                    'image_url' => $meal['imageUrl'] ?? null,
                    'prep_time' => $meal['prepTime'] ?? null,
                    'cook_time' => $meal['cookTime'] ?? null,
                    'difficulty' => $meal['difficulty'] ?? 'medium',
                    'servings' => $meal['servings'] ?? 1,
                    'nutrition' => $meal['nutrition'],
                    'ingredients' => $meal['ingredients'] ?? [],
                    'instructions' => $meal['instructions'] ?? [],
                    'tags' => $meal['tags'] ?? [],
                    'match_score' => $meal['matchScore'] ?? 0,
                    'why_suggested' => $meal['reasoning'] ?? ''
                ];

                $enhancedSuggestions[] = $enhancedMeal;
            }

            return response()->json([
                'success' => true,
                'meal_type' => $validated['meal_type'],
                'suggestions' => $enhancedSuggestions,
                'filters_applied' => array_filter($validated)
            ]);

        } catch (\Exception $e) {
            Log::error('Meal suggestions error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get meal suggestions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * WATER INTAKE TRACKING - Track water consumption
     */
    public function logWaterIntake(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'amount' => 'required|numeric|min:0',
                'unit' => 'required|string|in:ml,oz,cups,liters',
                'logged_at' => 'nullable|datetime'
            ]);

            $userId = Auth::id();
            $loggedAt = $validated['logged_at'] ?? now();

            $amountMl = $this->convertToMl($validated['amount'], $validated['unit']);

            $waterLog = DB::table('water_intake_logs')->insert([
                'user_id' => $userId,
                'amount_ml' => $amountMl,
                'unit' => $validated['unit'],
                'original_amount' => $validated['amount'],
                'logged_at' => $loggedAt,
                'created_at' => now()
            ]);

            $date = Carbon::parse($loggedAt)->toDateString();
            $dailyTotal = DB::table('water_intake_logs')
                ->where('user_id', $userId)
                ->whereDate('logged_at', $date)
                ->sum('amount_ml');

            $user = User::find($userId);
            $dailyGoal = $user->water_goal_ml ?? 2000; // Default 2L
            $percentageComplete = round(($dailyTotal / $dailyGoal) * 100, 2);

            $this->passioClient->request('POST', '/v2/water/log', [
                'user_id' => $userId,
                'amount_ml' => $amountMl,
                'timestamp' => $loggedAt
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Water intake logged successfully',
                'daily_total_ml' => $dailyTotal,
                'daily_goal_ml' => $dailyGoal,
                'percentage_complete' => $percentageComplete,
                'hydration_status' => $this->getHydrationStatus($dailyTotal, $dailyGoal)
            ]);

        } catch (\Exception $e) {
            Log::error('Water logging error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to log water intake',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * NUTRITION GOALS - Set and track nutrition goals
     */
    public function setNutritionGoals(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'daily_calories' => 'nullable|integer|min:1000|max:5000',
                'protein_g' => 'nullable|numeric|min:0',
                'carbs_g' => 'nullable|numeric|min:0',
                'fat_g' => 'nullable|numeric|min:0',
                'fiber_g' => 'nullable|numeric|min:0',
                'sugar_g' => 'nullable|numeric|min:0',
                'sodium_mg' => 'nullable|numeric|min:0',
                'water_ml' => 'nullable|integer|min:500|max:5000',
                'goal_type' => 'nullable|string|in:lose_weight,gain_muscle,maintain,health'
            ]);

            $userId = Auth::id();

            DB::table('nutrition_goals')->updateOrInsert(
                ['user_id' => $userId],
                array_merge($validated, [
                    'updated_at' => now()
                ])
            );

            $recommendations = $this->passioClient->request('POST', '/v2/goals/recommendations', [
                'user_id' => $userId,
                'goals' => $validated,
                'user_profile' => $this->getUserProfile($userId)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Nutrition goals updated successfully',
                'goals' => $validated,
                'recommendations' => $recommendations['recommendations'] ?? [],
                'meal_plan_preview' => $recommendations['sampleMealPlan'] ?? null
            ]);

        } catch (\Exception $e) {
            Log::error('Set goals error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to set nutrition goals',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * FOOD ALTERNATIVES - Get healthier alternatives for foods
     */
    public function getFoodAlternativesAPI(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'food_name' => 'nullable|string',
                'passio_id' => 'nullable|string',
                'criteria' => 'nullable|array', // lower_calorie, higher_protein, etc.
                'dietary_restrictions' => 'nullable|array'
            ]);

            if (empty($validated['food_name']) && empty($validated['passio_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Either food_name or passio_id is required'
                ], 400);
            }

            $alternatives = $this->passioClient->request('POST', '/v2/food/alternatives', [
                'food_name' => $validated['food_name'] ?? null,
                'passio_id' => $validated['passio_id'] ?? null,
                'criteria' => $validated['criteria'] ?? ['healthier'],
                'dietary_restrictions' => $validated['dietary_restrictions'] ?? [],
                'limit' => 5
            ]);

            if (!$alternatives || empty($alternatives['alternatives'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'No alternatives found',
                    'alternatives' => []
                ]);
            }

            $enhancedAlternatives = [];
            foreach ($alternatives['alternatives'] as $alt) {
                $enhancedAlt = [
                    'passio_id' => $alt['passioID'],
                    'name' => $alt['name'],
                    'brand' => $alt['brand'] ?? null,
                    'nutrition' => $alt['nutrition'],
                    'comparison' => $alt['comparison'] ?? [],
                    'benefits' => $alt['benefits'] ?? [],
                    'health_score' => $this->calculateHealthScore($alt),
                    'swap_rating' => $alt['swapRating'] ?? 0
                ];

                $enhancedAlternatives[] = $enhancedAlt;
            }

            return response()->json([
                'success' => true,
                'original_food' => $alternatives['originalFood'] ?? $validated['food_name'],
                'alternatives' => $enhancedAlternatives,
                'recommendation' => $alternatives['topRecommendation'] ?? null
            ]);

        } catch (\Exception $e) {
            Log::error('Get alternatives error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get food alternatives',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * NUTRITION TRENDS - Get nutrition trends and insights
     */
    public function getNutritionTrends(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'period' => 'required|string|in:week,month,quarter',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date'
            ]);

            $userId = Auth::id();

            if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
                $startDate = Carbon::parse($validated['start_date']);
                $endDate = Carbon::parse($validated['end_date']);
            } else {
                $endDate = Carbon::now();
                switch ($validated['period']) {
                    case 'week':
                        $startDate = $endDate->copy()->subWeek();
                        break;
                    case 'quarter':
                        $startDate = $endDate->copy()->subQuarter();
                        break;
                    default: // month
                        $startDate = $endDate->copy()->subMonth();
                }
            }

            $nutritionLogs = NutritionLog::where('user_id', $userId)
                ->whereBetween('date', [$startDate, $endDate])
                ->get();

            $trends = [
                'calories' => $this->calculateTrend($nutritionLogs, 'total_calories'),
                'protein' => $this->calculateTrend($nutritionLogs, 'macros.protein'),
                'carbs' => $this->calculateTrend($nutritionLogs, 'macros.carbs'),
                'fat' => $this->calculateTrend($nutritionLogs, 'macros.fat'),
                'water' => $this->calculateWaterTrend($userId, $startDate, $endDate)
            ];

            $insights = $this->passioClient->request('POST', '/v2/insights/nutrition', [
                'user_id' => $userId,
                'period' => [
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString()
                ],
                'trends' => $trends
            ]);

            return response()->json([
                'success' => true,
                'period' => [
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString()
                ],
                'trends' => $trends,
                'insights' => $insights['insights'] ?? [],
                'recommendations' => $insights['recommendations'] ?? [],
                'achievements' => $this->getNutritionAchievements($userId, $startDate, $endDate)
            ]);

        } catch (\Exception $e) {
            Log::error('Get trends error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get nutrition trends',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Private helper methods
     */
    private function getUserPreferences($userId)
    {
        if (!$userId) return [];

        $user = User::find($userId);
        if (!$user) return [];

        return [
            'dietary' => json_decode($user->dietary_preferences ?? '[]', true),
            'allergies' => json_decode($user->allergies ?? '[]', true),
            'dislikes' => json_decode($user->food_dislikes ?? '[]', true),
            'health_conditions' => json_decode($user->health_conditions ?? '[]', true)
        ];
    }

    private function getDetailedNutrition($passioId)
    {
        try {
            $nutrition = $this->passioClient->request('GET', "/v2/food/{$passioId}/nutrition");
            return $nutrition['nutrition'] ?? null;
        } catch (\Exception $e) {
            Log::warning('Failed to get detailed nutrition', ['passio_id' => $passioId]);
            return null;
        }
    }

    private function getFoodAlternatives($passioId)
    {
        try {
            $alternatives = $this->passioClient->request('GET', "/v2/food/{$passioId}/alternatives");
            return $alternatives['alternatives'] ?? [];
        } catch (\Exception $e) {
            Log::warning('Failed to get food alternatives', ['passio_id' => $passioId]);
            return [];
        }
    }

    private function calculateTotalNutrition($foods)
    {
        $total = [
            'calories' => 0,
            'protein' => 0,
            'carbs' => 0,
            'fat' => 0,
            'fiber' => 0,
            'sugar' => 0,
            'sodium' => 0
        ];

        foreach ($foods as $food) {
            if (isset($food['nutrition'])) {
                foreach ($total as $key => &$value) {
                    $value += $food['nutrition'][$key] ?? 0;
                }
            }
        }

        return $total;
    }

    private function getMealSuggestions($recognizedFoods)
    {
        try {
            $suggestions = $this->passioClient->request('POST', '/v2/meals/complement', [
                'existing_foods' => array_map(function($food) {
                    return $food['passio_id'];
                }, $recognizedFoods)
            ]);

            return $suggestions['suggestions'] ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function calculateHealthScore($foodData)
    {
        $score = 100;

        if (isset($foodData['nutrition'])) {
            $nutrition = $foodData['nutrition'];

            if (($nutrition['sugar'] ?? 0) > 10) {
                $score -= min(20, ($nutrition['sugar'] - 10) * 2);
            }

            if (($nutrition['sodium'] ?? 0) > 500) {
                $score -= min(15, ($nutrition['sodium'] - 500) / 50);
            }

            $score += min(10, ($nutrition['protein'] ?? 0) / 2);
            $score += min(10, ($nutrition['fiber'] ?? 0) * 2);
        }

        return max(0, min(100, round($score)));
    }

    private function getProductWarnings($productData)
    {
        $warnings = [];

        if (isset($productData['nutrition'])) {
            $nutrition = $productData['nutrition'];

            if (($nutrition['sugar'] ?? 0) > 20) {
                $warnings[] = 'High sugar content';
            }

            if (($nutrition['sodium'] ?? 0) > 800) {
                $warnings[] = 'High sodium content';
            }

            if (($nutrition['saturatedFat'] ?? 0) > 10) {
                $warnings[] = 'High saturated fat';
            }
        }

        if (!empty($productData['allergens'])) {
            $warnings[] = 'Contains allergens: ' . implode(', ', $productData['allergens']);
        }

        return $warnings;
    }

    private function getProductAlternatives($productData)
    {
        try {
            $alternatives = $this->passioClient->request('POST', '/v2/product/alternatives', [
                'product' => $productData,
                'criteria' => ['healthier', 'lower_calorie', 'organic']
            ]);

            return $alternatives['alternatives'] ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getProductRecommendations($product)
    {
        $recommendations = [];

        if ($product['health_score'] < 50) {
            $recommendations[] = 'Consider healthier alternatives';
        }

        if (!empty($product['warnings'])) {
            $recommendations[] = 'Check nutritional warnings';
        }

        return $recommendations;
    }

    private function logFoodRecognition($userId, $results)
    {
        try {
            DB::table('food_recognition_logs')->insert([
                'user_id' => $userId,
                'foods_recognized' => json_encode($results),
                'recognition_count' => count($results),
                'created_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log food recognition', ['error' => $e->getMessage()]);
        }
    }

    private function logBarcodeScann($userId, $product)
    {
        try {
            DB::table('barcode_scan_logs')->insert([
                'user_id' => $userId,
                'barcode' => $product['barcode'],
                'product_name' => $product['name'],
                'product_data' => json_encode($product),
                'scanned_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log barcode scan', ['error' => $e->getMessage()]);
        }
    }

    private function fallbackBarcodeSearch($barcode)
    {
        return null;
    }

    private function updateDailyNutrition($userId, $date)
    {
        $foodLogs = FoodLog::where('user_id', $userId)
            ->whereDate('consumed_at', $date)
            ->get();

        $totals = $this->calculateTotalNutrition($foodLogs->toArray());

        NutritionLog::updateOrCreate(
            [
                'user_id' => $userId,
                'date' => $date
            ],
            [
                'total_calories' => $totals['calories'],
                'macros' => [
                    'protein' => $totals['protein'],
                    'carbs' => $totals['carbs'],
                    'fat' => $totals['fat']
                ],
                'updated_at' => now()
            ]
        );
    }

    private function checkNutritionGoals($userId)
    {
        $goals = DB::table('nutrition_goals')->where('user_id', $userId)->first();
        if (!$goals) return null;

        $today = Carbon::today()->toDateString();
        $todayNutrition = NutritionLog::where('user_id', $userId)
            ->where('date', $today)
            ->first();

        if (!$todayNutrition) return null;

        return [
            'calories' => [
                'target' => $goals->daily_calories ?? 0,
                'current' => $todayNutrition->total_calories ?? 0,
                'percentage' => $goals->daily_calories > 0 ?
                    round(($todayNutrition->total_calories / $goals->daily_calories) * 100, 2) : 0
            ],
            'protein' => [
                'target' => $goals->protein_g ?? 0,
                'current' => $todayNutrition->macros['protein'] ?? 0,
                'percentage' => $goals->protein_g > 0 ?
                    round(($todayNutrition->macros['protein'] / $goals->protein_g) * 100, 2) : 0
            ]
        ];
    }

    private function getDailyTotals($userId, $date)
    {
        $nutritionLog = NutritionLog::where('user_id', $userId)
            ->where('date', $date)
            ->first();

        return $nutritionLog ? [
            'calories' => $nutritionLog->total_calories,
            'macros' => $nutritionLog->macros,
            'meals_logged' => $nutritionLog->meals ? count($nutritionLog->meals) : 0
        ] : [
            'calories' => 0,
            'macros' => ['protein' => 0, 'carbs' => 0, 'fat' => 0],
            'meals_logged' => 0
        ];
    }

    private function syncFoodLogWithPassio($foodLog)
    {
        try {
            $this->passioClient->request('POST', '/v2/food-log/sync', [
                'user_id' => $foodLog->user_id,
                'food_log' => $foodLog->toArray()
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to sync food log with Passio', ['error' => $e->getMessage()]);
        }
    }

    private function calculateRecipeHealthScore($nutrition, $ingredients)
    {
        $score = 70;

        $caloriesPerServing = $nutrition['calories'] ?? 0;
        if ($caloriesPerServing > 800) $score -= 10;
        if ($caloriesPerServing < 200) $score -= 5;

        $wholeIngredients = 0;
        foreach ($ingredients as $ing) {
            if (strpos(strtolower($ing['name']), 'whole') !== false ||
                strpos(strtolower($ing['name']), 'fresh') !== false) {
                $wholeIngredients++;
            }
        }

        $score += min(15, $wholeIngredients * 3);

        if (($nutrition['protein'] ?? 0) > 20) $score += 10;

        if (($nutrition['fiber'] ?? 0) > 5) $score += 10;

        return max(0, min(100, round($score)));
    }

    private function getRecipeImprovementSuggestions($ingredients, $healthScore)
    {
        $suggestions = [];

        if ($healthScore < 70) {
            $suggestions[] = 'Consider adding more vegetables for increased nutrients';
        }

        foreach ($ingredients as $ing) {
            if (($ing['calories'] ?? 0) > 200) {
                $suggestions[] = "Consider reducing portion of {$ing['name']} or finding a lower-calorie alternative";
                break;
            }
        }

        return $suggestions;
    }

    private function getRecipeDietaryTags($ingredients)
    {
        $tags = [];
        $hasMeat = false;
        $hasDairy = false;
        $hasGluten = false;

        foreach ($ingredients as $ing) {
            $name = strtolower($ing['name']);
            if (strpos($name, 'meat') !== false || strpos($name, 'chicken') !== false ||
                strpos($name, 'beef') !== false || strpos($name, 'pork') !== false) {
                $hasMeat = true;
            }
            if (strpos($name, 'milk') !== false || strpos($name, 'cheese') !== false ||
                strpos($name, 'butter') !== false || strpos($name, 'cream') !== false) {
                $hasDairy = true;
            }
            if (strpos($name, 'wheat') !== false || strpos($name, 'flour') !== false ||
                strpos($name, 'bread') !== false) {
                $hasGluten = true;
            }
        }

        if (!$hasMeat) $tags[] = 'vegetarian';
        if (!$hasMeat && !$hasDairy) $tags[] = 'vegan';
        if (!$hasGluten) $tags[] = 'gluten-free';

        return $tags;
    }

    private function convertToMl($amount, $unit)
    {
        switch ($unit) {
            case 'ml':
                return $amount;
            case 'oz':
                return $amount * 29.5735;
            case 'cups':
                return $amount * 236.588;
            case 'liters':
                return $amount * 1000;
            default:
                return $amount;
        }
    }

    private function getHydrationStatus($dailyTotal, $dailyGoal)
    {
        $percentage = ($dailyTotal / $dailyGoal) * 100;

        if ($percentage >= 100) {
            return 'Excellent - Goal achieved!';
        } elseif ($percentage >= 75) {
            return 'Good - Almost there!';
        } elseif ($percentage >= 50) {
            return 'Fair - Keep drinking!';
        } else {
            return 'Poor - Increase water intake';
        }
    }

    private function getUserProfile($userId)
    {
        $user = User::find($userId);
        if (!$user) return [];

        return [
            'age' => $user->age,
            'gender' => $user->gender,
            'weight' => $user->weight,
            'height' => $user->height,
            'activity_level' => $user->activity_level ?? 'moderate',
            'fitness_goals' => $user->fitness_goals
        ];
    }

    private function calculateTrend($logs, $field)
    {
        if ($logs->isEmpty()) return ['trend' => 'stable', 'change' => 0];

        $values = $logs->pluck($field)->filter()->values();
        if ($values->count() < 2) return ['trend' => 'insufficient_data', 'change' => 0];

        $firstHalf = $values->take($values->count() / 2)->avg();
        $secondHalf = $values->skip($values->count() / 2)->avg();

        $change = $secondHalf - $firstHalf;
        $percentChange = $firstHalf > 0 ? ($change / $firstHalf) * 100 : 0;

        return [
            'trend' => $change > 0 ? 'increasing' : ($change < 0 ? 'decreasing' : 'stable'),
            'change' => round($change, 2),
            'percent_change' => round($percentChange, 2)
        ];
    }

    private function calculateWaterTrend($userId, $startDate, $endDate)
    {
        $waterLogs = DB::table('water_intake_logs')
            ->where('user_id', $userId)
            ->whereBetween('logged_at', [$startDate, $endDate])
            ->selectRaw('DATE(logged_at) as date, SUM(amount_ml) as daily_total')
            ->groupBy('date')
            ->get();

        if ($waterLogs->isEmpty()) return ['trend' => 'no_data', 'average' => 0];

        return [
            'trend' => 'tracked',
            'average' => round($waterLogs->avg('daily_total'), 2),
            'total_days' => $waterLogs->count()
        ];
    }

    private function getNutritionAchievements($userId, $startDate, $endDate)
    {
        $achievements = [];

        $daysLogged = NutritionLog::where('user_id', $userId)
            ->whereBetween('date', [$startDate, $endDate])
            ->count();

        $totalDays = $startDate->diffInDays($endDate);

        if ($daysLogged == $totalDays) {
            $achievements[] = 'Perfect logging streak!';
        } elseif ($daysLogged / $totalDays >= 0.8) {
            $achievements[] = 'Consistent tracker';
        }

        $goals = DB::table('nutrition_goals')->where('user_id', $userId)->first();
        if ($goals) {
            $goalsMetCount = NutritionLog::where('user_id', $userId)
                ->whereBetween('date', [$startDate, $endDate])
                ->where('total_calories', '<=', $goals->daily_calories * 1.1)
                ->where('total_calories', '>=', $goals->daily_calories * 0.9)
                ->count();

            if ($goalsMetCount / $daysLogged >= 0.7) {
                $achievements[] = 'Calorie goal champion';
            }
        }

        return $achievements;
    }
}