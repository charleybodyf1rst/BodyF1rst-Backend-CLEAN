<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SiteController extends Controller
{
    public function siteInfo()          { return response()->json(['ok'=>true,'endpoint'=>'get-site-info'],200); }
    public function faqs()              { return response()->json(['ok'=>true,'endpoint'=>'get-faqs'],200); }
    public function dietary()           { return response()->json(['ok'=>true,'endpoint'=>'get-dietary-restrictions'],200); }
    public function nutrition(Request $request)
    {
        // Validate input data
        $validator = Validator::make($request->all(), [
            'age' => 'required|integer|min:1|max:120',
            'weight' => 'required|numeric|min:20|max:500',
            'height' => 'required|numeric|min:50|max:300',
            'gender' => 'required|string|in:male,female,other',
            'activity_level' => 'required|string|in:sedentary,lightly_active,moderately_active,very_active,extra_active',
            'goal' => 'required|string|in:lose_weight,maintain_weight,gain_weight,gain_muscle',
            'dietary_restrictions' => 'sometimes|array',
            'dietary_restrictions.*' => 'string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Sanitize and cast inputs
        $validatedData = $validator->validated();
        $age = (int) $validatedData['age'];
        $weight = (float) $validatedData['weight'];
        $height = (float) $validatedData['height'];
        $gender = strtolower(trim($validatedData['gender']));
        $activityLevel = strtolower(trim($validatedData['activity_level']));
        $goal = strtolower(trim($validatedData['goal']));
        $dietaryRestrictions = $validatedData['dietary_restrictions'] ?? [];

        // Perform nutrition calculations (example implementation)
        $bmr = $this->calculateBMR($age, $weight, $height, $gender);
        $tdee = $this->calculateTDEE($bmr, $activityLevel);
        $targetCalories = $this->calculateTargetCalories($tdee, $goal);
        $macros = $this->calculateMacros($targetCalories, $goal);

        return response()->json([
            'ok' => true,
            'endpoint' => 'get-nutrition-calculations',
            'data' => [
                'bmr' => round($bmr, 2),
                'tdee' => round($tdee, 2),
                'target_calories' => round($targetCalories, 2),
                'macros' => $macros,
                'dietary_restrictions' => $dietaryRestrictions,
            ]
        ], 200);
    }

    private function calculateBMR($age, $weight, $height, $gender)
    {
        // Mifflin-St Jeor Equation
        if ($gender === 'male') {
            return (10 * $weight) + (6.25 * $height) - (5 * $age) + 5;
        }

        if ($gender === 'female') {
            return (10 * $weight) + (6.25 * $height) - (5 * $age) - 161;
        }

        // For 'other', average the male and female formulas to remain inclusive
        $maleBmr = (10 * $weight) + (6.25 * $height) - (5 * $age) + 5;
        $femaleBmr = (10 * $weight) + (6.25 * $height) - (5 * $age) - 161;

        return ($maleBmr + $femaleBmr) / 2;
    }

    private function calculateTDEE($bmr, $activityLevel)
    {
        $multipliers = [
            'sedentary' => 1.2,
            'lightly_active' => 1.375,
            'moderately_active' => 1.55,
            'very_active' => 1.725,
            'extra_active' => 1.9
        ];

        return $bmr * ($multipliers[$activityLevel] ?? 1.2);
    }

    private function calculateTargetCalories($tdee, $goal)
    {
        switch ($goal) {
            case 'lose_weight':
                return $tdee - 500; // 500 calorie deficit
            case 'gain_weight':
            case 'gain_muscle':
                return $tdee + 300; // 300 calorie surplus
            default:
                return $tdee; // maintain weight
        }
    }

    private function calculateMacros($calories, $goal)
    {
        if ($goal === 'gain_muscle') {
            // Higher protein for muscle gain
            $proteinPercent = 0.30;
            $fatPercent = 0.25;
            $carbPercent = 0.45;
        } elseif ($goal === 'lose_weight') {
            // Higher protein for weight loss
            $proteinPercent = 0.35;
            $fatPercent = 0.25;
            $carbPercent = 0.40;
        } else {
            // Balanced macros
            $proteinPercent = 0.25;
            $fatPercent = 0.30;
            $carbPercent = 0.45;
        }

        return [
            'protein_grams' => round(($calories * $proteinPercent) / 4, 1),
            'fat_grams' => round(($calories * $fatPercent) / 9, 1),
            'carb_grams' => round(($calories * $carbPercent) / 4, 1),
            'protein_percent' => $proteinPercent * 100,
            'fat_percent' => $fatPercent * 100,
            'carb_percent' => $carbPercent * 100,
        ];
    }
    public function notifications()     { return response()->json(['ok'=>true,'endpoint'=>'get-notifications'],200); }
    
    public function myProfile()
    {
        return response()->json([
            'ok'   => true,
            'user' => ['id'=>1,'name'=>'Stub User','email'=>'stub@example.com']
        ], 200);
    }

    public function history()
    {
        return response()->json(['ok'=>true,'endpoint'=>'history','items'=>[]], 200);
    }

    public function coachKenHistory()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'messages' => []
            ]
        ], 200);
    }

    public function getMyProfile()
    {
        return response()->json([
            'ok' => true,
            'user' => [
                'id' => 1,
                'name' => 'Stub User',
                'email' => 'stub@example.com',
                'profile' => []
            ]
        ], 200);
    }

    public function getMyPlans()
    {
        return response()->json([
            'ok' => true,
            'plans' => []
        ], 200);
    }

    public function getMyNutritionPlan()
    {
        return response()->json([
            'ok' => true,
            'nutrition_plan' => []
        ], 200);
    }

    public function getBodyPointsHistory()
    {
        return response()->json([
            'ok' => true,
            'body_points' => []
        ], 200);
    }

    public function getTags()
    {
        return response()->json([
            'ok' => true,
            'tags' => []
        ], 200);
    }
}
