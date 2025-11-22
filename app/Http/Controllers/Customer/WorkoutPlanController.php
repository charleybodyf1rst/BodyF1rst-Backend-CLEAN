<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WorkoutPlanController extends Controller
{
    public function index()
    {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function show($id)
    {
        return response()->json(['status' => 200, 'data' => ['id' => $id, 'name' => 'Beginner Plan']]);
    }

    public function subscribe($id)
    {
        return response()->json(['status' => 200, 'message' => 'Subscribed to plan']);
    }
}