<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\DietaryRestriction;
use App\Models\EquipmentPreference;
use App\Models\TrainingPreference;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    /**
     * GET /api/customer/profile
     * Retrieve the authenticated user's profile information
     */
    public function getProfile(Request $request)
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'status' => 401,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'status' => 404,
                    'message' => 'User not found',
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'message' => 'Profile retrieved successfully',
                'data' => $user,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while retrieving profile',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT /api/customer/profile
     * Update the authenticated user's profile information
     */
    public function updateProfile(Request $request)
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'status' => 401,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'first_name' => 'sometimes|string|max:255',
                'last_name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $userId,
                'phone' => 'sometimes|string|max:20',
                'gender' => 'sometimes|in:male,female,other',
                'dob' => 'sometimes|date|before:today',
                'weight' => 'sometimes|numeric|min:0',
                'height' => 'sometimes|numeric|min:0',
                'activity_level' => 'sometimes|in:sedentary,lightly_active,moderately_active,very_active,extremely_active',
                'goal' => 'sometimes|string|max:255',
                'profile_image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 422,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'status' => 404,
                    'message' => 'User not found',
                ], 404);
            }

            $updateData = $request->only([
                'first_name',
                'last_name',
                'email',
                'phone',
                'gender',
                'dob',
                'weight',
                'height',
                'activity_level',
                'goal',
            ]);

            // Handle profile image upload
            if ($request->hasFile('profile_image')) {
                $file = $request->file('profile_image');
                $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('user_profiles', $filename, 'public');
                $updateData['profile_image'] = $filename;
            }

            $user->update($updateData);

            return response()->json([
                'status' => 200,
                'message' => 'Profile updated successfully',
                'data' => $user,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while updating profile',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/customer/get-dietary-restrictions
     * Retrieve all available dietary restrictions and user's selections
     */
    public function getDietaryRestrictions(Request $request)
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'status' => 401,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'status' => 404,
                    'message' => 'User not found',
                ], 404);
            }

            $availableRestrictions = DietaryRestriction::all();
            $userRestrictions = $user->dietary_restrictions ?? [];

            return response()->json([
                'status' => 200,
                'message' => 'Dietary restrictions retrieved successfully',
                'data' => [
                    'available_restrictions' => $availableRestrictions,
                    'user_restrictions' => $userRestrictions,
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while retrieving dietary restrictions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/customer/update-dietary-restrictions
     * Update user's dietary restriction preferences
     */
    public function updateDietaryRestrictions(Request $request)
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'status' => 401,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'dietary_restrictions' => 'required|array',
                'dietary_restrictions.*' => 'integer|exists:dietary_restrictions,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 422,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'status' => 404,
                    'message' => 'User not found',
                ], 404);
            }

            $restrictionIds = $request->input('dietary_restrictions');
            $restrictions = DietaryRestriction::whereIn('id', $restrictionIds)->get()->toArray();

            $user->update([
                'dietary_restrictions' => $restrictions,
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Dietary restrictions updated successfully',
                'data' => $user->dietary_restrictions,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while updating dietary restrictions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/customer/get-training-preferences
     * Retrieve all available training preferences and user's selections
     */
    public function getTrainingPreferences(Request $request)
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'status' => 401,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'status' => 404,
                    'message' => 'User not found',
                ], 404);
            }

            $availablePreferences = TrainingPreference::all();
            $userPreferences = $user->training_preferences ?? [];

            return response()->json([
                'status' => 200,
                'message' => 'Training preferences retrieved successfully',
                'data' => [
                    'available_preferences' => $availablePreferences,
                    'user_preferences' => $userPreferences,
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while retrieving training preferences',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/customer/update-training-preferences
     * Update user's training preference selections
     */
    public function updateTrainingPreferences(Request $request)
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'status' => 401,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'training_preferences' => 'required|array',
                'training_preferences.*' => 'integer|exists:training_preferences,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 422,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'status' => 404,
                    'message' => 'User not found',
                ], 404);
            }

            $preferenceIds = $request->input('training_preferences');
            $preferences = TrainingPreference::whereIn('id', $preferenceIds)->get()->toArray();

            $user->update([
                'training_preferences' => $preferences,
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Training preferences updated successfully',
                'data' => $user->training_preferences,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while updating training preferences',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/customer/get-equipment-preferences
     * Retrieve all available equipment preferences and user's selections
     */
    public function getEquipmentPreferences(Request $request)
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'status' => 401,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'status' => 404,
                    'message' => 'User not found',
                ], 404);
            }

            $availablePreferences = EquipmentPreference::all();
            $userPreferences = $user->equipment_preferences ?? [];

            return response()->json([
                'status' => 200,
                'message' => 'Equipment preferences retrieved successfully',
                'data' => [
                    'available_preferences' => $availablePreferences,
                    'user_preferences' => $userPreferences,
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while retrieving equipment preferences',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/customer/update-equipment-preferences
     * Update user's equipment preference selections
     */
    public function updateEquipmentPreferences(Request $request)
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'status' => 401,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'equipment_preferences' => 'required|array',
                'equipment_preferences.*' => 'integer|exists:equipment_preferences,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 422,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'status' => 404,
                    'message' => 'User not found',
                ], 404);
            }

            $preferenceIds = $request->input('equipment_preferences');
            $preferences = EquipmentPreference::whereIn('id', $preferenceIds)->get()->toArray();

            $user->update([
                'equipment_preferences' => $preferences,
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Equipment preferences updated successfully',
                'data' => $user->equipment_preferences,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while updating equipment preferences',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/customer/get-notification-preferences
     * Retrieve user's notification preference settings
     */
    public function getNotificationPreferences(Request $request)
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'status' => 401,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'status' => 404,
                    'message' => 'User not found',
                ], 404);
            }

            // Initialize default notification preferences if not set
            $preferences = $user->preferences ?? [];

            if (!isset($preferences['notifications'])) {
                $preferences['notifications'] = [
                    'workouts' => true,
                    'meals' => true,
                    'messages' => true,
                    'reminders' => true,
                    'progress' => true,
                    'social' => true,
                    'email' => true,
                    'push' => true,
                    'sms' => false,
                ];
            }

            return response()->json([
                'status' => 200,
                'message' => 'Notification preferences retrieved successfully',
                'data' => $preferences['notifications'],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while retrieving notification preferences',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/customer/update-notification-preferences
     * Update user's notification preference settings
     */
    public function updateNotificationPreferences(Request $request)
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'status' => 401,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'notifications' => 'required|array',
                'notifications.workouts' => 'boolean',
                'notifications.meals' => 'boolean',
                'notifications.messages' => 'boolean',
                'notifications.reminders' => 'boolean',
                'notifications.progress' => 'boolean',
                'notifications.social' => 'boolean',
                'notifications.email' => 'boolean',
                'notifications.push' => 'boolean',
                'notifications.sms' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 422,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'status' => 404,
                    'message' => 'User not found',
                ], 404);
            }

            $preferences = $user->preferences ?? [];
            $preferences['notifications'] = $request->input('notifications');

            $user->update([
                'preferences' => $preferences,
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Notification preferences updated successfully',
                'data' => $preferences['notifications'],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while updating notification preferences',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/customer/get-privacy-settings
     * Retrieve user's privacy settings
     */
    public function getPrivacySettings(Request $request)
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'status' => 401,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'status' => 404,
                    'message' => 'User not found',
                ], 404);
            }

            // Initialize default privacy settings if not set
            $preferences = $user->preferences ?? [];

            if (!isset($preferences['privacy'])) {
                $preferences['privacy'] = [
                    'profile_visible' => true,
                    'show_progress' => true,
                    'show_workouts' => true,
                    'show_nutrition' => true,
                    'allow_messages' => true,
                    'allow_friend_requests' => true,
                ];
            }

            return response()->json([
                'status' => 200,
                'message' => 'Privacy settings retrieved successfully',
                'data' => $preferences['privacy'],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while retrieving privacy settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/customer/update-privacy-settings
     * Update user's privacy settings
     */
    public function updatePrivacySettings(Request $request)
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'status' => 401,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'privacy' => 'required|array',
                'privacy.profile_visible' => 'boolean',
                'privacy.show_progress' => 'boolean',
                'privacy.show_workouts' => 'boolean',
                'privacy.show_nutrition' => 'boolean',
                'privacy.allow_messages' => 'boolean',
                'privacy.allow_friend_requests' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 422,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'status' => 404,
                    'message' => 'User not found',
                ], 404);
            }

            $preferences = $user->preferences ?? [];
            $preferences['privacy'] = $request->input('privacy');

            $user->update([
                'preferences' => $preferences,
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Privacy settings updated successfully',
                'data' => $preferences['privacy'],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while updating privacy settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/customer/export-data
     * Compile and export user's data as JSON
     */
    public function exportData(Request $request)
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'status' => 401,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'status' => 404,
                    'message' => 'User not found',
                ], 404);
            }

            // Compile user data
            $exportData = [
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'gender' => $user->gender,
                    'dob' => $user->dob,
                    'age' => $user->age,
                    'weight' => $user->weight,
                    'height' => $user->height,
                    'activity_level' => $user->activity_level,
                    'goal' => $user->goal,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
                'preferences' => [
                    'dietary_restrictions' => $user->dietary_restrictions,
                    'training_preferences' => $user->training_preferences,
                    'equipment_preferences' => $user->equipment_preferences,
                    'notifications' => $user->preferences['notifications'] ?? [],
                    'privacy' => $user->preferences['privacy'] ?? [],
                ],
                'health_metrics' => [
                    'protein' => $user->protein,
                    'carb' => $user->carb,
                    'fat' => $user->fat,
                    'calorie' => $user->calorie,
                    'bmr' => $user->bmr,
                    'tdee' => $user->tdee,
                ],
            ];

            // Generate filename with timestamp
            $filename = 'user_data_' . $user->id . '_' . now()->format('Y_m_d_H_i_s') . '.json';
            $filepath = 'exports/' . $filename;

            // Store the JSON file
            Storage::disk('public')->put($filepath, json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // Generate download URL
            $downloadUrl = url('/') . '/storage/' . $filepath;

            return response()->json([
                'status' => 200,
                'message' => 'Data exported successfully',
                'data' => [
                    'download_url' => $downloadUrl,
                    'filename' => $filename,
                    'exported_at' => now()->toIso8601String(),
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while exporting data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/customer/account
     * Soft delete user account and cascade relationships
     */
    public function deleteAccount(Request $request)
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'status' => 401,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'password' => 'required|string',
                'confirmation' => 'required|string|in:delete-my-account',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 422,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'status' => 404,
                    'message' => 'User not found',
                ], 404);
            }

            // Verify password
            if (!\Illuminate\Support\Facades\Hash::check($request->input('password'), $user->password)) {
                return response()->json([
                    'status' => 422,
                    'message' => 'Invalid password provided',
                ], 422);
            }

            // Soft delete the user (SoftDeletes trait will handle this)
            $user->delete();

            // Note: Cascade relationships should be defined in migrations
            // and Laravel will handle them automatically based on foreign key constraints

            return response()->json([
                'status' => 200,
                'message' => 'Account deleted successfully. Your data will be permanently removed after 30 days.',
                'data' => [
                    'deleted_at' => now()->toIso8601String(),
                    'user_id' => $user->id,
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while deleting account',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
