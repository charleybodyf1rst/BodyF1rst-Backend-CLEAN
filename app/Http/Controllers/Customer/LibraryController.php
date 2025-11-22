<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class LibraryController extends Controller
{
    /**
     * Display a listing of the resource
     * GET /api/library
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
                'message' => 'library fetched successfully',
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error fetching library: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource
     * GET /api/library/{id}
     */
    public function show($id)
    {
        try {
            // Mock data - replace with real DB query later
            $data = ->getMockItem($id);

            return response()->json([
                'status' => 200,
                'message' => 'library item fetched successfully',
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 404,
                'message' => 'library not found'
            ], 404);
        }
    }

    /**
     * Store a newly created resource
     * POST /api/library
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
                'message' => 'library created successfully',
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error creating library: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource
     * PUT/PATCH /api/library/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            // Mock update - replace with real DB update later
            $data = array_merge(['id' => $id], $request->all());

            return response()->json([
                'status' => 200,
                'message' => 'library updated successfully',
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error updating library: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource
     * DELETE /api/library/{id}
     */
    public function destroy($id)
    {
        try {
            return response()->json([
                'status' => 200,
                'message' => 'library deleted successfully'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error deleting library: ' . $e->getMessage()
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
                'controller' => 'LibraryController',
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
            'name' => 'library Item ' . $id,
            'description' => 'Sample library data',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
}
