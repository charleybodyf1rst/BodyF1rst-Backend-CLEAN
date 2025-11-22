<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function getDevices() {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function addDevice(Request $request) {
        return response()->json(['status' => 200, 'data' => ['id' => 1]]);
    }

    public function removeDevice($id) {
        return response()->json(['status' => 200, 'message' => 'Device removed']);
    }

    public function syncDevice($id) {
        return response()->json(['status' => 200, 'message' => 'Device synced']);
    }
}