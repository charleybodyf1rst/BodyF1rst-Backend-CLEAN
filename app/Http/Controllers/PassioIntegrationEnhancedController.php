<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Enhanced Passio Integration Controller
 * Provides proxy endpoints for Passio AI features:
 * - Food recognition (camera)
 * - Barcode scanning
 * - Recipe analysis
 */
class PassioIntegrationEnhancedController extends Controller
{
    private $passioApiKey;
    private $passioBaseUrl;

    public function __construct()
    {
        $this->middleware('auth:api');
        $this->passioApiKey = config('services.passio.api_key');
        $this->passioBaseUrl = config('services.passio.base_url', 'https://api.passiolife.com');
    }

    /**
     * Food Recognition from Image
     * POST /api/nutrition/passio/recognize
     */
    public function recognizeFoodFromImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|string', // Base64 encoded image
            'imageFormat' => 'nullable|string|in:base64,url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $imageData = $request->image;
            $imageFormat = $request->imageFormat ?? 'base64';

            // Call Passio Vision API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->passioApiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->passioBaseUrl}/v2/recognize", [
                'image' => $imageData,
                'imageFormat' => $imageFormat,
            ]);

            if (!$response->successful()) {
                Log::error('Passio API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to recognize food from image',
                ], $response->status());
            }

            $data = $response->json();

            // Format response for frontend
            $formatted = $this->formatRecognitionResponse($data);

            // Log successful recognition
            Log::info('Food recognized', [
                'user_id' => Auth::id(),
                'confidence' => $formatted['confidence'] ?? 0,
                'food_name' => $formatted['foodName'] ?? 'unknown',
            ]);

            return response()->json([
                'success' => true,
                'data' => $formatted,
            ]);

        } catch (\Exception $e) {
            Log::error('Error recognizing food', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process image',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Barcode Lookup
     * POST /api/nutrition/passio/barcode
     */
    public function lookupBarcode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'barcode' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $barcode = $request->barcode;

            // Check cache first (barcode lookups are static)
            $cacheKey = "passio_barcode_{$barcode}";
            $cached = Cache::get($cacheKey);

            if ($cached) {
                return response()->json([
                    'success' => true,
                    'data' => $cached,
                    'cached' => true,
                ]);
            }

            // Call Passio Barcode API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->passioApiKey,
            ])->get("{$this->passioBaseUrl}/v2/barcode/{$barcode}");

            if (!$response->successful()) {
                if ($response->status() === 404) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Product not found',
                    ], 404);
                }

                Log::error('Passio barcode API error', [
                    'status' => $response->status(),
                    'barcode' => $barcode,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to lookup barcode',
                ], $response->status());
            }

            $data = $response->json();
            $formatted = $this->formatBarcodeResponse($data);

            // Cache for 7 days (product info doesn't change often)
            Cache::put($cacheKey, $formatted, now()->addDays(7));

            Log::info('Barcode scanned', [
                'user_id' => Auth::id(),
                'barcode' => $barcode,
                'product' => $formatted['productName'] ?? 'unknown',
            ]);

            return response()->json([
                'success' => true,
                'data' => $formatted,
                'cached' => false,
            ]);

        } catch (\Exception $e) {
            Log::error('Error looking up barcode', [
                'error' => $e->getMessage(),
                'barcode' => $request->barcode,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to lookup barcode',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Recipe Analysis
     * POST /api/nutrition/passio/recipe
     */
    public function analyzeRecipe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'recipe_text' => 'required_without:recipe_url|string|max:10000',
            'recipe_url' => 'required_without:recipe_text|url',
            'servings' => 'nullable|integer|min:1|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $recipeText = $request->recipe_text;
            $recipeUrl = $request->recipe_url;
            $servings = $request->servings ?? 1;

            // Prepare request payload
            $payload = [
                'servings' => $servings,
            ];

            if ($recipeUrl) {
                $payload['url'] = $recipeUrl;
            } else {
                $payload['text'] = $recipeText;
            }

            // Call Passio Recipe API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->passioApiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("{$this->passioBaseUrl}/v2/recipe/analyze", $payload);

            if (!$response->successful()) {
                Log::error('Passio recipe API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to analyze recipe',
                ], $response->status());
            }

            $data = $response->json();
            $formatted = $this->formatRecipeResponse($data);

            Log::info('Recipe analyzed', [
                'user_id' => Auth::id(),
                'servings' => $servings,
                'total_calories' => $formatted['totalCalories'] ?? 0,
                'has_url' => !empty($recipeUrl),
            ]);

            return response()->json([
                'success' => true,
                'data' => $formatted,
            ]);

        } catch (\Exception $e) {
            Log::error('Error analyzing recipe', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to analyze recipe',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // Helper Methods

    protected function formatRecognitionResponse($data)
    {
        return [
            'foodName' => $data['food_name'] ?? $data['name'] ?? 'Unknown Food',
            'confidence' => $data['confidence'] ?? 0,
            'nutritionInfo' => [
                'calories' => $data['nutrition']['calories'] ?? 0,
                'protein' => $data['nutrition']['protein_g'] ?? 0,
                'carbs' => $data['nutrition']['carbs_g'] ?? 0,
                'fat' => $data['nutrition']['fat_g'] ?? 0,
                'fiber' => $data['nutrition']['fiber_g'] ?? 0,
                'servingSize' => $data['nutrition']['serving_size'] ?? '1 serving',
                'servingUnit' => $data['nutrition']['serving_unit'] ?? 'serving',
            ],
            'alternatives' => $data['alternatives'] ?? [],
            'passioId' => $data['passio_id'] ?? null,
            'imageUrl' => $data['image_url'] ?? null,
        ];
    }

    protected function formatBarcodeResponse($data)
    {
        return [
            'barcode' => $data['barcode'] ?? $data['upc'] ?? '',
            'productName' => $data['product_name'] ?? $data['name'] ?? 'Unknown Product',
            'brand' => $data['brand'] ?? '',
            'nutritionInfo' => [
                'calories' => $data['nutrition']['calories'] ?? 0,
                'protein' => $data['nutrition']['protein_g'] ?? 0,
                'carbs' => $data['nutrition']['carbs_g'] ?? 0,
                'fat' => $data['nutrition']['fat_g'] ?? 0,
                'fiber' => $data['nutrition']['fiber_g'] ?? 0,
                'sodium' => $data['nutrition']['sodium_mg'] ?? 0,
                'sugar' => $data['nutrition']['sugar_g'] ?? 0,
                'servingSize' => $data['nutrition']['serving_size'] ?? '1 serving',
                'servingUnit' => $data['nutrition']['serving_unit'] ?? 'serving',
                'servingsPerContainer' => $data['nutrition']['servings_per_container'] ?? 1,
            ],
            'ingredients' => $data['ingredients'] ?? [],
            'allergens' => $data['allergens'] ?? [],
            'imageUrl' => $data['image_url'] ?? null,
            'passioId' => $data['passio_id'] ?? null,
        ];
    }

    protected function formatRecipeResponse($data)
    {
        return [
            'recipeName' => $data['recipe_name'] ?? $data['name'] ?? 'Recipe',
            'servings' => $data['servings'] ?? 1,
            'totalNutrition' => [
                'calories' => $data['total_nutrition']['calories'] ?? 0,
                'protein' => $data['total_nutrition']['protein_g'] ?? 0,
                'carbs' => $data['total_nutrition']['carbs_g'] ?? 0,
                'fat' => $data['total_nutrition']['fat_g'] ?? 0,
                'fiber' => $data['total_nutrition']['fiber_g'] ?? 0,
            ],
            'perServingNutrition' => [
                'calories' => $data['per_serving']['calories'] ?? 0,
                'protein' => $data['per_serving']['protein_g'] ?? 0,
                'carbs' => $data['per_serving']['carbs_g'] ?? 0,
                'fat' => $data['per_serving']['fat_g'] ?? 0,
                'fiber' => $data['per_serving']['fiber_g'] ?? 0,
            ],
            'ingredients' => $data['ingredients'] ?? [],
            'instructions' => $data['instructions'] ?? [],
            'prepTime' => $data['prep_time'] ?? null,
            'cookTime' => $data['cook_time'] ?? null,
            'totalTime' => $data['total_time'] ?? null,
            'imageUrl' => $data['image_url'] ?? null,
        ];
    }
}
