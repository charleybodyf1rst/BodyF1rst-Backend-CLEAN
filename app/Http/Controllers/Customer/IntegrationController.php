<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function getIntegrations() {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function connectFitbit(Request $request) {
        return response()->json(['status' => 200, 'message' => 'Fitbit connected']);
    }

    public function disconnectFitbit() {
        return response()->json(['status' => 200, 'message' => 'Fitbit disconnected']);
    }

    public function connectAppleHealth(Request $request) {
        return response()->json(['status' => 200, 'message' => 'Apple Health connected']);
    }

    public function disconnectAppleHealth() {
        return response()->json(['status' => 200, 'message' => 'Apple Health disconnected']);
    }

    public function connectGoogleFit(Request $request) {
        return response()->json(['status' => 200, 'message' => 'Google Fit connected']);
    }

    public function disconnectGoogleFit() {
        return response()->json(['status' => 200, 'message' => 'Google Fit disconnected']);
    }
}