<?php

namespace App\Http\Controllers;

use App\Models\WeeklyCheckin;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class WeeklyCheckinController extends Controller
{
    /**
     * Get all check-ins for a client
     */
    public function getClientCheckins(Request $request)
    {
        $user = $request->user();

        $checkins = WeeklyCheckin::where('user_id', $user->id)
            ->with('coach:id,first_name,last_name,email')
            ->orderBy('checkin_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $checkins
        ]);
    }

    /**
     * Get check-ins for a specific coach's clients
     */
    public function getCoachCheckins(Request $request)
    {
        $coach = $request->user();

        $status = $request->query('status'); // pending, submitted, reviewed
        $clientId = $request->query('client_id');

        $query = WeeklyCheckin::where('coach_id', $coach->id)
            ->with('user:id,first_name,last_name,email,avatar');

        if ($status) {
            $query->where('status', $status);
        }

        if ($clientId) {
            $query->where('user_id', $clientId);
        }

        $checkins = $query->orderBy('checkin_date', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $checkins
        ]);
    }

    /**
     * Get a specific check-in by ID
     */
    public function getCheckin(Request $request, $id)
    {
        $user = $request->user();

        $checkin = WeeklyCheckin::with(['user', 'coach'])
            ->findOrFail($id);

        // Check authorization: user must be the client or the coach
        if ($checkin->user_id !== $user->id && $checkin->coach_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $checkin
        ]);
    }

    /**
     * Create a new weekly check-in
     */
    public function createCheckin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'checkin_date' => 'required|date',
            'week_number' => 'required|integer|min:1',
            'current_weight' => 'nullable|numeric',
            'weight_unit' => 'nullable|in:lbs,kg',
            'body_fat_percentage' => 'nullable|numeric|min:0|max:100',
            'measurements' => 'nullable|array',
            'energy_level' => 'nullable|integer|min:1|max:10',
            'mood' => 'nullable|integer|min:1|max:10',
            'sleep_quality' => 'nullable|integer|min:1|max:10',
            'sleep_hours' => 'nullable|numeric|min:0|max:24',
            'stress_level' => 'nullable|integer|min:1|max:10',
            'workouts_completed' => 'nullable|integer|min:0',
            'workouts_planned' => 'nullable|integer|min:0',
            'meals_logged' => 'nullable|integer|min:0',
            'water_intake_oz' => 'nullable|numeric|min:0',
            'what_went_well' => 'nullable|string',
            'challenges_faced' => 'nullable|string',
            'goals_next_week' => 'nullable|string',
            'questions_for_coach' => 'nullable|string',
            'additional_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Get user's coach
        $coachId = $user->coach_id ?? null;

        $checkin = WeeklyCheckin::create(array_merge(
            $validator->validated(),
            [
                'user_id' => $user->id,
                'coach_id' => $coachId,
                'status' => 'pending'
            ]
        ));

        return response()->json([
            'success' => true,
            'message' => 'Check-in created successfully',
            'data' => $checkin
        ], 201);
    }

    /**
     * Update a weekly check-in (client submits or edits)
     */
    public function updateCheckin(Request $request, $id)
    {
        $checkin = WeeklyCheckin::findOrFail($id);

        // Check authorization
        if ($checkin->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'current_weight' => 'nullable|numeric',
            'weight_unit' => 'nullable|in:lbs,kg',
            'body_fat_percentage' => 'nullable|numeric|min:0|max:100',
            'measurements' => 'nullable|array',
            'energy_level' => 'nullable|integer|min:1|max:10',
            'mood' => 'nullable|integer|min:1|max:10',
            'sleep_quality' => 'nullable|integer|min:1|max:10',
            'sleep_hours' => 'nullable|numeric|min:0|max:24',
            'stress_level' => 'nullable|integer|min:1|max:10',
            'workouts_completed' => 'nullable|integer|min:0',
            'workouts_planned' => 'nullable|integer|min:0',
            'meals_logged' => 'nullable|integer|min:0',
            'water_intake_oz' => 'nullable|numeric|min:0',
            'what_went_well' => 'nullable|string',
            'challenges_faced' => 'nullable|string',
            'goals_next_week' => 'nullable|string',
            'questions_for_coach' => 'nullable|string',
            'additional_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $checkin->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Check-in updated successfully',
            'data' => $checkin
        ]);
    }

    /**
     * Submit a weekly check-in (changes status from pending to submitted)
     */
    public function submitCheckin(Request $request, $id)
    {
        $checkin = WeeklyCheckin::findOrFail($id);

        // Check authorization
        if ($checkin->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $checkin->update([
            'status' => 'submitted',
            'submitted_at' => now()
        ]);

        // Send notification to coach
        if ($checkin->coach_id) {
            \App\Jobs\SendNotification::dispatch(
                $checkin->coach_id,
                'weekly_checkin_submitted',
                [
                    'client_name' => $request->user()->name,
                    'week_number' => $checkin->week_number,
                    'checkin_id' => $checkin->id,
                    'message' => 'Weekly check-in submitted by ' . $request->user()->name
                ],
                ['push', 'email']
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Check-in submitted successfully',
            'data' => $checkin
        ]);
    }

    /**
     * Coach provides feedback on a check-in
     */
    public function provideFeedback(Request $request, $id)
    {
        $checkin = WeeklyCheckin::findOrFail($id);

        // Check authorization: user must be the coach
        if ($checkin->coach_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'coach_feedback' => 'required|string',
            'coach_recommendations' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $checkin->update([
            'coach_feedback' => $request->coach_feedback,
            'coach_recommendations' => $request->coach_recommendations,
            'coach_reviewed_at' => now(),
            'status' => 'reviewed'
        ]);

        // Send notification to client
        \App\Jobs\SendNotification::dispatch(
            $checkin->user_id,
            'weekly_checkin_reviewed',
            [
                'coach_name' => $request->user()->name,
                'week_number' => $checkin->week_number,
                'checkin_id' => $checkin->id,
                'message' => 'Your coach has reviewed your week ' . $checkin->week_number . ' check-in'
            ],
            ['push', 'email']
        );

        return response()->json([
            'success' => true,
            'message' => 'Feedback provided successfully',
            'data' => $checkin
        ]);
    }

    /**
     * Upload progress photos for a check-in
     */
    public function uploadPhotos(Request $request, $id)
    {
        $checkin = WeeklyCheckin::findOrFail($id);

        // Check authorization
        if ($checkin->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'front_photo' => 'nullable|image|max:10240', // 10MB max
            'side_photo' => 'nullable|image|max:10240',
            'back_photo' => 'nullable|image|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $photos = [];

        if ($request->hasFile('front_photo')) {
            $path = $request->file('front_photo')->store('checkins/photos', 'public');
            $photos['front_photo'] = $path;

            // Delete old photo if exists
            if ($checkin->front_photo) {
                Storage::disk('public')->delete($checkin->front_photo);
            }
        }

        if ($request->hasFile('side_photo')) {
            $path = $request->file('side_photo')->store('checkins/photos', 'public');
            $photos['side_photo'] = $path;

            if ($checkin->side_photo) {
                Storage::disk('public')->delete($checkin->side_photo);
            }
        }

        if ($request->hasFile('back_photo')) {
            $path = $request->file('back_photo')->store('checkins/photos', 'public');
            $photos['back_photo'] = $path;

            if ($checkin->back_photo) {
                Storage::disk('public')->delete($checkin->back_photo);
            }
        }

        $checkin->update($photos);

        return response()->json([
            'success' => true,
            'message' => 'Photos uploaded successfully',
            'data' => $checkin
        ]);
    }

    /**
     * Delete a weekly check-in
     */
    public function deleteCheckin(Request $request, $id)
    {
        $checkin = WeeklyCheckin::findOrFail($id);

        // Check authorization
        if ($checkin->user_id !== $request->user()->id && $checkin->coach_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Delete photos from storage
        if ($checkin->front_photo) {
            Storage::disk('public')->delete($checkin->front_photo);
        }
        if ($checkin->side_photo) {
            Storage::disk('public')->delete($checkin->side_photo);
        }
        if ($checkin->back_photo) {
            Storage::disk('public')->delete($checkin->back_photo);
        }

        $checkin->delete();

        return response()->json([
            'success' => true,
            'message' => 'Check-in deleted successfully'
        ]);
    }

    /**
     * Get check-in statistics for a client
     */
    public function getClientStats(Request $request, $clientId = null)
    {
        $user = $request->user();
        $targetUserId = $clientId ?? $user->id;

        // Authorization: can only view own stats or coach viewing their client's stats
        if ($targetUserId !== $user->id) {
            // Check if user is a coach and clientId is their client
            $client = User::find($targetUserId);
            if (!$client || $client->coach_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
        }

        $checkins = WeeklyCheckin::where('user_id', $targetUserId)
            ->orderBy('checkin_date')
            ->get();

        // Calculate statistics
        $stats = [
            'total_checkins' => $checkins->count(),
            'submitted_count' => $checkins->where('status', 'submitted')->count() + $checkins->where('status', 'reviewed')->count(),
            'reviewed_count' => $checkins->where('status', 'reviewed')->count(),
            'pending_count' => $checkins->where('status', 'pending')->count(),
            'compliance_rate' => $checkins->avg('compliance_rate') ?? 0,
            'avg_wellness_score' => $checkins->avg('wellness_score') ?? 0,
            'weight_progress' => [
                'starting_weight' => $checkins->first()->current_weight ?? null,
                'current_weight' => $checkins->last()->current_weight ?? null,
                'total_change' => null,
                'unit' => $checkins->last()->weight_unit ?? 'lbs',
            ],
            'recent_checkins' => $checkins->take(-5)->values(),
        ];

        if ($stats['weight_progress']['starting_weight'] && $stats['weight_progress']['current_weight']) {
            $stats['weight_progress']['total_change'] =
                round($stats['weight_progress']['current_weight'] - $stats['weight_progress']['starting_weight'], 2);
        }

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
