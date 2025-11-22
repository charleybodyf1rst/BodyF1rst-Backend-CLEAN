<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MealController extends Controller
{
    public function index()
    {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function create(Request $request)
    {
        return response()->json(['status' => 200, 'data' => ['id' => 1]]);
    }

    public function show($id)
    {
        return response()->json(['status' => 200, 'data' => ['id' => $id]]);
    }

    public function logMeal($id)
    {
        return response()->json(['status' => 200, 'message' => 'Meal logged']);
    }
}