<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function exportWorkouts()
    {
        return response()->json(['status' => 200, 'data' => ['file' => 'workouts.csv']]);
    }

    public function exportNutrition()
    {
        return response()->json(['status' => 200, 'data' => ['file' => 'nutrition.csv']]);
    }

    public function exportProgress()
    {
        return response()->json(['status' => 200, 'data' => ['file' => 'progress.csv']]);
    }

    public function exportAll()
    {
        return response()->json(['status' => 200, 'data' => ['file' => 'all_data.zip']]);
    }
}