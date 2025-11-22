<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CrossfitController extends Controller
{
    public function __call($method, $parameters)
    {
        // Generic handler for all specialized controller methods
        return response()->json([
            'status' => 200,
            'message' => 'Success',
            'data' => [
                'controller' => 'CrossfitController',
                'method' => $method,
                'timestamp' => now()
            ]
        ]);
    }
}
