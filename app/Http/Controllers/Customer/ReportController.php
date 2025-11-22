<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function getProgressReport() {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function getNutritionReport() {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function getWorkoutReport() {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function generateReport(Request $request) {
        return response()->json(['status' => 200, 'data' => ['id' => 1]]);
    }

    public function exportData(Request $request) {
        return response()->json(['status' => 200, 'data' => ['export_id' => 1]]);
    }

    public function getExportStatus($id) {
        return response()->json(['status' => 200, 'data' => ['status' => 'ready']]);
    }

    public function downloadExport($id) {
        return response()->json(['status' => 200, 'data' => ['url' => '/exports/' . $id]]);
    }
}