<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FoodController extends Controller
{
    public function search(Request $request)
    {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function show($id)
    {
        return response()->json(['status' => 200, 'data' => ['id' => $id, 'name' => 'Apple']]);
    }

    public function scanBarcode($code)
    {
        return response()->json(['status' => 200, 'data' => ['barcode' => $code]]);
    }
}