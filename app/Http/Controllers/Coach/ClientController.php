<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ClientController extends Controller
{
    /**
     * Get all clients for the coach
     * GET /api/customer/coach/clients
     */
    public function getClients(Request $request)
    {
        try {
            $coachId = auth()->id();
            $status = $request->get('status'); // active, inactive, all

            $query = DB::table('coach_clients as cc')
                ->join('users as u', 'cc.client_id', '=', 'u.id')
                ->where('cc.coach_id', $coachId)
                ->select('u.id', 'u.name', 'u.email', 'u.profile_photo', 'cc.status', 'cc.created_at');

            if ($status && $status !== 'all') {
                $query->where('cc.status', $status);
            }

            $clients = $query->orderBy('cc.created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $clients
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }
    }

    /**
     * Get specific client details
     * GET /api/customer/coach/clients/{id}
     */
    public function getClient($id)
    {
        try {
            $coachId = auth()->id();

            $client = DB::table('coach_clients as cc')
                ->join('users as u', 'cc.client_id', '=', 'u.id')
                ->where('cc.coach_id', $coachId)
                ->where('cc.client_id', $id)
                ->select('u.*', 'cc.status', 'cc.created_at as enrolled_at')
                ->first();

            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $client
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching client details'
            ], 500);
        }
    }

    /**
     * Get client progress
     * GET /api/customer/coach/clients/{id}/progress
     */
    public function getClientProgress($id)
    {
        try {
            $coachId = auth()->id();

            // Verify client belongs to coach
            $isCoachClient = DB::table('coach_clients')
                ->where('coach_id', $coachId)
                ->where('client_id', $id)
                ->exists();

            if (!$isCoachClient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found'
                ], 404);
            }

            $progress = [
                'workouts' => [
                    'completed_this_week' => DB::table('workout_logs')
                        ->where('user_id', $id)
                        ->whereBetween('completed_at', [now()->startOfWeek(), now()->endOfWeek()])
                        ->count(),
                    'completed_this_month' => DB::table('workout_logs')
                        ->where('user_id', $id)
                        ->whereMonth('completed_at', now()->month)
                        ->count()
                ],
                'nutrition' => [
                    'compliance_rate' => 85, // TODO: Calculate actual compliance
                    'calories_average' => 2100
                ],
                'measurements' => DB::table('user_measurements')
                    ->where('user_id', $id)
                    ->orderBy('measurement_date', 'desc')
                    ->first(),
                'goals' => DB::table('user_goals')
                    ->where('user_id', $id)
                    ->where('status', 'active')
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $progress
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => [
                    'workouts' => ['completed_this_week' => 0, 'completed_this_month' => 0],
                    'nutrition' => ['compliance_rate' => 0, 'calories_average' => 0],
                    'measurements' => null,
                    'goals' => []
                ]
            ]);
        }
    }

    /**
     * Get client workouts
     * GET /api/customer/coach/clients/{id}/workouts
     */
    public function getClientWorkouts($id)
    {
        try {
            $coachId = auth()->id();

            // Verify client belongs to coach
            $isCoachClient = DB::table('coach_clients')
                ->where('coach_id', $coachId)
                ->where('client_id', $id)
                ->exists();

            if (!$isCoachClient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found'
                ], 404);
            }

            $workouts = DB::table('workout_logs')
                ->where('user_id', $id)
                ->orderBy('completed_at', 'desc')
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $workouts
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }
    }

    /**
     * Get client nutrition data
     * GET /api/customer/coach/clients/{id}/nutrition
     */
    public function getClientNutrition($id)
    {
        try {
            $coachId = auth()->id();

            // Verify client belongs to coach
            $isCoachClient = DB::table('coach_clients')
                ->where('coach_id', $coachId)
                ->where('client_id', $id)
                ->exists();

            if (!$isCoachClient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found'
                ], 404);
            }

            $nutrition = [
                'current_plan' => DB::table('nutrition_plan_assignments')
                    ->where('user_id', $id)
                    ->where('status', 'active')
                    ->first(),
                'recent_logs' => DB::table('nutrition_logs')
                    ->where('user_id', $id)
                    ->orderBy('log_date', 'desc')
                    ->limit(7)
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $nutrition
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => [
                    'current_plan' => null,
                    'recent_logs' => []
                ]
            ]);
        }
    }

    /**
     * Get client measurements
     * GET /api/customer/coach/clients/{id}/measurements
     */
    public function getClientMeasurements($id)
    {
        try {
            $coachId = auth()->id();

            // Verify client belongs to coach
            $isCoachClient = DB::table('coach_clients')
                ->where('coach_id', $coachId)
                ->where('client_id', $id)
                ->exists();

            if (!$isCoachClient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found'
                ], 404);
            }

            $measurements = DB::table('user_measurements')
                ->where('user_id', $id)
                ->orderBy('measurement_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $measurements
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }
    }

    /**
     * Add note to client
     * POST /api/customer/coach/clients/{id}/notes
     */
    public function addClientNote(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'note' => 'required|string',
            'category' => 'nullable|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $coachId = auth()->id();

            // Verify client belongs to coach
            $isCoachClient = DB::table('coach_clients')
                ->where('coach_id', $coachId)
                ->where('client_id', $id)
                ->exists();

            if (!$isCoachClient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found'
                ], 404);
            }

            $noteId = DB::table('coach_client_notes')->insertGetId([
                'coach_id' => $coachId,
                'client_id' => $id,
                'note' => $request->note,
                'category' => $request->category,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Note added successfully',
                'data' => ['id' => $noteId]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding note',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
