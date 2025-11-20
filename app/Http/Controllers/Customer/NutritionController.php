<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\BodyPoint;
use App\Models\NutritionCalculation;
use App\Models\UserNutrition;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class NutritionController extends Controller
{
    /**
     * Get user's daily nutrition data
     */
    public function getDailyNutrition(Request $request)
    {
        $user = Auth::user();
        $date = $request->get('date', Carbon::today()->toDateString());
        
        $nutrition = UserNutrition::where('user_id', $user->id)
            ->whereDate('date', $date)
            ->first();

        if (!$nutrition) {
            // Create default nutrition entry for the day
            $nutrition = UserNutrition::create([
                'user_id' => $user->id,
                'date' => $date,
                'calories_consumed' => 0,
                'calories_target' => $user->daily_calorie_goal ?? 2000,
                'protein_consumed' => 0,
                'protein_target' => $user->daily_protein_goal ?? 150,
                'carbs_consumed' => 0,
                'carbs_target' => $user->daily_carbs_goal ?? 200,
                'fats_consumed' => 0,
                'fats_target' => $user->daily_fats_goal ?? 65,
            ]);
        }

        // Calculate percentages
        $caloriePercentage = $nutrition->calories_target > 0 
            ? min(100, ($nutrition->calories_consumed / $nutrition->calories_target) * 100) 
            : 0;
        
        $proteinPercentage = $nutrition->protein_target > 0 
            ? min(100, ($nutrition->protein_consumed / $nutrition->protein_target) * 100) 
            : 0;
        
        $carbsPercentage = $nutrition->carbs_target > 0 
            ? min(100, ($nutrition->carbs_consumed / $nutrition->carbs_target) * 100) 
            : 0;
        
        $fatsPercentage = $nutrition->fats_target > 0 
            ? min(100, ($nutrition->fats_consumed / $nutrition->fats_target) * 100) 
            : 0;

        // Calculate body points based on nutrition completion
        $bodyPoints = $this->calculateNutritionBodyPoints($caloriePercentage, $proteinPercentage, $carbsPercentage, $fatsPercentage);

        $response = [
            "status" => 200,
            "message" => "Daily nutrition data retrieved successfully",
            "nutrition" => [
                "date" => $date,
                "calories" => [
                    "consumed" => $nutrition->calories_consumed,
                    "target" => $nutrition->calories_target,
                    "remaining" => max(0, $nutrition->calories_target - $nutrition->calories_consumed),
                    "percentage" => round($caloriePercentage, 1)
                ],
                "macros" => [
                    "protein" => [
                        "consumed" => $nutrition->protein_consumed,
                        "target" => $nutrition->protein_target,
                        "percentage" => round($proteinPercentage, 1)
                    ],
                    "carbs" => [
                        "consumed" => $nutrition->carbs_consumed,
                        "target" => $nutrition->carbs_target,
                        "percentage" => round($carbsPercentage, 1)
                    ],
                    "fats" => [
                        "consumed" => $nutrition->fats_consumed,
                        "target" => $nutrition->fats_target,
                        "percentage" => round($fatsPercentage, 1)
                    ]
                ],
                "body_points" => $bodyPoints,
                "last_synced" => $nutrition->last_synced_at
            ]
        ];

        return response($response, $response['status']);
    }

    /**
     * Update daily nutrition intake
     */
    public function updateNutritionIntake(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'calories_consumed' => 'nullable|numeric|min:0',
            'protein_consumed' => 'nullable|numeric|min:0',
            'carbs_consumed' => 'nullable|numeric|min:0',
            'fats_consumed' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response([
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        $date = $request->date;

        $nutrition = UserNutrition::updateOrCreate(
            [
                'user_id' => $user->id,
                'date' => $date
            ],
            [
                'calories_consumed' => $request->calories_consumed ?? 0,
                'calories_target' => $user->daily_calorie_goal ?? 2000,
                'protein_consumed' => $request->protein_consumed ?? 0,
                'protein_target' => $user->daily_protein_goal ?? 150,
                'carbs_consumed' => $request->carbs_consumed ?? 0,
                'carbs_target' => $user->daily_carbs_goal ?? 200,
                'fats_consumed' => $request->fats_consumed ?? 0,
                'fats_target' => $user->daily_fats_goal ?? 65,
                'updated_at' => now()
            ]
        );

        return response([
            "status" => 200,
            "message" => "Nutrition intake updated successfully",
            "nutrition" => $nutrition
        ], 200);
    }

    /**
     * Sync nutrition data from health apps
     */
    public function syncHealthAppData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'source' => 'required|string|in:apple_health,google_fit,myfitnesspal',
            'calories' => 'nullable|numeric|min:0',
            'protein' => 'nullable|numeric|min:0',
            'carbs' => 'nullable|numeric|min:0',
            'fats' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response([
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        $date = $request->date;

        $nutrition = UserNutrition::updateOrCreate(
            [
                'user_id' => $user->id,
                'date' => $date
            ],
            [
                'calories_consumed' => $request->calories ?? 0,
                'protein_consumed' => $request->protein ?? 0,
                'carbs_consumed' => $request->carbs ?? 0,
                'fats_consumed' => $request->fats ?? 0,
                'sync_source' => $request->source,
                'last_synced_at' => now(),
                'updated_at' => now()
            ]
        );

        return response([
            "status" => 200,
            "message" => "Health app data synced successfully",
            "nutrition" => $nutrition,
            "source" => $request->source
        ], 200);
    }

    /**
     * Get nutrition goals/targets for user
     */
    public function getNutritionGoals(Request $request)
    {
        $user = Auth::user();

        $goals = [
            'calories' => $user->daily_calorie_goal ?? 2000,
            'protein' => $user->daily_protein_goal ?? 150,
            'carbs' => $user->daily_carbs_goal ?? 200,
            'fats' => $user->daily_fats_goal ?? 65,
        ];

        return response([
            "status" => 200,
            "message" => "Nutrition goals retrieved successfully",
            "goals" => $goals
        ], 200);
    }

    /**
     * Update nutrition goals/targets for user
     */
    public function updateNutritionGoals(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'calories' => 'nullable|numeric|min:1000|max:5000',
            'protein' => 'nullable|numeric|min:50|max:300',
            'carbs' => 'nullable|numeric|min:100|max:500',
            'fats' => 'nullable|numeric|min:30|max:150',
        ]);

        if ($validator->fails()) {
            return response([
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        
        $updateData = [];
        if ($request->has('calories')) $updateData['daily_calorie_goal'] = $request->calories;
        if ($request->has('protein')) $updateData['daily_protein_goal'] = $request->protein;
        if ($request->has('carbs')) $updateData['daily_carbs_goal'] = $request->carbs;
        if ($request->has('fats')) $updateData['daily_fats_goal'] = $request->fats;

        $user->update($updateData);

        return response([
            "status" => 200,
            "message" => "Nutrition goals updated successfully",
            "goals" => [
                'calories' => $user->daily_calorie_goal,
                'protein' => $user->daily_protein_goal,
                'carbs' => $user->daily_carbs_goal,
                'fats' => $user->daily_fats_goal,
            ]
        ], 200);
    }

    /**
     * Get nutrition history for a date range
     */
    public function getNutritionHistory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response([
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        
        $nutritionHistory = UserNutrition::where('user_id', $user->id)
            ->whereBetween('date', [$request->start_date, $request->end_date])
            ->orderBy('date', 'desc')
            ->get()
            ->map(function ($nutrition) {
                return [
                    'date' => $nutrition->date,
                    'calories' => [
                        'consumed' => $nutrition->calories_consumed,
                        'target' => $nutrition->calories_target,
                        'percentage' => $nutrition->calories_target > 0 
                            ? round(($nutrition->calories_consumed / $nutrition->calories_target) * 100, 1) 
                            : 0
                    ],
                    'macros' => [
                        'protein' => [
                            'consumed' => $nutrition->protein_consumed,
                            'target' => $nutrition->protein_target,
                            'percentage' => $nutrition->protein_target > 0 
                                ? round(($nutrition->protein_consumed / $nutrition->protein_target) * 100, 1) 
                                : 0
                        ],
                        'carbs' => [
                            'consumed' => $nutrition->carbs_consumed,
                            'target' => $nutrition->carbs_target,
                            'percentage' => $nutrition->carbs_target > 0 
                                ? round(($nutrition->carbs_consumed / $nutrition->carbs_target) * 100, 1) 
                                : 0
                        ],
                        'fats' => [
                            'consumed' => $nutrition->fats_consumed,
                            'target' => $nutrition->fats_target,
                            'percentage' => $nutrition->fats_target > 0 
                                ? round(($nutrition->fats_consumed / $nutrition->fats_target) * 100, 1) 
                                : 0
                        ]
                    ],
                    'sync_source' => $nutrition->sync_source,
                    'last_synced' => $nutrition->last_synced_at
                ];
            });

        return response([
            "status" => 200,
            "message" => "Nutrition history retrieved successfully",
            "history" => $nutritionHistory,
            "total_days" => $nutritionHistory->count()
        ], 200);
    }

    /**
     * Calculate body points based on nutrition completion
     */
    private function calculateNutritionBodyPoints($caloriePercentage, $proteinPercentage, $carbsPercentage, $fatsPercentage)
    {
        // Get body points configuration
        $bodyPointsConfig = BodyPoint::where('meta_key', 'nutrition')->first();
        $maxPoints = $bodyPointsConfig ? $bodyPointsConfig->meta_value['max_points'] ?? 100 : 100;

        // Calculate average completion percentage
        $averageCompletion = ($caloriePercentage + $proteinPercentage + $carbsPercentage + $fatsPercentage) / 4;

        // Calculate points based on completion percentage
        $points = round(($averageCompletion / 100) * $maxPoints);

        return [
            'earned' => $points,
            'max' => $maxPoints,
            'percentage' => round($averageCompletion, 1),
            'breakdown' => [
                'calories' => round(($caloriePercentage / 100) * ($maxPoints / 4)),
                'protein' => round(($proteinPercentage / 100) * ($maxPoints / 4)),
                'carbs' => round(($carbsPercentage / 100) * ($maxPoints / 4)),
                'fats' => round(($fatsPercentage / 100) * ($maxPoints / 4))
            ]
        ];
    }

    /**
     * Analyze food from photo using Passio AI
     * POST /api/customer/nutrition/analyze-photo
     */
    public function analyzePhoto(Request $request)
    {
        $validated = $request->validate([
            'image' => 'required|image|max:10240', // 10MB max
        ]);

        $user = Auth::user();

        try {
            // Upload image to temporary storage
            $imagePath = $request->file('image')->store('temp/nutrition-photos', 'public');
            $fullPath = storage_path('app/public/' . $imagePath);

            // Use Passio MCP to recognize food
            // Note: This requires the Passio Nutrition MCP server to be configured
            $imageUrl = asset('storage/' . $imagePath);

            // For now, return a mock response that can be replaced with actual Passio integration
            // When MCP is available, use: mcp__passio-nutrition__recognize_food

            return response()->json([
                'success' => true,
                'message' => 'Photo analyzed successfully',
                'data' => [
                    'recognized_foods' => [],
                    'image_url' => $imageUrl,
                    'note' => 'Passio AI integration pending - please configure MCP server'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to analyze photo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Swap a meal in the meal plan with an alternative
     * POST /api/customer/nutrition/swap-meal/{mealId}
     */
    public function swapMeal($mealId, Request $request)
    {
        $validated = $request->validate([
            'alternative_meal_id' => 'required|exists:meals,id',
            'reason' => 'nullable|string|in:preference,allergy,availability,other'
        ]);

        $user = Auth::user();

        try {
            // Find the current meal assignment
            $currentMeal = DB::table('user_meal_plans')
                ->where('id', $mealId)
                ->where('user_id', $user->id)
                ->first();

            if (!$currentMeal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Meal not found in your plan'
                ], 404);
            }

            // Get alternative meal details
            $alternativeMeal = DB::table('meals')->find($validated['alternative_meal_id']);

            // Update the meal plan
            DB::table('user_meal_plans')
                ->where('id', $mealId)
                ->update([
                    'meal_id' => $validated['alternative_meal_id'],
                    'updated_at' => now()
                ]);

            // Log the swap
            DB::table('meal_swap_history')->insert([
                'user_id' => $user->id,
                'original_meal_id' => $currentMeal->meal_id,
                'new_meal_id' => $validated['alternative_meal_id'],
                'reason' => $validated['reason'] ?? 'preference',
                'swapped_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Meal swapped successfully',
                'data' => [
                    'new_meal' => $alternativeMeal,
                    'meal_plan_id' => $mealId
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to swap meal: ' . $e->getMessage()
            ], 500);
        }
    }
}
