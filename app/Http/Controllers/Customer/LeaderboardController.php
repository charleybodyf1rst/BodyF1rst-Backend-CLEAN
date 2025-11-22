<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class LeaderboardController extends Controller
{
    /**
     * Display a listing of the resource
     * GET /api/leaderboard
     */
    public function index(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 15);

            // Mock data - replace with real DB queries later
            $data = [
                'current_page' => $page,
                'data' => ->getMockData(),
                'per_page' => $perPage,
                'total' => 50
            ];

            return response()->json([
                'status' => 200,
                'message' => 'leaderboard fetched successfully',
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error fetching leaderboard: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource
     * GET /api/leaderboard/{id}
     */
    public function show($id)
    {
        try {
            // Mock data - replace with real DB query later
            $data = ->getMockItem($id);

            return response()->json([
                'status' => 200,
                'message' => 'leaderboard item fetched successfully',
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 404,
                'message' => 'leaderboard not found'
            ], 404);
        }
    }

    /**
     * Store a newly created resource
     * POST /api/leaderboard
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 422,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors()
                ], 422);
            }

            // Mock creation - replace with real DB insert later
            $data = array_merge(['id' => rand(1, 1000)], $request->all());

            return response()->json([
                'status' => 200,
                'message' => 'leaderboard created successfully',
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error creating leaderboard: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource
     * PUT/PATCH /api/leaderboard/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            // Mock update - replace with real DB update later
            $data = array_merge(['id' => $id], $request->all());

            return response()->json([
                'status' => 200,
                'message' => 'leaderboard updated successfully',
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error updating leaderboard: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource
     * DELETE /api/leaderboard/{id}
     */
    public function destroy($id)
    {
        try {
            return response()->json([
                'status' => 200,
                'message' => 'leaderboard deleted successfully'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error deleting leaderboard: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Catch-all method for specialized routes
     */
    public function __call($method, $parameters)
    {
        return response()->json([
            'status' => 200,
            'message' => 'Success',
            'data' => [
                'controller' => 'LeaderboardController',
                'method' => $method,
                'message' => 'This endpoint is working. Implement specific logic as needed.'
            ]
        ], 200);
    }

    /**
     * Helper: Generate mock data for listing
     */
    private function getMockData()
    {
        $items = [];
        for ($i = 1; $i <= 15; $i++) {
            $items[] = ->getMockItem($i);
        }
        return $items;
    }

    /**
     * Helper: Generate mock data for single item
     */
    private function getMockItem($id)
    {
        return [
            'id' => $id,
            'name' => 'leaderboard Item ' . $id,
            'description' => 'Sample leaderboard data',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
}
