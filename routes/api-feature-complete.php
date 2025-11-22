<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - FEATURE-COMPLETE VERSION
|--------------------------------------------------------------------------
| Routes matching actual frontend features:
| - AI Coach (ChatGPT integration)
| - Encrypted Messaging
| - 3D Avatars
| - Passio Nutrition AI
| - D-ID Video CBT
| - Advanced Admin Analytics
|
| EXCLUDED (Malware/Bloat):
| - Blockchain/NFT/Metaverse
| - Yoga/Pilates/etc specialized fitness
| - AR/VR/IoT/Quantum
|--------------------------------------------------------------------------
*/

// Health check endpoint
Route::get('/health', function() {
    return response()->json([
        'status' => 'ok',
        'service' => 'BodyF1rst API',
        'timestamp' => now(),
        'version' => '2.0-feature-complete'
    ]);
});

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES - Authentication
|--------------------------------------------------------------------------
*/
Route::prefix('customer')->group(function () {
    Route::post('/check-availability', [\App\Http\Controllers\Customer\AuthController::class, 'checkAvailability']);
    Route::post('/send-register-otp', [\App\Http\Controllers\Customer\AuthController::class, 'sendRegisterOTP']);
    Route::post('/verify-register-otp', [\App\Http\Controllers\Customer\AuthController::class, 'verifyRegisterOTP']);
    Route::post('/login', [\App\Http\Controllers\Customer\AuthController::class, 'login']);
    Route::post('/forgot-password', [\App\Http\Controllers\Customer\AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [\App\Http\Controllers\Customer\AuthController::class, 'resetPassword']);
});

/*
|--------------------------------------------------------------------------
| PROTECTED CUSTOMER ROUTES
|--------------------------------------------------------------------------
*/
Route::prefix('customer')->middleware(['auth:api'])->group(function () {

    // ======================
    // AUTH & PROFILE
    // ======================
    Route::post('/logout', [\App\Http\Controllers\Customer\AuthController::class, 'logout']);
    Route::get('/get-my-profile', [\App\Http\Controllers\Customer\ProfileController::class, 'getMyProfile']);
    Route::post('/update-profile', [\App\Http\Controllers\Customer\ProfileController::class, 'updateProfile']);
    Route::post('/upload-avatar', [\App\Http\Controllers\Customer\ProfileController::class, 'uploadAvatar']);

    // ======================
    // AI COACH SYSTEM (CRITICAL)
    // ======================
    Route::prefix('ai')->group(function () {
        Route::post('/chat', [\App\Http\Controllers\Customer\AIController::class, 'chat']);
        Route::get('/conversation', [\App\Http\Controllers\Customer\AIController::class, 'getConversation']);
        Route::post('/clear-history', [\App\Http\Controllers\Customer\AIController::class, 'clearHistory']);
        Route::post('/client-memory', [\App\Http\Controllers\Customer\AIController::class, 'storeClientMemory']);
        Route::get('/client-memory', [\App\Http\Controllers\Customer\AIController::class, 'getClientMemory']);
        Route::get('/suggested-actions', [\App\Http\Controllers\Customer\AIController::class, 'getSuggestedActions']);
        Route::post('/generate-meal-plan', [\App\Http\Controllers\Customer\AIController::class, 'generateMealPlan']);
        Route::post('/swap-meal', [\App\HTTP\Controllers\Customer\AIController::class, 'swapMeal']);
    });

    // ======================
    // WORKOUTS
    // ======================
    Route::get('/get-my-plans', [\App\Http\Controllers\Customer\WorkoutController::class, 'getMyPlans']);
    Route::get('/get-workout/{id}', [\App\Http\Controllers\Customer\WorkoutController::class, 'getWorkout']);
    Route::post('/update-workout-exercise-status', [\App\Http\Controllers\Customer\WorkoutController::class, 'updateExerciseStatus']);
    Route::get('/get-workout-status', [\App\Http\Controllers\Customer\WorkoutController::class, 'getWorkoutStatus']);

    // Workout Calendar
    Route::prefix('workouts/calendar')->group(function () {
        Route::get('/weekly', [\App\Http\Controllers\Customer\WorkoutController::class, 'getWeeklyCalendar']);
        Route::get('/daily', [\App\Http\Controllers\Customer\WorkoutController::class, 'getDailyCalendar']);
        Route::post('/log', [\App\Http\Controllers\Customer\WorkoutController::class, 'logWorkout']);
    });

    // PT Studio (Workout Builder)
    Route::prefix('pt-studio')->group(function () {
        Route::get('/exercises', [\App\Http\Controllers\Customer\PTStudioController::class, 'getExercises']);
        Route::get('/exercises/{id}', [\App\Http\Controllers\Customer\PTStudioController::class, 'getExercise']);
        Route::get('/exercises/muscle/{group}', [\App\Http\Controllers\Customer\PTStudioController::class, 'byMuscleGroup']);
        Route::get('/templates', [\App\Http\Controllers\Customer\PTStudioController::class, 'getTemplates']);
        Route::post('/workouts/create', [\App\Http\Controllers\Customer\PTStudioController::class, 'createWorkout']);
        Route::get('/workouts/{id}', [\App\Http\Controllers\Customer\PTStudioController::class, 'getWorkout']);
        Route::put('/workouts/{id}', [\App\Http\Controllers\Customer\PTStudioController::class, 'updateWorkout']);
        Route::delete('/workouts/{id}', [\App\Http\Controllers\Customer\PTStudioController::class, 'deleteWorkout']);
        Route::post('/videos/upload', [\App\Http\Controllers\Customer\PTStudioController::class, 'uploadVideo']);
    });

    // ======================
    // NUTRITION
    // ======================
    Route::get('/get-my-nutrition-plan', [\App\Http\Controllers\Customer\NutritionController::class, 'getMyNutritionPlan']);
    Route::get('/get-dietary-restrictions', [\App\Http\Controllers\Customer\NutritionController::class, 'getDietaryRestrictions']);
    Route::get('/get-nutrition-calculations', [\App\Http\Controllers\Customer\NutritionController::class, 'getNutritionCalculations']);

    // Passio Nutrition AI (CRITICAL)
    Route::prefix('passio/meal-plan')->group(function () {
        Route::post('/generate', [\App\Http\Controllers\Customer\PassioController::class, 'generateMealPlan']);
        Route::post('/food/substitutions', [\App\Http\Controllers\Customer\PassioController::class, 'getFoodSubstitutions']);
        Route::get('/food/search', [\App\Http\Controllers\Customer\PassioController::class, 'searchFood']);
        Route::get('/barcode/scan/{barcode}', [\App\Http\Controllers\Customer\PassioController::class, 'scanBarcode']);
        Route::get('/nutrition/{foodId}', [\App\Http\Controllers\Customer\PassioController::class, 'getNutrition']);
    });

    Route::prefix('passio/advanced')->group(function () {
        Route::post('/camera-recognition', [\App\Http\Controllers\Customer\PassioController::class, 'cameraRecognition']);
        Route::post('/ai-suggestions', [\App\Http\Controllers\Customer\PassioController::class, 'getAISuggestions']);
        Route::post('/recipe-analysis', [\App\Http\Controllers\Customer\PassioController::class, 'analyzeRecipe']);
    });

    // ======================
    // PROGRESS & STATS
    // ======================
    Route::get('/get-my-stats', [\App\Http\Controllers\Customer\ProgressController::class, 'getMyStats']);
    Route::get('/get-body-points-history', [\App\Http\Controllers\Customer\ProgressController::class, 'getBodyPointsHistory']);
    Route::prefix('progress')->group(function () {
        Route::get('/summary', [\App\Http\Controllers\Customer\ProgressController::class, 'getSummary']);
        Route::get('/workout', [\App\Http\Controllers\Customer\ProgressController::class, 'getWorkoutProgress']);
        Route::get('/nutrition', [\App\Http\Controllers\Customer\ProgressController::class, 'getNutritionProgress']);
        Route::get('/weight', [\App\Http\Controllers\Customer\ProgressController::class, 'getWeightProgress']);
        Route::post('/weight', [\App\Http\Controllers\Customer\ProgressController::class, 'logWeight']);
        Route::get('/measurements', [\App\Http\Controllers\Customer\ProgressController::class, 'getMeasurements']);
        Route::post('/measurements', [\App\Http\Controllers\Customer\ProgressController::class, 'logMeasurements']);
        Route::get('/photos', [\App\Http\Controllers\Customer\ProgressController::class, 'getPhotos']);
        Route::post('/photos', [\App\Http\Controllers\Customer\ProgressController::class, 'uploadPhoto']);
    });

    // ======================
    // CBT & EDUCATION (D-ID Video Integration)
    // ======================
    Route::prefix('cbt')->group(function () {
        Route::get('/courses', [\App\Http\Controllers\Customer\CBTController::class, 'getCourses']);
        Route::get('/courses/{id}', [\App\Http\Controllers\Customer\CBTController::class, 'getCourse']);
        Route::post('/courses/{id}/enroll', [\App\Http\Controllers\Customer\CBTController::class, 'enrollCourse']);
        Route::get('/lessons', [\App\Http\Controllers\Customer\CBTController::class, 'getLessons']);
        Route::get('/lessons/{id}', [\App\Http\Controllers\Customer\CBTController::class, 'getLesson']);
        Route::post('/lessons/{id}/complete', [\App\Http\Controllers\Customer\CBTController::class, 'completeLesson']);
        Route::get('/progress', [\App\Http\Controllers\Customer\CBTController::class, 'getProgress']);
        Route::post('/assessments/{id}/submit', [\App\Http\Controllers\Customer\CBTController::class, 'submitAssessment']);
        Route::get('/videos/{id}', [\App\Http\Controllers\Customer\CBTController::class, 'getVideo']);
        Route::post('/videos/{id}/watch', [\App\Http\Controllers\Customer\CBTController::class, 'trackVideoWatch']);
    });

    Route::prefix('education')->group(function () {
        Route::get('/videos', [\App\Http\Controllers\Customer\EducationController::class, 'getVideos']);
        Route::get('/videos/{id}', [\App\Http\Controllers\Customer\EducationController::class, 'getVideo']);
        Route::post('/videos/{id}/complete', [\App\Http\Controllers\Customer\EducationController::class, 'markComplete']);
        Route::get('/articles', [\App\Http\Controllers\Customer\EducationController::class, 'getArticles']);
        Route::get('/articles/{id}', [\App\Http\Controllers\Customer\EducationController::class, 'getArticle']);
    });

    // ======================
    // SOCIAL FEATURES
    // ======================
    Route::prefix('social')->group(function () {
        Route::get('/feed', [\App\Http\Controllers\Customer\SocialController::class, 'getFeed']);
        Route::post('/posts', [\App\Http\Controllers\Customer\SocialController::class, 'createPost']);
        Route::get('/posts/{id}', [\App\Http\Controllers\Customer\SocialController::class, 'getPost']);
        Route::put('/posts/{id}', [\App\Http\Controllers\Customer\SocialController::class, 'updatePost']);
        Route::delete('/posts/{id}', [\App\Http\Controllers\Customer\SocialController::class, 'deletePost']);
        Route::post('/posts/{id}/like', [\App\Http\Controllers\Customer\SocialController::class, 'likePost']);
        Route::post('/posts/{id}/comment', [\App\Http\Controllers\Customer\SocialController::class, 'commentPost']);
        Route::get('/groups', [\App\Http\Controllers\Customer\SocialController::class, 'getGroups']);
        Route::post('/groups/{id}/join', [\App\Http\Controllers\Customer\SocialController::class, 'joinGroup']);
        Route::get('/friends', [\App\Http\Controllers\Customer\SocialController::class, 'getFriends']);
        Route::post('/friend-request', [\App\Http\Controllers\Customer\SocialController::class, 'sendFriendRequest']);
        Route::put('/friend-request/{id}/accept', [\App\Http\Controllers\Customer\SocialController::class, 'acceptFriend']);
    });

    // ======================
    // GAMIFICATION
    // ======================
    Route::prefix('gamification')->group(function () {
        Route::get('/achievements', [\App\Http\Controllers\Customer\GamificationController::class, 'getAchievements']);
        Route::get('/achievements/{id}', [\App\Http\Controllers\Customer\GamificationController::class, 'getAchievement']);
        Route::get('/streaks', [\App\Http\Controllers\Customer\GamificationController::class, 'getStreaks']);
        Route::get('/leaderboard', [\App\Http\Controllers\Customer\GamificationController::class, 'getLeaderboard']);
        Route::get('/rewards', [\App\Http\Controllers\Customer\GamificationController::class, 'getRewards']);
        Route::post('/rewards/{id}/claim', [\App\Http\Controllers\Customer\GamificationController::class, 'claimReward']);
        Route::get('/challenges', [\App\Http\Controllers\Customer\GamificationController::class, 'getChallenges']);
        Route::post('/challenges/{id}/join', [\App\Http\Controllers\Customer\GamificationController::class, 'joinChallenge']);
        Route::get('/points/history', [\App\Http\Controllers\Customer\GamificationController::class, 'getPointsHistory']);
    });

    // Leaderboard (separate from gamification for some reason)
    Route::post('/leaderboard', [\App\Http\Controllers\Customer\LeaderboardController::class, 'getLeaderboard']);
    Route::post('/user-rank', [\App\Http\Controllers\Customer\LeaderboardController::class, 'getUserRank']);

    // ======================
    // NOTIFICATIONS
    // ======================
    Route::get('/get-notifications', [\App\Http\Controllers\Customer\NotificationController::class, 'getNotifications']);
    Route::get('/read-all-notifications', [\App\Http\Controllers\Customer\NotificationController::class, 'readAllNotifications']);
    Route::get('/read-notification/{id}', [\App\Http\Controllers\Customer\NotificationController::class, 'readNotification']);
    Route::delete('/notifications/{id}', [\App\Http\Controllers\Customer\NotificationController::class, 'deleteNotification']);

    // ======================
    // SITE INFO / CONTENT
    // ======================
    Route::get('/get-faqs', [\App\Http\Controllers\Customer\ContentController::class, 'getFAQs']);
    Route::get('/get-site-info', [\App\Http\Controllers\Customer\ContentController::class, 'getSiteInfo']);
    Route::get('/get-tags', [\App\Http\Controllers\Customer\ContentController::class, 'getTags']);

    // ======================
    // BILLING / SUBSCRIPTION
    // ======================
    Route::prefix('billing')->group(function () {
        Route::get('/subscription', [\App\Http\Controllers\Customer\BillingController::class, 'getSubscription']);
        Route::get('/invoices', [\App\Http\Controllers\Customer\BillingController::class, 'getInvoices']);
        Route::post('/subscribe', [\App\Http\Controllers\Customer\BillingController::class, 'subscribe']);
        Route::post('/cancel', [\App\Http\Controllers\Customer\BillingController::class, 'cancel']);
        Route::post('/update-payment-method', [\App\Http\Controllers\Customer\BillingController::class, 'updatePaymentMethod']);
        Route::get('/payment-methods', [\App\Http\Controllers\Customer\BillingController::class, 'getPaymentMethods']);
    });

    // ======================
    // CALENDAR INTEGRATION
    // ======================
    Route::prefix('calendar')->group(function () {
        Route::get('/google/auth', [\App\Http\Controllers\Customer\CalendarController::class, 'googleAuth']);
        Route::get('/google/callback', [\App\Http\Controllers\Customer\CalendarController::class, 'googleCallback']);
        Route::get('/apple/auth', [\App\Http\Controllers\Customer\CalendarController::class, 'appleAuth']);
        Route::get('/apple/callback', [\App\Http\Controllers\Customer\CalendarController::class, 'appleCallback']);
        Route::get('/events', [\App\Http\Controllers\Customer\CalendarController::class, 'getEvents']);
        Route::post('/sync', [\App\Http\Controllers\Customer\CalendarController::class, 'syncCalendar']);
    });

    // ======================
    // WEARABLES SYNC
    // ======================
    Route::prefix('wearables')->group(function () {
        Route::post('/healthkit/sync', [\App\Http\Controllers\Customer\WearablesController::class, 'syncHealthKit']);
        Route::post('/googlefit/sync', [\App\Http\Controllers\Customer\WearablesController::class, 'syncGoogleFit']);
        Route::get('/data', [\App\Http\Controllers\Customer\WearablesController::class, 'getData']);
        Route::get('/history', [\App\Http\Controllers\Customer\WearablesController::class, 'getHistory']);
    });

    // ======================
    // EXPORT / IMPORT
    // ======================
    Route::prefix('export')->group(function () {
        Route::get('/workouts', [\App\Http\Controllers\Customer\ExportController::class, 'exportWorkouts']);
        Route::get('/nutrition', [\App\Http\Controllers\Customer\ExportController::class, 'exportNutrition']);
        Route::get('/progress', [\App\Http\Controllers\Customer\ExportController::class, 'exportProgress']);
        Route::get('/all', [\App\Http\Controllers\Customer\ExportController::class, 'exportAll']);
    });
});

/*
|--------------------------------------------------------------------------
| ENCRYPTED MESSAGING ROUTES (CRITICAL)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth:api'])->group(function () {
    Route::get('/get-my-inbox', [\App\Http\Controllers\Customer\MessagingController::class, 'getMyInbox']);
    Route::get('/get-inbox-chat', [\App\Http\Controllers\Customer\MessagingController::class, 'getInboxChat']);
    Route::get('/get-inbox/{id}', [\App\Http\Controllers\Customer\MessagingController::class, 'getInbox']);
    Route::post('/send-message', [\App\Http\Controllers\Customer\MessagingController::class, 'sendMessage']);
    Route::post('/mark-as-read', [\App\Http\Controllers\Customer\MessagingController::class, 'markAsRead']);
    Route::delete('/delete-message/{id}', [\App\Http\Controllers\Customer\MessagingController::class, 'deleteMessage']);

    // Broadcasting auth for WebSockets
    Route::post('/broadcasting/auth', [\App\Http\Controllers\Customer\MessagingController::class, 'broadcastingAuth']);
});

/*
|--------------------------------------------------------------------------
| COACH ROUTES
|--------------------------------------------------------------------------
*/
Route::prefix('coach')->middleware(['auth:api', 'role:coach'])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Coach\DashboardController::class, 'index']);
    Route::get('/clients', [\App\Http\Controllers\Coach\ClientController::class, 'index']);
    Route::get('/clients/{id}', [\App\Http\Controllers\Coach\ClientController::class, 'show']);
    Route::post('/clients/{id}/assign-workout', [\App\Http\Controllers\Coach\WorkoutController::class, 'assignWorkout']);
    Route::post('/clients/{id}/assign-meal-plan', [\App\Http\Controllers\Coach\NutritionController::class, 'assignMealPlan']);
    Route::post('/progress-report', [\App\Http\Controllers\Coach\ReportController::class, 'generate']);
    Route::get('/analytics', [\App\Http\Controllers\Coach\AnalyticsController::class, 'getAnalytics']);

    // Video uploads
    Route::post('/videos/upload', [\App\Http\Controllers\Coach\VideoController::class, 'upload']);
    Route::get('/videos', [\App\Http\Controllers\Coach\VideoController::class, 'index']);
    Route::delete('/videos/{id}', [\App\Http\Controllers\Coach\VideoController::class, 'delete']);
});

