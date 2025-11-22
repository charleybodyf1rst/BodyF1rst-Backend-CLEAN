<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - CLEAN VERSION WITHOUT MALWARE
|--------------------------------------------------------------------------
*/

// Health check endpoint
Route::get('/health', function() {
    return response()->json([
        'status' => 'ok',
        'service' => 'BodyF1rst API',
        'timestamp' => now(),
        'version' => '1.0'
    ]);
});

// Public Authentication Routes
Route::post('/register', [\App\Http\Controllers\Customer\AuthController::class, 'register']);
Route::post('/login', [\App\Http\Controllers\Customer\AuthController::class, 'login']);
Route::post('/forgot-password', [\App\Http\Controllers\Customer\AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [\App\Http\Controllers\Customer\AuthController::class, 'resetPassword']);

// Protected Customer Routes
Route::middleware(['auth:api'])->group(function () {
    // User Profile
    Route::get('/user', [\App\Http\Controllers\Customer\AuthController::class, 'getMyProfile']);
    Route::post('/user/update', [\App\Http\Controllers\Customer\AuthController::class, 'updateProfile']);
    Route::post('/logout', [\App\Http\Controllers\Customer\AuthController::class, 'logout']);

    // Workouts
    Route::get('/workouts', [\App\Http\Controllers\Customer\WorkoutController::class, 'index']);
    Route::get('/workouts/{id}', [\App\Http\Controllers\Customer\WorkoutController::class, 'show']);
    Route::post('/workouts/complete', [\App\Http\Controllers\Customer\WorkoutController::class, 'complete']);
    Route::get('/workout-plans', [\App\Http\Controllers\Customer\WorkoutController::class, 'getPlans']);
    Route::get('/exercises', [\App\Http\Controllers\Customer\WorkoutController::class, 'getExercises']);

    // Nutrition
    Route::get('/nutrition/plans', [\App\Http\Controllers\Customer\NutritionController::class, 'getPlans']);
    Route::get('/nutrition/meals', [\App\Http\Controllers\Customer\NutritionController::class, 'getMeals']);
    Route::post('/nutrition/log', [\App\Http\Controllers\Customer\NutritionController::class, 'logMeal']);
    Route::get('/foods/search', [\App\Http\Controllers\Customer\NutritionController::class, 'searchFoods']);

    // CBT & Education
    Route::get('/cbt/courses', [\App\Http\Controllers\Customer\CBTController::class, 'getCourses']);
    Route::get('/cbt/lessons', [\App\Http\Controllers\Customer\CBTController::class, 'getLessons']);
    Route::post('/cbt/complete', [\App\Http\Controllers\Customer\CBTController::class, 'completeLesson']);
    Route::get('/education/videos', [\App\Http\Controllers\Customer\EducationController::class, 'getVideos']);

    // Progress & Analytics
    Route::get('/progress/summary', [\App\Http\Controllers\Customer\ProgressController::class, 'getSummary']);
    Route::get('/progress/charts', [\App\Http\Controllers\Customer\ProgressController::class, 'getCharts']);
    Route::get('/analytics/overview', [\App\Http\Controllers\Customer\AnalyticsController::class, 'getOverview']);

    // Gamification
    Route::get('/achievements', [\App\Http\Controllers\Customer\AchievementController::class, 'index']);
    Route::get('/leaderboard', [\App\Http\Controllers\Customer\LeaderboardController::class, 'index']);
    Route::get('/rewards', [\App\Http\Controllers\Customer\RewardController::class, 'index']);
    Route::post('/rewards/claim', [\App\Http\Controllers\Customer\RewardController::class, 'claim']);

    // Notifications
    Route::get('/notifications', [\App\Http\Controllers\Customer\NotificationController::class, 'index']);
    Route::post('/notifications/mark-read', [\App\Http\Controllers\Customer\NotificationController::class, 'markAsRead']);

    // Library
    Route::get('/library/exercises', [\App\Http\Controllers\Customer\LibraryController::class, 'getExercises']);
    Route::get('/library/recipes', [\App\Http\Controllers\Customer\LibraryController::class, 'getRecipes']);

    // Billing
    Route::get('/billing/subscription', [\App\Http\Controllers\Customer\BillingController::class, 'getSubscription']);
    Route::get('/billing/invoices', [\App\Http\Controllers\Customer\BillingController::class, 'getInvoices']);
    Route::post('/billing/subscribe', [\App\Http\Controllers\Customer\BillingController::class, 'subscribe']);
    Route::post('/billing/cancel', [\App\Http\Controllers\Customer\BillingController::class, 'cancel']);
});

// Coach Routes
Route::prefix('coach')->middleware(['auth:api', 'role:coach'])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Coach\DashboardController::class, 'index']);
    Route::get('/clients', [\App\Http\Controllers\Coach\ClientController::class, 'index']);
    Route::get('/clients/{id}', [\App\Http\Controllers\Coach\ClientController::class, 'show']);
    Route::post('/progress-report', [\App\Http\Controllers\Coach\ReportController::class, 'generate']);
    Route::post('/assign-workout', [\App\Http\Controllers\Coach\WorkoutController::class, 'assign']);
    Route::post('/assign-meal-plan', [\App\Http\Controllers\Coach\NutritionController::class, 'assign']);
});

// Admin Routes
Route::prefix('admin')->group(function () {
    // Admin Auth (no middleware)
    Route::post('/login', [\App\Http\Controllers\Admin\AuthController::class, 'login']);
    Route::post('/forgot-password', [\App\Http\Controllers\Admin\AuthController::class, 'forgotPassword']);

    // Protected Admin Routes
    Route::middleware(['auth:api', 'role:admin'])->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index']);
        Route::get('/users', [\App\Http\Controllers\Admin\UserController::class, 'index']);
        Route::get('/users/{id}', [\App\Http\Controllers\Admin\UserController::class, 'show']);
        Route::post('/users/{id}/update', [\App\Http\Controllers\Admin\UserController::class, 'update']);
        Route::delete('/users/{id}', [\App\Http\Controllers\Admin\UserController::class, 'delete']);

        Route::get('/coaches', [\App\Http\Controllers\Admin\CoachController::class, 'index']);
        Route::post('/coaches/add', [\App\Http\Controllers\Admin\CoachController::class, 'add']);
        Route::get('/clients', [\App\Http\Controllers\Admin\ClientController::class, 'index']);

        Route::get('/analytics', [\App\Http\Controllers\Admin\AnalyticsController::class, 'index']);
        Route::get('/revenue', [\App\Http\Controllers\Admin\RevenueController::class, 'index']);
        Route::get('/subscriptions', [\App\Http\Controllers\Admin\SubscriptionController::class, 'index']);
        Route::get('/payment-controls', [\App\Http\Controllers\Admin\PaymentController::class, 'index']);

        Route::get('/system-health', [\App\Http\Controllers\Admin\SystemController::class, 'health']);
        Route::get('/audit-logs', [\App\Http\Controllers\Admin\AuditController::class, 'index']);
        Route::get('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'index']);
        Route::post('/settings/update', [\App\Http\Controllers\Admin\SettingsController::class, 'update']);

        Route::post('/broadcast', [\App\Http\Controllers\Admin\NotificationController::class, 'broadcast']);
    });
});

// Fallback for undefined routes
Route::fallback(function(){
    return response()->json([
        'message' => 'Endpoint not found',
        'status' => 404
    ], 404);
});