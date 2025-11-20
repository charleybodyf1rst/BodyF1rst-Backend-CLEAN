<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Services\PassioClient;

/**
 * Passio API Proxy Controller
 *
 * This controller provides secure proxy endpoints for the frontend to access
 * Passio Nutrition AI API without exposing the API key.
 *
 * Frontend endpoints:
 * - POST /passio/recognize-food
 * - POST /passio/search-food
 * - POST /passio/generate-meal-plan
 * - POST /api/passio-nutrition-info
 */
class PassioProxyController extends Controller
{
    private PassioClient $passioClient;

    public function __construct(PassioClient $passioClient)
    {
        $this->passioClient = $passioClient;
    }

    /**
     * PROXY ENDPOINT 1: Recognize food from image
     * Frontend calls: POST /passio/recognize-food
     *
     * Receives base64 image from frontend and forwards to Passio Vision AI
     */
    public function recognizeFood(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|string',
            'imageFormat' => 'nullable|string|in:base64,url'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $imageData = $request->input('image');
            $imageFormat = $request->input('imageFormat', 'base64');

            Log::info('Passio food recognition request', [
                'imageFormat' => $imageFormat,
                'imageSize' => strlen($imageData)
            ]);

            $result = $this->passioClient->recognizeFood($imageData, $imageFormat);

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to recognize food from image'
                ], 500);
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Error recognizing food', [
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
     * PROXY ENDPOINT 2: Search foods in Passio database
     * Frontend calls: POST /passio/search-food
     *
     * Searches Passio database by food name
     */
    public function searchFood(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2|max:255',
            'limit' => 'nullable|integer|min:1|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = $request->input('query');
            $limit = $request->input('limit', 20);

            Log::info('Passio food search', ['query' => $query, 'limit' => $limit]);

            $searchResults = $this->passioClient->searchFood($query, ['limit' => $limit]);

            if (!$searchResults) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to search foods'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'results' => $searchResults
            ]);

        } catch (\Exception $e) {
            Log::error('Error searching foods', [
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
     * PROXY ENDPOINT 3: Generate AI meal plan
     * Frontend calls: POST /passio/generate-meal-plan
     *
     * Generates complete daily meal plan based on nutrition targets
     */
    public function generateMealPlan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'calories' => 'required|integer|min:1200|max:5000',
            'protein' => 'required|integer|min:0',
            'carbs' => 'required|integer|min:0',
            'fat' => 'required|integer|min:0',
            'numMeals' => 'nullable|integer|min:1|max:6',
            'dietaryPreferences' => 'nullable|array',
            'dietaryPreferences.*' => 'string',
            'excludeIngredients' => 'nullable|array',
            'excludeIngredients.*' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userProfile = [];
            $goals = [
                'calories' => $request->input('calories'),
                'protein' => $request->input('protein'),
                'carbs' => $request->input('carbs'),
                'fat' => $request->input('fat')
            ];
            $preferences = [
                'meals_per_day' => $request->input('numMeals', 4),
                'dietary' => $request->input('dietaryPreferences', [])
            ];
            $restrictions = [
                'exclude' => $request->input('excludeIngredients', [])
            ];

            Log::info('Passio meal plan generation', [
                'calories' => $goals['calories'],
                'protein' => $goals['protein'],
                'numMeals' => $preferences['meals_per_day']
            ]);

            $result = $this->passioClient->generateAIMealPlan(
                $userProfile,
                $goals,
                $preferences,
                $restrictions,
                1  // Generate 1 day plan
            );

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate meal plan'
                ], 500);
            }

            // Format response to match frontend expectations
            return response()->json([
                'success' => true,
                'meals' => $result['meals'] ?? [],
                'totals' => [
                    'calories' => $result['totalCalories'] ?? 0,
                    'protein' => $result['totalProtein'] ?? 0,
                    'carbs' => $result['totalCarbs'] ?? 0,
                    'fat' => $result['totalFat'] ?? 0
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating meal plan', [
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
     * PROXY ENDPOINT 4: Get nutrition info by refCode
     * Frontend calls: POST /api/passio-nutrition-info
     *
     * Gets detailed nutrition information for a specific food by its Passio refCode
     */
    public function getNutritionInfo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'refCode' => 'required|string|max:255',
            'servingSize' => 'nullable|numeric|min:0.1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $refCode = $request->input('refCode');
            $servingSize = $request->input('servingSize', 1.0);

            Log::info('Passio nutrition info request', [
                'refCode' => $refCode,
                'servingSize' => $servingSize
            ]);

            $nutritionData = $this->passioClient->getNutritionData($refCode, (float)$servingSize);

            if (!$nutritionData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Food not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $nutritionData
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching nutrition info', [
                'error' => $e->getMessage(),
                'refCode' => $request->input('refCode'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }
}