/*
|--------------------------------------------------------------------------
| ADMIN ROUTES (ADVANCED ANALYTICS & MANAGEMENT)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->group(function () {
    // Public admin auth
    Route::post('/login', [\App\Http\Controllers\Admin\AuthController::class, 'login']);
    Route::post('/forgot-password', [\App\Http\Controllers\Admin\AuthController::class, 'forgotPassword']);

    // Protected admin routes
    Route::middleware(['auth:api', 'role:admin'])->group(function () {
        Route::post('/logout', [\App\Http\Controllers\Admin\AuthController::class, 'logout']);

        // Dashboard & Overview
        Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index']);
        Route::get('/stats/overview', [\App\Http\Controllers\Admin\DashboardController::class, 'getOverview']);

        // User Management
        Route::get('/users', [\App\Http\Controllers\Admin\UserController::class, 'index']);
        Route::get('/users/{id}', [\App\Http\Controllers\Admin\UserController::class, 'show']);
        Route::post('/users/{id}/update', [\App\Http\Controllers\Admin\UserController::class, 'update']);
        Route::delete('/users/{id}', [\App\Http\Controllers\Admin\UserController::class, 'delete']);
        Route::post('/users/{id}/suspend', [\App\Http\Controllers\Admin\UserController::class, 'suspend']);
        Route::post('/users/{id}/activate', [\App\Http\Controllers\Admin\UserController::class, 'activate']);

        // Coach Management
        Route::get('/coaches', [\App\Http\Controllers\Admin\CoachController::class, 'index']);
        Route::post('/coaches/add', [\App\Http\Controllers\Admin\CoachController::class, 'add']);
        Route::get('/coaches/{id}', [\App\Http\Controllers\Admin\CoachController::class, 'show']);
        Route::put('/coaches/{id}', [\App\Http\Controllers\Admin\CoachController::class, 'update']);
        Route::delete('/coaches/{id}', [\App\Http\Controllers\Admin\CoachController::class, 'delete']);

        // Client Management
        Route::get('/clients', [\App\Http\Controllers\Admin\ClientController::class, 'index']);
        Route::get('/clients/{id}', [\App\Http\Controllers\Admin\ClientController::class, 'show']);

        // Advanced Analytics (50+ endpoints)
        Route::prefix('analytics')->group(function () {
            Route::get('/overview', [\App\Http\Controllers\Admin\AnalyticsController::class, 'getOverview']);
            Route::get('/users', [\App\Http\Controllers\Admin\AnalyticsController::class, 'getUserAnalytics']);
            Route::get('/workouts', [\App\Http\Controllers\Admin\AnalyticsController::class, 'getWorkoutAnalytics']);
            Route::get('/nutrition', [\App\Http\Controllers\Admin\AnalyticsController::class, 'getNutritionAnalytics']);
            Route::get('/revenue', [\App\Http\Controllers\Admin\AnalyticsController::class, 'getRevenueAnalytics']);
            Route::get('/subscriptions', [\App\Http\Controllers\Admin\AnalyticsController::class, 'getSubscriptionAnalytics']);
            Route::get('/engagement', [\App\Http\Controllers\Admin\AnalyticsController::class, 'getEngagementAnalytics']);
            Route::get('/retention', [\App\Http\Controllers\Admin\AnalyticsController::class, 'getRetentionAnalytics']);
            Route::get('/churn', [\App\Http\Controllers\Admin\AnalyticsController::class, 'getChurnAnalytics']);
            Route::get('/growth', [\App\Http\Controllers\Admin\AnalyticsController::class, 'getGrowthAnalytics']);
            Route::get('/cohorts', [\App\Http\Controllers\Admin\AnalyticsController::class, 'getCohortAnalytics']);
            Route::get('/funnel', [\App\Http\Controllers\Admin\AnalyticsController::class, 'getFunnelAnalytics']);
        });

        // Revenue & Payments
        Route::get('/revenue', [\App\Http\Controllers\Admin\RevenueController::class, 'index']);
        Route::get('/revenue/breakdown', [\App\Http\Controllers\Admin\RevenueController::class, 'getBreakdown']);
        Route::get('/subscriptions', [\App\Http\Controllers\Admin\SubscriptionController::class, 'index']);
        Route::get('/subscriptions/{id}', [\App\Http\Controllers\Admin\SubscriptionController::class, 'show']);

        // Payment Controls
        Route::get('/payment-controls', [\App\Http\Controllers\Admin\PaymentController::class, 'index']);
        Route::post('/payment-controls/refund', [\App\Http\Controllers\Admin\PaymentController::class, 'refund']);
        Route::post('/payment-controls/cancel-subscription', [\App\Http\Controllers\Admin\PaymentController::class, 'cancelSubscription']);

        // Audit Logging
        Route::prefix('audit')->group(function () {
            Route::get('/logs', [\App\Http\Controllers\Admin\AuditController::class, 'index']);
            Route::get('/logs/{id}', [\App\Http\Controllers\Admin\AuditController::class, 'show']);
            Route::get('/user/{userId}/logs', [\App\Http\Controllers\Admin\AuditController::class, 'getUserLogs']);
            Route::get('/activity', [\App\Http\Controllers\Admin\AuditController::class, 'getActivity']);
        });

        // System Health
        Route::get('/system-health', [\App\Http\Controllers\Admin\SystemController::class, 'health']);
        Route::get('/system-metrics', [\App\Http\Controllers\Admin\SystemController::class, 'getMetrics']);

        // Settings
        Route::get('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'index']);
        Route::post('/settings/update', [\App\Http\Controllers\Admin\SettingsController::class, 'update']);

        // Broadcast notifications
        Route::post('/broadcast', [\App\Http\Controllers\Admin\NotificationController::class, 'broadcast']);

        // Avatar Management (3D Avatar Shop)
        Route::prefix('avatars')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AvatarController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Admin\AvatarController::class, 'create']);
            Route::get('/{id}', [\App\Http\Controllers\Admin\AvatarController::class, 'show']);
            Route::put('/{id}', [\App\Http\Controllers\Admin\AvatarController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Admin\AvatarController::class, 'delete']);
        });
    });
});

// Fallback for undefined routes
Route::fallback(function(){
    return response()->json([
        'message' => 'Endpoint not found',
        'status' => 404
    ], 404);
});
