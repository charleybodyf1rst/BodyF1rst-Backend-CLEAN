<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ExerciseController extends Controller
{
    public function index()
    {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function show($id)
    {
        return response()->json(['status' => 200, 'data' => ['id' => $id, 'name' => 'Push-up']]);
    }

    public function byMuscleGroup($group)
    {
        return response()->json(['status' => 200, 'data' => ['muscle_group' => $group, 'exercises' => []]]);
    }
}