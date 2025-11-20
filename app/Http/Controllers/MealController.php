<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Services\PassioTokenService;

class MealController extends Controller
{
    private readonly PassioTokenService $passioTokenService;

    public function __construct(PassioTokenService $passioTokenService)
    {
        $this->passioTokenService = $passioTokenService;
    }

    /**
     * Get food by product code
     */
    public function getFoodByProductCode(string $productCode): JsonResponse
    {
        try {
            $data = $this->passioTokenService->makeAuthenticatedRequest(
                'GET',
                "/v2/products/napi/food/productCode/{$productCode}"
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching food by product code', [
                'product_code' => $productCode,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch food data'
            ], 500);
        }
    }

    /**
     * Search foods with advanced parameters
     */
    public function searchFoodsAdvanced(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'term' => 'nullable|string|max:255',
            'image' => 'nullable|file|image|max:10240', // 10MB max
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $searchData = [];
            
            if ($request->has('term')) {
                $searchData['term'] = $request->input('term');
            }

            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $searchData['image'] = base64_encode(file_get_contents($image->path()));
                $searchData['image_type'] = $image->getClientMimeType();
            }

            $searchData['limit'] = $request->input('limit', 20);
            $searchData['offset'] = $request->input('offset', 0);

            $data = $this->passioTokenService->makeAuthenticatedRequest(
                'POST',
                '/v2/products/napi/food/search/advanced',
                $searchData
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error in advanced food search', [
                'error' => $e->getMessage(),
                'request_data' => $request->except(['image'])
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to search foods'
            ], 500);
        }
    }

    /**
     * Get nutrition advice
     */
    public function getNutritionAdvice(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_profile' => 'required|array',
            'user_profile.age' => 'required|integer|min:1|max:120',
            'user_profile.gender' => 'required|string|in:male,female,other',
            'user_profile.weight' => 'required|numeric|min:20|max:500',
            'user_profile.height' => 'required|numeric|min:50|max:300',
            'user_profile.activity_level' => 'required|string|in:sedentary,light,moderate,active,very_active',
            'goals' => 'nullable|array',
            'dietary_restrictions' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $adviceData = [
                'user_profile' => $request->input('user_profile'),
                'goals' => $request->input('goals', []),
                'dietary_restrictions' => $request->input('dietary_restrictions', [])
            ];

            $data = $this->passioTokenService->makeAuthenticatedRequest(
                'POST',
                '/v2/nutrition-advisor/advice',
                $adviceData
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting nutrition advice', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get nutrition advice'
            ], 500);
        }
    }

    /**
     * Get meal plan recommendations
     */
    public function getMealPlanRecommendations(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|string|exists:users,id',
            'days' => 'nullable|integer|min:1|max:30',
            'calories_per_day' => 'nullable|integer|min:1000|max:5000',
            'meal_types' => 'nullable|array',
            'dietary_preferences' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $planData = [
                'user_id' => $request->input('user_id'),
                'days' => $request->input('days', 7),
                'calories_per_day' => $request->input('calories_per_day'),
                'meal_types' => $request->input('meal_types', ['breakfast', 'lunch', 'dinner', 'snack']),
                'dietary_preferences' => $request->input('dietary_preferences', [])
            ];

            $data = $this->passioTokenService->makeAuthenticatedRequest(
                'POST',
                '/v2/meal-planner/recommendations',
                $planData
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting meal plan recommendations', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get meal plan recommendations'
            ], 500);
        }
    }

    /**
     * Log food intake
     */
    public function logFoodIntake(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|string|exists:users,id',
            'food_items' => 'required|array',
            'food_items.*.product_code' => 'required|string',
            'food_items.*.quantity' => 'required|numeric|min:0.1',
            'food_items.*.unit' => 'required|string',
            'meal_type' => 'required|string|in:breakfast,lunch,dinner,snack',
            'consumed_at' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $logData = [
                'user_id' => $request->input('user_id'),
                'food_items' => $request->input('food_items'),
                'meal_type' => $request->input('meal_type'),
                'consumed_at' => $request->input('consumed_at', now()->toISOString())
            ];

            $data = $this->passioTokenService->makeAuthenticatedRequest(
                'POST',
                '/v2/nutrition-log/intake',
                $logData
            );

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Food intake logged successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error logging food intake', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to log food intake'
            ], 500);
        }
    }

    /**
     * Get nutrition summary for a specific date
     */
    public function getNutritionSummary(Request $request, string $userId, string $date): JsonResponse
    {
        try {
            $data = $this->passioTokenService->makeAuthenticatedRequest(
                'GET',
                "/v2/nutrition-log/summary/{$userId}/{$date}"
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting nutrition summary', [
                'user_id' => $userId,
                'date' => $date,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get nutrition summary'
            ], 500);
        }
    }
}
