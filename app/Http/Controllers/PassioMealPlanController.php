<?php

namespace App\Http\Controllers;

use App\Services\PassioClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PassioMealPlanController extends Controller
{
    private PassioClient $passioClient;

    public function __construct(PassioClient $passioClient)
    {
        $this->passioClient = $passioClient;
    }

    /**
     * Generate AI-powered meal plan
     *
     * POST /api/passio/meal-plan/generate
     */
    public function generateAIMealPlan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userProfile' => 'required|array',
            'userProfile.age' => 'required|integer|min:10|max:120',
            'userProfile.gender' => 'required|string|in:male,female',
            'userProfile.weight' => 'required|numeric|min:20',
            'userProfile.height' => 'required|numeric|min:50',
            'userProfile.activity' => 'required|string|in:sedentary,light,moderate,active,very_active',
            'nutritionGoals' => 'required|array',
            'nutritionGoals.calories' => 'required|integer|min:800|max:5000',
            'nutritionGoals.protein' => 'required|integer|min:0',
            'nutritionGoals.carbs' => 'required|integer|min:0',
            'nutritionGoals.fat' => 'required|integer|min:0',
            'dietaryPreferences' => 'sometimes|array',
            'restrictions' => 'sometimes|array',
            'days' => 'sometimes|integer|min:1|max:90'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->passioClient->generateAIMealPlan(
                $request->input('userProfile'),
                $request->input('nutritionGoals'),
                $request->input('dietaryPreferences', []),
                $request->input('restrictions', []),
                $request->input('days', 7)
            );

            if (!$result) {
                return response()->json([
                    'status' => 500,
                    'success' => false,
                    'message' => 'Failed to generate AI meal plan'
                ], 500);
            }

            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'AI Meal Plan Generated Successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Error generating AI meal plan', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'An error occurred while generating the meal plan'
            ], 500);
        }
    }

    /**
     * Get food substitutions for a given food item
     *
     * POST /api/passio/food/substitutions
     */
    public function getFoodSubstitutions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'foodId' => 'required|string',
            'criteria' => 'sometimes|array',
            'limit' => 'sometimes|integer|min:1|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->passioClient->getFoodSubstitutions(
                $request->input('foodId'),
                $request->input('criteria', ['similar_nutrition' => true]),
                $request->input('limit', 10)
            );

            if (!$result) {
                return response()->json([
                    'status' => 404,
                    'success' => false,
                    'message' => 'No substitutions found'
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'Food Substitutions Retrieved Successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting food substitutions', [
                'error' => $e->getMessage(),
                'foodId' => $request->input('foodId')
            ]);

            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'An error occurred while fetching substitutions'
            ], 500);
        }
    }

    /**
     * Find foods by nutrition profile
     *
     * POST /api/passio/food/find-by-nutrition
     */
    public function findFoodByNutrition(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nutrition' => 'required|array',
            'nutrition.calories' => 'sometimes|numeric|min:0',
            'nutrition.protein' => 'sometimes|numeric|min:0',
            'nutrition.carbs' => 'sometimes|numeric|min:0',
            'nutrition.fat' => 'sometimes|numeric|min:0',
            'restrictions' => 'sometimes|array',
            'limit' => 'sometimes|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->passioClient->findFoodByNutrition(
                $request->input('nutrition'),
                $request->input('restrictions', []),
                $request->input('limit', 20)
            );

            if (!$result) {
                return response()->json([
                    'status' => 404,
                    'success' => false,
                    'message' => 'No matching foods found'
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'Foods Retrieved Successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Error finding food by nutrition', [
                'error' => $e->getMessage(),
                'nutrition' => $request->input('nutrition')
            ]);

            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'An error occurred while searching for foods'
            ], 500);
        }
    }

    /**
     * Enhanced food search with filters
     *
     * GET /api/passio/food/search
     */
    public function searchFood(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2',
            'filters' => 'sometimes|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->passioClient->searchFood(
                $request->input('query'),
                $request->input('filters', [])
            );

            if (!$result) {
                return response()->json([
                    'status' => 404,
                    'success' => false,
                    'message' => 'No foods found'
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'Food Search Results Retrieved Successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Error searching food', [
                'error' => $e->getMessage(),
                'query' => $request->input('query')
            ]);

            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'An error occurred while searching'
            ], 500);
        }
    }

    /**
     * Enhanced barcode scanning with alternatives
     *
     * GET /api/passio/barcode/scan/{barcode}
     */
    public function scanBarcode(Request $request, string $barcode)
    {
        $validator = Validator::make(['barcode' => $barcode], [
            'barcode' => 'required|string|min:8|max:20'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'success' => false,
                'message' => 'Invalid barcode format',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $includeAlternatives = $request->query('includeAlternatives', false);

            $result = $this->passioClient->scanBarcode(
                $barcode,
                filter_var($includeAlternatives, FILTER_VALIDATE_BOOLEAN)
            );

            if (!$result) {
                return response()->json([
                    'status' => 404,
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'Barcode Scanned Successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Error scanning barcode', [
                'error' => $e->getMessage(),
                'barcode' => $barcode
            ]);

            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'An error occurred while scanning the barcode'
            ], 500);
        }
    }

    /**
     * Get detailed nutrition data with serving size multiplier
     *
     * GET /api/passio/nutrition/{foodId}
     */
    public function getNutritionData(Request $request, string $foodId)
    {
        $validator = Validator::make(
            ['foodId' => $foodId, 'servingSize' => $request->query('servingSize', 1.0)],
            [
                'foodId' => 'required|string',
                'servingSize' => 'sometimes|numeric|min:0.1|max:100'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $servingSize = (float) $request->query('servingSize', 1.0);

            $result = $this->passioClient->getNutritionData($foodId, $servingSize);

            if (!$result) {
                return response()->json([
                    'status' => 404,
                    'success' => false,
                    'message' => 'Nutrition data not found'
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'Nutrition Data Retrieved Successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting nutrition data', [
                'error' => $e->getMessage(),
                'foodId' => $foodId
            ]);

            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'An error occurred while fetching nutrition data'
            ], 500);
        }
    }

    /**
     * Get personalized food recommendations
     *
     * POST /api/passio/recommendations
     */
    public function getFoodRecommendations(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'preferences' => 'required|array',
            'recentMeals' => 'sometimes|array',
            'mealType' => 'sometimes|string|in:breakfast,lunch,dinner,snack,any'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->passioClient->getFoodRecommendations(
                $request->input('preferences'),
                $request->input('recentMeals', []),
                $request->input('mealType', 'any')
            );

            if (!$result) {
                return response()->json([
                    'status' => 404,
                    'success' => false,
                    'message' => 'No recommendations found'
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'Food Recommendations Retrieved Successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting food recommendations', [
                'error' => $e->getMessage(),
                'preferences' => $request->input('preferences')
            ]);

            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'An error occurred while fetching recommendations'
            ], 500);
        }
    }

    /**
     * Analyze meal and provide nutrition breakdown
     *
     * POST /api/passio/meal/analyze
     */
    public function analyzeMeal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'foods' => 'required|array|min:1',
            'foods.*.foodId' => 'required|string',
            'foods.*.quantity' => 'required|numeric|min:0',
            'foods.*.unit' => 'sometimes|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->passioClient->analyzeMeal($request->input('foods'));

            if (!$result) {
                return response()->json([
                    'status' => 500,
                    'success' => false,
                    'message' => 'Failed to analyze meal'
                ], 500);
            }

            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'Meal Analyzed Successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Error analyzing meal', [
                'error' => $e->getMessage(),
                'foods_count' => count($request->input('foods', []))
            ]);

            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'An error occurred while analyzing the meal'
            ], 500);
        }
    }

    /**
     * Get popular foods by category
     *
     * GET /api/passio/foods/popular
     */
    public function getPopularFoods(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category' => 'sometimes|string',
            'limit' => 'sometimes|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->passioClient->getPopularFoods(
                $request->query('category', 'all'),
                (int) $request->query('limit', 50)
            );

            if (!$result) {
                return response()->json([
                    'status' => 404,
                    'success' => false,
                    'message' => 'No popular foods found'
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'Popular Foods Retrieved Successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting popular foods', [
                'error' => $e->getMessage(),
                'category' => $request->query('category', 'all')
            ]);

            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'An error occurred while fetching popular foods'
            ], 500);
        }
    }

    /**
     * Validate and correct food portions
     *
     * POST /api/passio/portion/validate
     */
    public function validatePortion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'foodId' => 'required|string',
            'quantity' => 'required|numeric|min:0',
            'unit' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->passioClient->validatePortion(
                $request->input('foodId'),
                (float) $request->input('quantity'),
                $request->input('unit')
            );

            if (!$result) {
                return response()->json([
                    'status' => 500,
                    'success' => false,
                    'message' => 'Failed to validate portion'
                ], 500);
            }

            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'Portion Validated Successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Error validating portion', [
                'error' => $e->getMessage(),
                'foodId' => $request->input('foodId')
            ]);

            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'An error occurred while validating the portion'
            ], 500);
        }
    }

    /**
     * Test Passio API connectivity
     *
     * GET /api/passio/ping
     */
    public function ping()
    {
        try {
            $result = $this->passioClient->ping();

            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'Passio API Connection Test',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Error pinging Passio API', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Failed to connect to Passio API',
                'data' => [
                    'status' => 'error',
                    'message' => $e->getMessage()
                ]
            ], 500);
        }
    }
}
