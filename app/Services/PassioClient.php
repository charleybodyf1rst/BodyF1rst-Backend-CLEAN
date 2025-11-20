<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PassioClient
{
    private Client $client;
    private string $baseUrl;
    private string $apiKey;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.passio.base_url');
        $this->apiKey = config('services.passio.api_key');
        $this->timeout = config('services.passio.timeout', 15);

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => $this->timeout,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Get or refresh the bearer token from Passio API
     */
    private function getBearerToken(): ?string
    {
        $cacheKey = 'passio_bearer_token';
        
        // Try to get cached token
        $token = Cache::get($cacheKey);
        if ($token) {
            return $token;
        }

        try {
            // Request new token
            $response = $this->client->post('/v2/token', [
                'json' => [
                    'apiKey' => $this->apiKey,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            if (isset($data['accessToken'])) {
                $token = $data['accessToken'];
                $expiresIn = $data['expiresIn'] ?? 3600; // Default 1 hour
                
                // Cache token for slightly less time than expiry
                Cache::put($cacheKey, $token, now()->addSeconds($expiresIn - 300));
                
                return $token;
            }
        } catch (RequestException $e) {
            Log::error('Failed to get Passio bearer token', [
                'error' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
            ]);
        }

        return null;
    }

    /**
     * Make authenticated request to Passio API
     */
    public function request(string $method, string $endpoint, array $data = []): ?array
    {
        $token = $this->getBearerToken();
        if (!$token) {
            Log::error('No bearer token available for Passio API request');
            return null;
        }

        try {
            $options = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ];

            if (!empty($data)) {
                $options['json'] = $data;
            }

            $response = $this->client->request($method, $endpoint, $options);
            
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Passio API request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
            ]);
            
            return null;
        }
    }

    /**
     * Fetch meal plan from Passio API
     */
    public function fetchMealPlan(array $parameters = []): ?array
    {
        return $this->request('POST', '/v2/meal-plan', $parameters);
    }

    /**
     * Generate AI-powered meal plan with advanced parameters
     *
     * @param array $userProfile User information (age, gender, weight, height, activity)
     * @param array $goals Nutrition goals (calories, protein, carbs, fat)
     * @param array $preferences Dietary preferences (vegetarian, vegan, keto, etc.)
     * @param array $restrictions Food allergies and restrictions
     * @param int $days Number of days to generate (default 7)
     * @return array|null Generated meal plan
     */
    public function generateAIMealPlan(
        array $userProfile,
        array $goals,
        array $preferences = [],
        array $restrictions = [],
        int $days = 7
    ): ?array {
        $parameters = [
            'userProfile' => $userProfile,
            'nutritionGoals' => $goals,
            'dietaryPreferences' => $preferences,
            'restrictions' => $restrictions,
            'days' => $days,
            'mealsPerDay' => $preferences['meals_per_day'] ?? 3,
            'includeSnacks' => $preferences['include_snacks'] ?? true,
        ];

        return $this->request('POST', '/v2/ai/meal-plan/generate', $parameters);
    }

    /**
     * Get food substitutions for a given food item
     *
     * @param string $foodId Original food item ID
     * @param array $criteria Substitution criteria (similar_calories, similar_macros, etc.)
     * @param int $limit Number of substitutions to return
     * @return array|null List of substitute foods
     */
    public function getFoodSubstitutions(
        string $foodId,
        array $criteria = ['similar_nutrition' => true],
        int $limit = 10
    ): ?array {
        $parameters = array_merge($criteria, [
            'foodId' => $foodId,
            'limit' => $limit,
        ]);

        return $this->request('POST', '/v2/food/substitutions', $parameters);
    }

    /**
     * Find food substitutions by nutrition profile
     *
     * @param array $nutritionTarget Target nutrition values
     * @param array $dietaryRestrictions Dietary restrictions to consider
     * @param int $limit Number of results
     * @return array|null Matching foods
     */
    public function findFoodByNutrition(
        array $nutritionTarget,
        array $dietaryRestrictions = [],
        int $limit = 20
    ): ?array {
        $parameters = [
            'nutrition' => $nutritionTarget,
            'restrictions' => $dietaryRestrictions,
            'limit' => $limit,
        ];

        return $this->request('POST', '/v2/food/find-by-nutrition', $parameters);
    }

    /**
     * Search for food items with enhanced filters
     *
     * @param string $query Search query
     * @param array $filters Additional filters (category, brand, etc.)
     * @return array|null Search results
     */
    public function searchFood(string $query, array $filters = []): ?array
    {
        $parameters = array_merge(['query' => $query], $filters);
        return $this->request('GET', '/v2/search', $parameters);
    }

    /**
     * Enhanced barcode scan with nutrition details
     *
     * @param string $barcode UPC/EAN barcode
     * @param bool $includeAlternatives Include alternative products
     * @return array|null Food item data
     */
    public function scanBarcode(string $barcode, bool $includeAlternatives = false): ?array
    {
        $parameters = [
            'barcode' => $barcode,
            'includeAlternatives' => $includeAlternatives,
            'includeNutrition' => true,
        ];

        return $this->request('GET', '/v2/barcode/scan', $parameters);
    }

    /**
     * Get detailed nutrition data for a food item
     *
     * @param string $foodId Food item ID
     * @param float $servingSize Optional serving size multiplier
     * @return array|null Nutrition data
     */
    public function getNutritionData(string $foodId, float $servingSize = 1.0): ?array
    {
        $parameters = ['servingSize' => $servingSize];
        return $this->request('GET', "/v2/nutrition/{$foodId}", $parameters);
    }

    /**
     * Get food recommendations based on user preferences and recent meals
     *
     * @param array $userPreferences User dietary preferences
     * @param array $recentMeals Recently logged meals
     * @param string $mealType Type of meal (breakfast, lunch, dinner, snack)
     * @return array|null Recommended foods
     */
    public function getFoodRecommendations(
        array $userPreferences,
        array $recentMeals = [],
        string $mealType = 'any'
    ): ?array {
        $parameters = [
            'preferences' => $userPreferences,
            'recentMeals' => $recentMeals,
            'mealType' => $mealType,
        ];

        return $this->request('POST', '/v2/recommendations', $parameters);
    }

    /**
     * Analyze meal and provide nutrition breakdown
     *
     * @param array $foods List of foods with quantities
     * @return array|null Meal analysis
     */
    public function analyzeMeal(array $foods): ?array
    {
        return $this->request('POST', '/v2/meal/analyze', ['foods' => $foods]);
    }

    /**
     * Get popular foods by category
     *
     * @param string $category Food category
     * @param int $limit Number of results
     * @return array|null Popular foods
     */
    public function getPopularFoods(string $category = 'all', int $limit = 50): ?array
    {
        return $this->request('GET', '/v2/foods/popular', [
            'category' => $category,
            'limit' => $limit,
        ]);
    }

    /**
     * Validate and correct food portions
     *
     * @param string $foodId Food ID
     * @param float $quantity Quantity
     * @param string $unit Unit of measurement
     * @return array|null Corrected portion data
     */
    public function validatePortion(string $foodId, float $quantity, string $unit): ?array
    {
        return $this->request('POST', '/v2/portion/validate', [
            'foodId' => $foodId,
            'quantity' => $quantity,
            'unit' => $unit,
        ]);
    }

    /**
     * Recognize food from image using Passio Vision AI
     *
     * @param string $imageData Base64 encoded image data
     * @param string $imageFormat Image format (base64, url)
     * @return array|null Recognition results
     */
    public function recognizeFood(string $imageData, string $imageFormat = 'base64'): ?array
    {
        $parameters = [
            'image' => $imageData,
            'imageFormat' => $imageFormat,
        ];

        return $this->request('POST', '/v2/vision/recognize', $parameters);
    }

    /**
     * Test connectivity to Passio API
     */
    public function ping(): array
    {
        try {
            $token = $this->getBearerToken();
            return [
                'status' => $token ? 'success' : 'failed',
                'message' => $token ? 'Successfully connected to Passio API' : 'Failed to get bearer token',
                'timestamp' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ];
        }
    }
}
