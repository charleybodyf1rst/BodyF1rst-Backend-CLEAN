<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function importData(Request $request)
    {
        return response()->json(['status' => 200, 'message' => 'Data imported']);
    }

    public function importWorkouts(Request $request)
    {
        return response()->json(['status' => 200, 'message' => 'Workouts imported']);
    }
}