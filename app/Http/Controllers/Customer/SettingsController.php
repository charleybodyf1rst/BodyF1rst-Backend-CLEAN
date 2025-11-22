<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function getSettings() {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function updateSettings(Request $request) {
        return response()->json(['status' => 200, 'message' => 'Settings updated']);
    }

    public function getPrivacy() {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function updatePrivacy(Request $request) {
        return response()->json(['status' => 200, 'message' => 'Privacy updated']);
    }

    public function getUnits() {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function updateUnits(Request $request) {
        return response()->json(['status' => 200, 'message' => 'Units updated']);
    }

    public function getGoals() {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function updateGoals(Request $request) {
        return response()->json(['status' => 200, 'message' => 'Goals updated']);
    }
}