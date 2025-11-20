<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BodyPoint;
use App\Models\NutritionCalculation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NutritionController extends Controller
{
    public function getNutritionCalculations(Request $request)
    {
        $nutrition_calculations = NutritionCalculation::where('is_current', 1)->select('meta_key', 'meta_value')->get();

        $nutrition_data = $nutrition_calculations->mapWithKeys(function ($calculation) {
            return [$calculation['meta_key'] => $calculation['meta_value']];
        });

        $response = [
            "status" => 200,
            "message" => "Nutrition Calculation Fetched",
            "nutrition_calculations" => $nutrition_data,
        ];

        return response($response, $response['status']);
    }

    public function updateNutritionCalculation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'meta_key' => 'required',
            'meta_value' => 'required|array',
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }

        $existingRecordCount = NutritionCalculation::where('meta_key', $request->meta_key)->count();
        $existingRecord = NutritionCalculation::where('meta_key', $request->meta_key)->where('is_default', 1)->first();
        if (isset($existingRecord)) {
            $existingRecord->is_current = 0;
            $existingRecord->save();
        }
        $name = ucfirst($request->meta_key);
        if ($existingRecordCount === 1) {

            $new_record = NutritionCalculation::create([
                'meta_key' => $request->meta_key,
                'meta_value' => json_encode($request->meta_value),
            ]);
            $new_record->is_default = 0;
            $new_record->is_current = 1;
            $new_record->save();

            $nutrition_data = [$new_record['meta_key'] => $new_record['meta_value']];
            $response = [
                "status" => 200,
                "message" => "$name Calculations Updated successfully",
                "nutrition_calculations" => $nutrition_data
            ];
        } elseif ($existingRecordCount === 2) {
            $record = NutritionCalculation::where('meta_key', $request->meta_key)->skip(1)->first();
            if ($record) {
                $record->update([
                    'meta_value' => json_encode($request->meta_value),
                ]);
                $record->is_default = 0;
                $record->is_current = 1;
                $record->save();

                $nutrition_data = [$record['meta_key'] => $record['meta_value']];
                $response = [
                    "status" => 200,
                    "message" => "$name Calculations Updated successfully",
                    "nutrition_calculations" => $nutrition_data
                ];
            } else {
                $response = [
                    "status" => 422,
                    "message" => "$name Calculations Not Found",
                ];
            }
        } else {
            $response = [
                "status" => 422,
                "message" => "Invalid record count for this meta_key",
            ];
        }

        return response($response, $response["status"]);
    }

    public function restoreNutritionCalculation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'meta_key' => 'required',
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }

        $name = ucfirst($request->meta_key);
        $defaultRecord = NutritionCalculation::where('meta_key', $request->meta_key)->where('is_default', 1)->first();
        if (isset($defaultRecord)) {
            $defaultRecord->is_current = 1;
            $defaultRecord->save();

            $nutrition_data = [$defaultRecord['meta_key'] => $defaultRecord['meta_value']];

            NutritionCalculation::where('meta_key', $request->meta_key)
                ->where('is_default', 0)
                ->update(['is_current' => 0]);

            $response = [
                "status" => 200,
                "message" => "$name Restored to Default Successfully",
                "nutrition_calculations" => $nutrition_data
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "No Default Record found for the $name",
            ];
        }

        return response($response, $response["status"]);
    }

    //BodyPoints
    public function getBodyPoints(Request $request)
    {
        $body_points = BodyPoint::select('meta_key', 'meta_value')->get();

        $body_data = $body_points->mapWithKeys(function ($calculation) {
            return [$calculation['meta_key'] => $calculation['meta_value']];
        });

        $response = [
            "status" => 200,
            "message" => "Body Points Fetched",
            "body_points" => $body_data,
        ];

        return response($response, $response['status']);
    }

    public function updateBodyPoint(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'meta_key' => 'required',
            'meta_value' => 'required|array',
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }

        $body_point = BodyPoint::where('meta_key',$request->meta_key)->first();

        if(isset($body_point))
        {
            $body_point->update([
                'meta_value' => $request->meta_value,
            ]);
            $body_point->save();

            $body_data = [$body_point['meta_key'] => $body_point['meta_value']];
            $response = [
                "status" => 200,
                "message" => "Body Points Updated successfully",
                "body_points" => $body_data
            ];
        }
        else
        {
            $response = [
                "status" => 422,
                "message" => "Body Points Not Found!",
            ];
        }

        return response($response, $response["status"]);
    }

    /**
     * Get all nutrition plans (meal templates) for assignment
     */
    public function getAllNutritionPlans(Request $request)
    {
        // For now, return meal templates as nutrition plans
        // This can be extended later to include proper nutrition plan models
        $mealTemplates = \App\Models\MealTemplate::with('coach:id,first_name,last_name')
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where(function ($subquery) use ($request) {
                    $subquery->where('name', 'LIKE', '%' . $request->search . '%')
                        ->orWhere('description', 'LIKE', '%' . $request->search . '%');
                });
            })
            ->when($request->filled('meal_type'), function ($query) use ($request) {
                $query->where('meal_type', $request->meal_type);
            })
            ->when($request->filled('category'), function ($query) use ($request) {
                $query->where('category', $request->category);
            })
            ->orderBy('use_count', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'description' => $template->description,
                    'meal_type' => $template->meal_type,
                    'total_calories' => $template->total_calories,
                    'protein_g' => $template->total_protein_g,
                    'carbs_g' => $template->total_carbs_g,
                    'fat_g' => $template->total_fat_g,
                    'fiber_g' => $template->total_fiber_g,
                    'category' => $template->category,
                    'tags' => $template->tags,
                    'prep_time_minutes' => $template->prep_time_minutes,
                    'cook_time_minutes' => $template->cook_time_minutes,
                    'use_count' => $template->use_count,
                    'is_public' => $template->is_public,
                    'coach_name' => ($template->coach->first_name ?? '') . ' ' . ($template->coach->last_name ?? ''),
                    'image_url' => $template->image_url,
                ];
            });

        return response([
            "status" => 200,
            "message" => "Nutrition Plans Fetched Successfully",
            "success" => true,
            "data" => $mealTemplates
        ], 200);
    }
}
