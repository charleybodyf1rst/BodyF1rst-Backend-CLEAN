<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ChallengeController;
use App\Http\Controllers\Admin\CoachController;
use App\Http\Controllers\Admin\ExerciseController;
use App\Http\Controllers\Admin\IntroVideoController;
use App\Http\Controllers\Admin\MealPlanController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\NutritionController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VideoController;
use App\Http\Controllers\Admin\WorkoutController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\Customer\DashboardController;
use App\Http\Controllers\Customer\NotificationController as CustomerNotificationController;
use App\Http\Controllers\Customer\WorkoutController as CustomerWorkoutController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// SECURITY FIX: Removed malware endpoint '/get-logs' - was public backdoor for exfiltrating activity logs
// If needed in future, must be inside auth:admin middleware with proper authorization

Route::group(['namespace' => 'Admin', 'prefix' => "admin", "as" => "admin."], function () {
    Route::post('/login', [\App\Http\Controllers\Admin\AuthController::class, "login"])->name('login');
    Route::post('/verify-login-otp', [\App\Http\Controllers\Admin\AuthController::class, "verifyLoginOtp"])->name('verifyLoginOtp');
    Route::post('/send-forgot-otp',[\App\Http\Controllers\Admin\AuthController::class,'sendForgotOtp'])->name('sendForgotOtp');
    Route::post('/forgot-password',[\App\Http\Controllers\Admin\AuthController::class,'forgotPassword'])->name('forgotPassword');
    Route::post('/send-reset-password-link', [\App\Http\Controllers\Admin\AuthController::class, "sendResetPasswordLink"])->name("sendResetPasswordLink.api");

    Route::group(["middleware" => ["role",'token_expiration']], function () {
        //Auth
        Route::get('/logout', [\App\Http\Controllers\Admin\AuthController::class, "logout"])->name("logout.api");
        Route::get('/get-my-profile', [\App\Http\Controllers\Admin\AuthController::class, "getMyProfile"])->name("getMyProfile.api");
        Route::post('/update-profile', [\App\Http\Controllers\Admin\AuthController::class, "updateProfile"])->name("updateProfile.api");
        Route::post('/change-password', [\App\Http\Controllers\Admin\AuthController::class, "changePassword"])->name("changePassword.api");
        Route::group(['middleware' => ['auth:admin']],function(){
            // 🏗️ Enhanced Workout Builder Endpoints
            Route::prefix('workout-builder')->group(function() {
                // Video Library Management
                Route::get('/videos', [\App\Http\Controllers\Admin\WorkoutController::class, 'getVideoLibrary'])->name('getVideoLibrary.api');
                Route::get('/videos/categories', [\App\Http\Controllers\Admin\WorkoutController::class, 'getVideoCategories'])->name('getVideoCategories.api');
                Route::post('/videos/search', [\App\Http\Controllers\Admin\WorkoutController::class, 'searchVideos'])->name('searchVideos.api');
                Route::post('/videos/transcribe/{videoId}', [\App\Http\Controllers\Admin\WorkoutController::class, 'transcribeVideo'])->name('transcribeVideo.api');
                
                // Template Management
                Route::get('/templates', [\App\Http\Controllers\Admin\WorkoutController::class, 'getTemplates'])->name('getTemplates.api');
                Route::post('/templates', [\App\Http\Controllers\Admin\WorkoutController::class, 'saveTemplate'])->name('saveTemplate.api');
                Route::put('/templates/{id}', [\App\Http\Controllers\Admin\WorkoutController::class, 'updateTemplate'])->name('updateTemplate.api');
                Route::delete('/templates/{id}', [\App\Http\Controllers\Admin\WorkoutController::class, 'deleteTemplate'])->name('deleteTemplate.api');
                
                // AMRAP/EMOM Builders
                Route::post('/amrap/create', [\App\Http\Controllers\Admin\WorkoutController::class, 'createAMRAP'])->name('createAMRAP.api');
                Route::post('/emom/create', [\App\Http\Controllers\Admin\WorkoutController::class, 'createEMOM'])->name('createEMOM.api');
                Route::post('/rft/create', [\App\Http\Controllers\Admin\WorkoutController::class, 'createRFT'])->name('createRFT.api');
                
                // Weekly Plan Builder
                Route::get('/weekly-plans', [\App\Http\Controllers\Admin\WorkoutController::class, 'getWeeklyPlans'])->name('getWeeklyPlans.api');
                Route::post('/weekly-plans', [\App\Http\Controllers\Admin\WorkoutController::class, 'createWeeklyPlan'])->name('createWeeklyPlan.api');
                Route::put('/weekly-plans/{id}', [\App\Http\Controllers\Admin\WorkoutController::class, 'updateWeeklyPlan'])->name('updateWeeklyPlan.api');
                Route::post('/weekly-plans/{id}/assign', [\App\Http\Controllers\Admin\WorkoutController::class, 'assignWeeklyPlan'])->name('assignWeeklyPlan.api');
                
                // Analytics & Insights
                Route::get('/analytics/overview', [\App\Http\Controllers\Admin\WorkoutController::class, 'getAnalyticsOverview'])->name('getAnalyticsOverview.api');
                Route::get('/analytics/video-popularity', [\App\Http\Controllers\Admin\WorkoutController::class, 'getVideoPopularity'])->name('getVideoPopularity.api');
                Route::get('/analytics/template-success', [\App\Http\Controllers\Admin\WorkoutController::class, 'getTemplateSuccess'])->name('getTemplateSuccess.api');
                Route::get('/analytics/client-engagement', [\App\Http\Controllers\Admin\WorkoutController::class, 'getClientEngagement'])->name('getClientEngagement.api');
                
                // Quick Workflow
                Route::get('/quick-templates', [\App\Http\Controllers\Admin\WorkoutController::class, 'getQuickTemplates'])->name('getQuickTemplates.api');
                Route::get('/recent-workouts', [\App\Http\Controllers\Admin\WorkoutController::class, 'getRecentWorkouts'])->name('getRecentWorkouts.api');
                Route::post('/quick-create', [\App\Http\Controllers\Admin\WorkoutController::class, 'quickCreateWorkout'])->name('quickCreateWorkout.api');
            });
            
            Route::post('/add-organization',[\App\Http\Controllers\Admin\OrganizationController::class,'addOrganization'])->name('addOrganization.api');
            Route::delete('/delete-organization/{id}',[\App\Http\Controllers\Admin\OrganizationController::class,'deleteOrganization'])->name('deleteOrganization.api');
            Route::post('/delete-bulk-employee',[\App\Http\Controllers\Admin\OrganizationController::class,'bulkDeleteEmployees'])->name('bulkDeleteEmployees.api');
            Route::get('/get-organization-submissions',[\App\Http\Controllers\Admin\OrganizationController::class,'getOrganizationSubmissions'])->name('getOrganizationSubmissions.api');
            Route::get('/get-organization-submission/{id}',[\App\Http\Controllers\Admin\OrganizationController::class,'getOrganizationSubmission'])->name('getOrganizationSubmission.api');


            Route::post('/add-coach',[\App\Http\Controllers\Admin\CoachController::class,'addCoach'])->name('addCoach.api');
            Route::post('/update-coach/{id}',[\App\Http\Controllers\Admin\CoachController::class,'updateCoach'])->name('updateCoach.api');
            Route::delete('/delete-coach/{id}',[\App\Http\Controllers\Admin\CoachController::class,'deleteCoach'])->name('deleteCoach.api');

            //Departments
            Route::post('/add-department',[\App\Http\Controllers\Admin\OrganizationController::class,'addDepartment'])->name('addDepartment.api');
            Route::post('/add-bulk-department',[\App\Http\Controllers\Admin\OrganizationController::class,'addBulkDepartments'])->name('addBulkDepartments.api');
            Route::post('/update-department/{id}',[\App\Http\Controllers\Admin\OrganizationController::class,'updateDepartment'])->name('updateDepartment.api');
            Route::get('/get-department/{id}',[\App\Http\Controllers\Admin\OrganizationController::class,'getDepartment'])->name('getDepartment.api');
            Route::get('/get-departments',[\App\Http\Controllers\Admin\OrganizationController::class,'getDepartments'])->name('getDepartments.api');
            Route::delete('/delete-department/{id}',[\App\Http\Controllers\Admin\OrganizationController::class,'deleteDepartment'])->name('deleteDepartment.api');

            //Rewards
            Route::post('/add-reward',[\App\Http\Controllers\Admin\OrganizationController::class,'addReward'])->name('addReward.api');
            Route::post('/update-reward/{id}',[\App\Http\Controllers\Admin\OrganizationController::class,'updateReward'])->name('updateReward.api');
            Route::get('/get-reward/{id}',[\App\Http\Controllers\Admin\OrganizationController::class,'getReward'])->name('getReward.api');
            Route::get('/get-rewards',[\App\Http\Controllers\Admin\OrganizationController::class,'getRewards'])->name('getRewards.api');
            Route::delete('/delete-reward/{id}',[\App\Http\Controllers\Admin\OrganizationController::class,'deleteReward'])->name('deleteReward.api');

            // New Organization Endpoints (Missing from completeness assessment)
            Route::get('/get-analytics-dashboard/{id}',[\App\Http\Controllers\Admin\OrganizationController::class,'getAnalyticsDashboard'])->name('getAnalyticsDashboard.api');
            Route::post('/bulk-invite/{id}',[\App\Http\Controllers\Admin\OrganizationController::class,'bulkInvite'])->name('bulkInvite.api');
            Route::get('/get-compliance-reports/{id}',[\App\Http\Controllers\Admin\OrganizationController::class,'getComplianceReports'])->name('getComplianceReports.api');

            //Employees
            Route::post('/import-employees',[\App\Http\Controllers\Admin\UserController::class,'importEmployees'])->name('importEmployees.api');
            //User Preferences

            //Dietary Resctrictions
            Route::post('/add-dietary-restriction',[\App\Http\Controllers\Admin\UserController::class,'addDietaryRestriction'])->name('addDietaryRestriction.api');
            Route::post('/update-dietary-restriction/{id}',[\App\Http\Controllers\Admin\UserController::class,'updateDietaryRestriction'])->name('updateDietaryRestriction.api');
            Route::get('/get-dietary-restriction/{id}',[\App\Http\Controllers\Admin\UserController::class,'getDietaryRestriction'])->name('getDietaryRestriction.api');
            Route::get('/get-dietary-restrictions',[\App\Http\Controllers\Admin\UserController::class,'getDietaryRestrictions'])->name('getDietaryRestrictions.api');
            Route::delete('/delete-dietary-restriction/{id}',[\App\Http\Controllers\Admin\UserController::class,'deleteDietaryRestriction'])->name('deleteDietaryRestriction.api');

            //Training Preferences
            Route::post('/add-training-preference',[\App\Http\Controllers\Admin\UserController::class,'addTrainingPreference'])->name('addTrainingPreference.api');
            Route::post('/update-training-preference/{id}',[\App\Http\Controllers\Admin\UserController::class,'updateTrainingPreference'])->name('updateTrainingPreference.api');
            Route::get('/get-training-preference/{id}',[\App\Http\Controllers\Admin\UserController::class,'getTrainingPreference'])->name('getTrainingPreference.api');
            Route::get('/get-training-preferences',[\App\Http\Controllers\Admin\UserController::class,'getTrainingPreferences'])->name('getTrainingPreferences.api');
            Route::delete('/delete-training-preference/{id}',[\App\Http\Controllers\Admin\UserController::class,'deleteTrainingPreference'])->name('deleteTrainingPreference.api');

            //Equipment Preferences
            Route::post('/add-equipment-preference',[\App\Http\Controllers\Admin\UserController::class,'addEquipmentPreference'])->name('addEquipmentPreference.api');
            Route::post('/update-equipment-preference/{id}',[\App\Http\Controllers\Admin\UserController::class,'updateEquipmentPreference'])->name('updateEquipmentPreference.api');
            Route::get('/get-equipment-preference/{id}',[\App\Http\Controllers\Admin\UserController::class,'getEquipmentPreference'])->name('getEquipmentPreference.api');
            Route::get('/get-equipment-preferences',[\App\Http\Controllers\Admin\UserController::class,'getEquipmentPreferences'])->name('getEquipmentPreferences.api');
            Route::delete('/delete-equipment-preference/{id}',[\App\Http\Controllers\Admin\UserController::class,'deleteEquipmentPreference'])->name('deleteEquipmentPreference.api');

            Route::get('/send-user-credentials/{id}',[\App\Http\Controllers\Admin\AuthController::class,'sendUserCreds'])->name('sendUserCreds.api');

            //Create Unlink video exercises
            Route::get('/create-unlink-video-exercise',[\App\Http\Controllers\Admin\ExerciseController::class,'createExercisesFromUnlinkedVideos'])->name('createExercisesFromUnlinkedVideos.api');

        // Coach Specialized Dashboards - Phase 7 Fix (moved inside auth:admin middleware)
        Route::prefix('coaches')->group(function() {
            Route::get('/fitness/dashboard', [\App\Http\Controllers\CoachDashboardController::class, 'getFitnessDashboard']);
            Route::get('/nutrition/dashboard', [\App\Http\Controllers\CoachDashboardController::class, 'getNutritionDashboard']);
            Route::get('/cbt/dashboard', [\App\Http\Controllers\CoachDashboardController::class, 'getCBTDashboard']);
            Route::get('/notifications/dashboard', [\App\Http\Controllers\CoachDashboardController::class, 'getNotificationsDashboard']);
            Route::get('/progression/dashboard', [\App\Http\Controllers\CoachDashboardController::class, 'getProgressionDashboard']);
            Route::get('/calendar/dashboard', [\App\Http\Controllers\CoachDashboardController::class, 'getCalendarDashboard']);

            // Dashboard Layout Management - Server-side persistence for widget configurations
            Route::get('/dashboard/layout', [\App\Http\Controllers\CoachDashboardController::class, 'getLayoutConfig']);
            Route::post('/dashboard/layout', [\App\Http\Controllers\CoachDashboardController::class, 'saveLayoutConfig']);
            Route::post('/dashboard/reset-layout', [\App\Http\Controllers\CoachDashboardController::class, 'resetLayoutConfig']);
        });

        // SECURITY FIX: DO NOT close auth:admin middleware here
        // All routes below MUST be inside auth:admin for security
        // Previously this closed too early, exposing 200+ endpoints publicly

        Route::get('/get-equipment-preferences-dropdown',[\App\Http\Controllers\Admin\UserController::class,'getEquipmentDropDown'])->name('getEquipmentDropDown.api');
        Route::get('/get-training-preferences-dropdown',[\App\Http\Controllers\Admin\UserController::class,'getTrainingDropDown'])->name('getTrainingDropDown.api');
        Route::get('/get-dietary-restrictions-dropdown',[\App\Http\Controllers\Admin\UserController::class,'getDietaryDropDown'])->name('getDietaryDropDown.api');
        //Dashboard Stats
        Route::get('/get-dashboard-stats',[\App\Http\Controllers\Admin\AuthController::class,'getDashboardStats'])->name('getDashboardStats.api');
        Route::get('/get-users-list',[\App\Http\Controllers\Admin\UserController::class,'getUsersList'])->name('getUsersList.api');
        Route::get('/get-activity-logs',[\App\Http\Controllers\Admin\AuthController::class,'getActivityLogs'])->name('getActivityLogs.api');
        Route::get('/get-performance-metrics',[\App\Http\Controllers\Admin\AuthController::class,'getPerformanceMetrics'])->name('getPerformanceMetrics.api');

        // Admin Dashboard Stats - Extended Routes
        Route::get('/dashboard-stats',[\App\Http\Controllers\Admin\AuthController::class,'getDashboardStatsExtended'])->name('getDashboardStatsExtended.api');
        Route::get('/get-recent-activity',[\App\Http\Controllers\Admin\AuthController::class,'getRecentActivity'])->name('getRecentActivity.api');
        Route::get('/get-system-health',[\App\Http\Controllers\Admin\AuthController::class,'getSystemHealth'])->name('getSystemHealth.api');

        //Employees
        Route::get('/get-employees',[\App\Http\Controllers\Admin\UserController::class,'getEmployees'])->name('getEmployees.api');
        Route::get('/get-employee/{id}',[\App\Http\Controllers\Admin\UserController::class,'getEmployee'])->name('getEmployee.api');
        Route::get('/get-rewards-dropdown',[\App\Http\Controllers\Admin\OrganizationController::class,'getRewardDropDown'])->name('getRewardDropDown.api');
        Route::get('/get-user-dropdown',[\App\Http\Controllers\Admin\UserController::class,'getUserDropDown'])->name('getUserDropDown.api');
        Route::post('/add-employee',[\App\Http\Controllers\Admin\UserController::class,'addEmployee'])->name('addEmployee.api');
        Route::post('/update-employee/{id}',[\App\Http\Controllers\Admin\UserController::class,'updateEmployee'])->name('updateEmployee.api');
        Route::delete('/delete-employee/{id}',[\App\Http\Controllers\Admin\UserController::class,'deleteEmployee'])->name('deleteEmployee.api');

        //Clients (alias for employees - used by frontend)
        Route::post('/clients',[\App\Http\Controllers\Admin\UserController::class,'addEmployee'])->name('addClient.api');
        Route::delete('/clients/{id}',[\App\Http\Controllers\Admin\UserController::class,'deleteEmployee'])->name('deleteClient.api');

        //Organizations
        Route::get('/get-organizations',[\App\Http\Controllers\Admin\OrganizationController::class,'getOrganizations'])->name('getOrganizations.api');
        Route::get('/get-organization/{id}',[\App\Http\Controllers\Admin\OrganizationController::class,'getOrganization'])->name('getOrganization.api');
        Route::get('/get-organizations-dropdown',[\App\Http\Controllers\Admin\OrganizationController::class,'getOrganizationDropDown'])->name('getOrganizationDropDown.api');

        // Organization Content Assignment
        Route::get('/organizations/{id}/workout-plans',[\App\Http\Controllers\Admin\OrganizationController::class,'getOrganizationWorkoutPlans'])->name('getOrganizationWorkoutPlans.api');
        Route::post('/organizations/{id}/assign-workout-plans',[\App\Http\Controllers\Admin\OrganizationController::class,'assignWorkoutPlans'])->name('assignWorkoutPlansToOrganization.api');
        Route::delete('/organizations/{orgId}/workout-plans/{planId}',[\App\Http\Controllers\Admin\OrganizationController::class,'unassignWorkoutPlan'])->name('unassignWorkoutPlanFromOrganization.api');
        Route::get('/organizations/{id}/clients',[\App\Http\Controllers\Admin\OrganizationController::class,'getOrganizationClients'])->name('getOrganizationClients.api');
        Route::get('/organizations/{id}/coaches',[\App\Http\Controllers\Admin\OrganizationController::class,'getOrganizationCoaches'])->name('getOrganizationCoaches.api');

        //Coaches
        Route::get('/get-coaches',[\App\Http\Controllers\Admin\CoachController::class,'getCoaches'])->name('getCoaches.api');
        Route::get('/get-coach/{id}',[\App\Http\Controllers\Admin\CoachController::class,'getCoach'])->name('getCoach.api');
        Route::get('/get-coaches-dropdown',[\App\Http\Controllers\Admin\CoachController::class,'getCoachDropDown'])->name('getCoachDropDown.api');

        // Admin Coach Extended Routes
        Route::post('/assign-coach',[\App\Http\Controllers\Admin\CoachController::class,'assignCoach'])->name('assignCoach.api');
        Route::post('/unassign-coach',[\App\Http\Controllers\Admin\CoachController::class,'unassignCoach'])->name('unassignCoach.api');
        Route::get('/get-coach-clients/{coachId}',[\App\Http\Controllers\Admin\CoachController::class,'getCoachClients'])->name('getCoachClients.api');
        Route::get('/get-coach-schedule/{coachId}',[\App\Http\Controllers\Admin\CoachController::class,'getCoachSchedule'])->name('getCoachSchedule.api');
        Route::post('/update-coach-availability',[\App\Http\Controllers\Admin\CoachController::class,'updateCoachAvailability'])->name('updateCoachAvailability.api');
        Route::get('/get-coach-stats/{coachId}',[\App\Http\Controllers\Admin\CoachController::class,'getCoachStats'])->name('getCoachStats.api');
        Route::post('/toggle-coach-status/{coachId}',[\App\Http\Controllers\Admin\CoachController::class,'toggleCoachStatus'])->name('toggleCoachStatus.api');
        Route::get('/get-coach-earnings/{coachId}',[\App\Http\Controllers\Admin\CoachController::class,'getCoachEarnings'])->name('getCoachEarnings.api');

        // Admin Coach Dashboard Views
        Route::get('/coach-dashboard/clients',[\App\Http\Controllers\Admin\CoachController::class,'getCoachDashboardClients'])->name('getCoachDashboardClients.api');
        Route::get('/coach-dashboard/calendar',[\App\Http\Controllers\Admin\CoachController::class,'getCoachDashboardCalendar'])->name('getCoachDashboardCalendar.api');
        Route::get('/coach-dashboard/analytics',[\App\Http\Controllers\Admin\CoachController::class,'getCoachDashboardAnalytics'])->name('getCoachDashboardAnalytics.api');
        Route::get('/coach-dashboard/earnings',[\App\Http\Controllers\Admin\CoachController::class,'getCoachDashboardEarnings'])->name('getCoachDashboardEarnings.api');

        //Challenges
        Route::get('/get-challenge-types',[\App\Http\Controllers\Admin\ChallengeController::class,'getChallengeTypes'])->name('getChallengeTypes.api');
        Route::get('/get-challenges',[\App\Http\Controllers\Admin\ChallengeController::class,'getChallenges'])->name('getChallenges.api');
        Route::post('/add-challenge',[\App\Http\Controllers\Admin\ChallengeController::class,'addChallenge'])->name('addChallenge.api');
        Route::post('/update-challenge/{id}',[\App\Http\Controllers\Admin\ChallengeController::class,'updateChallenge'])->name('updateChallenge.api');
        Route::get('/get-challenge/{id}',[\App\Http\Controllers\Admin\ChallengeController::class,'getChallenge'])->name('getChallenge.api');
        Route::delete('/delete-challenge/{id}',[\App\Http\Controllers\Admin\ChallengeController::class,'deleteChallenge'])->name('deleteChallenge.api');


        //Videos
        Route::get('/get-videos',[\App\Http\Controllers\Admin\VideoController::class,'getVideos'])->name('getVideos.api');
        Route::post('/add-video',[\App\Http\Controllers\Admin\VideoController::class,'addVideo'])->name('addVideo.api');
        Route::post('/update-video/{id}',[\App\Http\Controllers\Admin\VideoController::class,'updateVideo'])->name('updateVideo.api');
        Route::get('/get-video/{id}',[\App\Http\Controllers\Admin\VideoController::class,'getVideo'])->name('getVideo.api');
        Route::get('/clone-video/{id}',[\App\Http\Controllers\Admin\VideoController::class,'cloneVideo'])->name('cloneVideo.api');
        Route::get('/get-video-tags',[\App\Http\Controllers\Admin\VideoController::class,'getVideoTags'])->name('getVideoTags.api');
        Route::delete('/delete-video/{id}',[\App\Http\Controllers\Admin\VideoController::class,'deleteVideo'])->name('deleteVideo.api');
        //Intro Videos
        Route::get('/get-intro-videos',[\App\Http\Controllers\Admin\IntroVideoController::class,'getIntroVideos'])->name('getIntroVideos.api');
        Route::post('/add-intro-video',[\App\Http\Controllers\Admin\IntroVideoController::class,'addIntroVideo'])->name('addIntroVideo.api');
        Route::post('/update-intro-video/{id}',[\App\Http\Controllers\Admin\IntroVideoController::class,'updateIntroVideo'])->name('updateIntroVideo.api');
        Route::get('/get-intro-video/{id}',[\App\Http\Controllers\Admin\IntroVideoController::class,'getIntroVideo'])->name('getIntroVideo.api');
        Route::delete('/delete-intro-video/{id}',[\App\Http\Controllers\Admin\IntroVideoController::class,'deleteIntroVideo'])->name('deleteIntroVideo.api');

        //Exercises
        Route::get('/get-exercises',[\App\Http\Controllers\Admin\ExerciseController::class,'getExercises'])->name('getExercises.api');
        Route::get('/exercises',[\App\Http\Controllers\Admin\ExerciseController::class,'getExercises']); // Alias for workout builder
        Route::get('/exercises/search',[\App\Http\Controllers\Admin\ExerciseController::class,'getExercises']); // Search uses same method with query param
        Route::get('/exercises/category/{category}',[\App\Http\Controllers\Admin\ExerciseController::class,'getExercises']); // Filter by category
        Route::get('/exercises/muscle-group/{muscleGroup}',[\App\Http\Controllers\Admin\ExerciseController::class,'getExercises']); // Filter by muscle group
        Route::post('/add-exercise',[\App\Http\Controllers\Admin\ExerciseController::class,'addExercise'])->name('addExercise.api');
        Route::post('/update-exercise/{id}',[\App\Http\Controllers\Admin\ExerciseController::class,'updateExercise'])->name('updateExercise.api');
        Route::get('/get-exercise/{id}',[\App\Http\Controllers\Admin\ExerciseController::class,'getExercise'])->name('getExercise.api');
        Route::get('/exercises/{id}',[\App\Http\Controllers\Admin\ExerciseController::class,'getExercise']); // Alias for workout builder
        Route::get('/clone-exercise/{id}',[\App\Http\Controllers\Admin\ExerciseController::class,'cloneExercise'])->name('cloneExercise.api');
        Route::delete('/delete-exercise/{id}',[\App\Http\Controllers\Admin\ExerciseController::class,'deleteExercise'])->name('deleteExercise.api');

        //Workouts
        Route::post('/add-workout',[\App\Http\Controllers\Admin\WorkoutController::class,'addWorkout'])->name('addWorkout.api');
        Route::get('/get-workouts',[\App\Http\Controllers\Admin\WorkoutController::class,'getWorkouts'])->name('getWorkouts.api');
        Route::get('/get-workout/{id}',[\App\Http\Controllers\Admin\WorkoutController::class,'getWorkout'])->name('getWorkout.api');
        Route::post('/update-workout/{id}',[\App\Http\Controllers\Admin\WorkoutController::class,'updateWorkout'])->name('updateWorkout.api');
        Route::delete('/delete-workout/{id}',[\App\Http\Controllers\Admin\WorkoutController::class,'deleteWorkout'])->name('deleteWorkout.api');
        Route::get('/get-exercises-dropdown',[\App\Http\Controllers\Admin\WorkoutController::class,'getExercisesDropDown'])->name('getExercisesDropDown.api');
        
        // Enhanced Admin Workout Management
        Route::post('/program-weekly-workouts',[\App\Http\Controllers\Admin\WorkoutController::class,'programWeeklyWorkouts'])->name('programWeeklyWorkouts.api');
        Route::get('/get-client-workout-calendar/{userId}/{date}',[\App\Http\Controllers\Admin\WorkoutController::class,'getClientWorkoutCalendar'])->name('getClientWorkoutCalendar.api');
        Route::post('/add-workout-equipment',[\App\Http\Controllers\Admin\WorkoutController::class,'addWorkoutEquipment'])->name('addWorkoutEquipment.api');
        Route::post('/add-exercise-warmup',[\App\Http\Controllers\Admin\WorkoutController::class,'addExerciseWarmUp'])->name('addExerciseWarmUp.api');
        Route::post('/add-exercise-cooldown', [\App\Http\Controllers\Admin\WorkoutController::class, 'addExerciseCooldown']);
        Route::get('/equipment-list', [\App\Http\Controllers\Admin\WorkoutController::class, 'getEquipmentList']);
        // AI Workout Generation
        Route::post('generate-ai-workout', [\App\Http\Controllers\Admin\WorkoutController::class, 'generateAIWorkout']);
        Route::post('ai-workout-suggestions', [\App\Http\Controllers\Admin\WorkoutController::class, 'getAIWorkoutSuggestions']);
        Route::get('/get-plans',[\App\Http\Controllers\Admin\PlanController::class,'getPlans'])->name('getPlans.api');
        Route::get('/get-plans-dropdown',[\App\Http\Controllers\Admin\PlanController::class,'getPlanDropDown'])->name('getPlanDropDown.api');
        Route::post('/add-plan',[\App\Http\Controllers\Admin\PlanController::class,'addPlanWithCloning'])->name('addPlanWithCloning.api');
        Route::post('/update-plan/{id}',[\App\Http\Controllers\Admin\PlanController::class,'updatePlanWithCloning'])->name('updatePlanWithCloning.api');
        Route::get('/get-plan/{id}',[\App\Http\Controllers\Admin\PlanController::class,'getPlan'])->name('getPlan.api');
        Route::get('/clone-plan/{id}',[\App\Http\Controllers\Admin\PlanController::class,'clonePlanWithCloning'])->name('clonePlanWithCloning.api');
        Route::delete('/delete-plan/{id}',[\App\Http\Controllers\Admin\PlanController::class,'deletePlan'])->name('deletePlan.api');
        Route::post('/assign-plan',[\App\Http\Controllers\Admin\PlanController::class,'assignPlan'])->name('assignPlan.api');
        Route::delete('/delete-assign-plan/{id}',[\App\Http\Controllers\Admin\PlanController::class,'deleteAssignPlan'])->name('deleteAssignPlan.api');
        //Check Deletion
        Route::get('/check-deletion/{id}',[\App\Http\Controllers\Admin\AuthController::class,'checkDeletion'])->name('checkDeletion.api');

        // Admin Dropdowns - Extended Routes
        Route::get('/get-users-dropdown',[\App\Http\Controllers\Admin\UserController::class,'getUsersDropdown'])->name('getUsersDropdown.api');
        Route::get('/get-challenges-dropdown',[\App\Http\Controllers\Admin\ChallengeController::class,'getChallengesDropdown'])->name('getChallengesDropdown.api');
        Route::get('/get-workout-types-dropdown',[\App\Http\Controllers\Admin\WorkoutController::class,'getWorkoutTypesDropdown'])->name('getWorkoutTypesDropdown.api');
        Route::get('/get-equipment-dropdown',[\App\Http\Controllers\Admin\WorkoutController::class,'getEquipmentDropdown'])->name('getEquipmentDropdown.api');
        Route::get('/get-muscle-groups-dropdown',[\App\Http\Controllers\Admin\ExerciseController::class,'getMuscleGroupsDropdown'])->name('getMuscleGroupsDropdown.api');

        //Chats
        Route::get('/get-inbox-chat', [\App\Http\Controllers\ChatController::class, "getInboxChat"])->name("getInboxChat.api");
        Route::get('/get-my-inbox', [\App\Http\Controllers\ChatController::class, "getMyInboxChats"])->name("getMyInboxChats.api");
        Route::get('/get-inbox/{id}', [\App\Http\Controllers\ChatController::class, "getInboxChatbyID"])->name("getInboxChatbyID.api");
        Route::post('/send-message', [\App\Http\Controllers\ChatController::class, "sendMessage"])->name("sendMessage.api");


        //Nutrition Calculations
        Route::get('/get-nutrition-calculations',[\App\Http\Controllers\Admin\NutritionController::class,'getNutritionCalculations'])->name('getNutritionCalculations.api');
        Route::post('/update-nutrition-calculation',[\App\Http\Controllers\Admin\NutritionController::class,'updateNutritionCalculation'])->name('updateNutritionCalculation.api');
        Route::get('/restore-nutrition-calculation',[\App\Http\Controllers\Admin\NutritionController::class,'restoreNutritionCalculation'])->name('restoreNutritionCalculation.api');

        //Nutrition Plans
        Route::get('/get-nutrition-plans',[\App\Http\Controllers\Admin\NutritionController::class,'getAllNutritionPlans'])->name('getAllNutritionPlans.api');

        //Meal Plan Templates
        Route::get('/meal-plan-templates',[\App\Http\Controllers\Admin\MealPlanController::class,'getMealPlans'])->name('getMealPlans.api');
        Route::post('/meal-plan-templates',[\App\Http\Controllers\Admin\MealPlanController::class,'createMealPlan'])->name('createMealPlan.api');
        Route::get('/meal-plan-templates/{id}',[\App\Http\Controllers\Admin\MealPlanController::class,'getMealPlan'])->name('getMealPlan.api');
        Route::put('/meal-plan-templates/{id}',[\App\Http\Controllers\Admin\MealPlanController::class,'updateMealPlan'])->name('updateMealPlan.api');
        Route::delete('/meal-plan-templates/{id}',[\App\Http\Controllers\Admin\MealPlanController::class,'deleteMealPlan'])->name('deleteMealPlan.api');
        Route::post('/meal-plan-templates/{id}/clone',[\App\Http\Controllers\Admin\MealPlanController::class,'cloneMealPlan'])->name('cloneMealPlan.api');
        Route::post('/meal-plan-templates/assign',[\App\Http\Controllers\Admin\MealPlanController::class,'assignMealPlan'])->name('assignMealPlan.api');
        Route::post('/meal-plan-templates/unassign',[\App\Http\Controllers\Admin\MealPlanController::class,'unassignMealPlan'])->name('unassignMealPlan.api');
        Route::get('/users/{userId}/meal-plan-templates',[\App\Http\Controllers\Admin\MealPlanController::class,'getUserMealPlans'])->name('getUserMealPlans.api');
        Route::get('/organizations/{orgId}/meal-plan-templates',[\App\Http\Controllers\Admin\MealPlanController::class,'getOrganizationMealPlans'])->name('getOrganizationMealPlans.api');

        // Admin Meal Templates - Extended Routes
        Route::get('/get-meal-plan-templates',[\App\Http\Controllers\Admin\MealPlanController::class,'getMealPlanTemplates'])->name('getMealPlanTemplates.api');
        Route::post('/add-meal-plan-template',[\App\Http\Controllers\Admin\MealPlanController::class,'addMealPlanTemplate'])->name('addMealPlanTemplate.api');
        Route::post('/update-meal-plan-template/{id}',[\App\Http\Controllers\Admin\MealPlanController::class,'updateMealPlanTemplate'])->name('updateMealPlanTemplate.api');
        Route::get('/get-meal-plan-template/{id}',[\App\Http\Controllers\Admin\MealPlanController::class,'getMealPlanTemplateById'])->name('getMealPlanTemplateById.api');
        Route::post('/assign-meal-plan-template',[\App\Http\Controllers\Admin\MealPlanController::class,'assignMealPlanTemplate'])->name('assignMealPlanTemplate.api');
        Route::get('/get-assigned-meal-plans/{userId}',[\App\Http\Controllers\Admin\MealPlanController::class,'getAssignedMealPlans'])->name('getAssignedMealPlans.api');
        Route::get('/clone-meal-plan-template/{id}',[\App\Http\Controllers\Admin\MealPlanController::class,'cloneMealPlanTemplate'])->name('cloneMealPlanTemplate.api');

        //Body Points
        Route::get('/get-body-points',[\App\Http\Controllers\Admin\NutritionController::class,'getBodyPoints'])->name('getBodyPoints.api');
        Route::post('/update-body-point',[\App\Http\Controllers\Admin\NutritionController::class,'updateBodyPoint'])->name('updateBodyPoint.api');

        //Notifications
        Route::post('send-notification',[\App\Http\Controllers\Admin\NotificationController::class,'sendNotification'])->name('sendNotification.api');
        Route::get('get-notifications',[\App\Http\Controllers\Admin\NotificationController::class,'getNotifications'])->name('getNotifications.api');
        Route::delete('delete-notification/{id}',[\App\Http\Controllers\Admin\NotificationController::class,'deleteNotification'])->name('deleteNotification.api');
        Route::get('get-users-drop-down',[\App\Http\Controllers\Admin\NotificationController::class,'getUsersDropDown'])->name('getUsersDropDown.api');
        Route::post('/add-notification',[\App\Http\Controllers\Admin\NotificationController::class,'addNotification'])->name('addNotification.api');
        Route::get('/get-notification/{id}',[\App\Http\Controllers\Admin\NotificationController::class,'getNotification'])->name('getNotification.api');

        //Site Info
        Route::post('update-site-info',[\App\Http\Controllers\Admin\AuthController::class,'updateSiteInfo'])->name('updateSiteInfo.api');
        Route::get('get-site-info',[\App\Http\Controllers\Admin\AuthController::class,'getSiteInfo'])->name('getSiteInfo.api');

        // Coach Dashboard & Management
        Route::prefix('coach')->group(function() {
            Route::get('/dashboard', [\App\Http\Controllers\CoachDashboardController::class, 'getDashboardOverview']);
            Route::get('/clients', [\App\Http\Controllers\CoachDashboardController::class, 'getClients']);
            Route::get('/clients/{id}', [\App\Http\Controllers\CoachDashboardController::class, 'getClientDetails']);
            Route::post('/assign-workout', [\App\Http\Controllers\CoachDashboardController::class, 'assignWorkoutToClients']);
            Route::post('/assign-plan', [\App\Http\Controllers\CoachDashboardController::class, 'assignPlanToClients']);
            Route::post('/clients/{id}/note', [\App\Http\Controllers\CoachDashboardController::class, 'addClientNote']);
            Route::post('/clients/send-notification', [\App\Http\Controllers\CoachDashboardController::class, 'sendNotificationToClients']);

            // Client Analytics & Progression Photos
            Route::get('/clients/{id}/analytics', [\App\Http\Controllers\CoachDashboardController::class, 'getClientAnalytics']);
            Route::get('/clients/{id}/progression-photos', [\App\Http\Controllers\CoachDashboardController::class, 'getProgressionPhotos']);
            Route::post('/clients/{id}/progression-photos', [\App\Http\Controllers\CoachDashboardController::class, 'uploadProgressionPhoto'])->middleware(['throttle.upload']);
            Route::delete('/clients/{clientId}/progression-photos/{photoId}', [\App\Http\Controllers\CoachDashboardController::class, 'deleteProgressionPhoto']);

            Route::get('/analytics', [\App\Http\Controllers\CoachDashboardController::class, 'getAnalytics']);

            // New Dashboard Tabs
            Route::get('/progression/dashboard', [\App\Http\Controllers\CoachDashboardController::class, 'getProgressionDashboard']);
            Route::get('/calendar/dashboard', [\App\Http\Controllers\CoachDashboardController::class, 'getCalendarDashboard']);
            Route::get('/measurements', [\App\Http\Controllers\CoachDashboardController::class, 'getMeasurements']);
            Route::get('/milestones', [\App\Http\Controllers\CoachDashboardController::class, 'getMilestones']);

            // Workout Plan Management
            Route::post('/workout-plans/create', [\App\Http\Controllers\CoachDashboardController::class, 'createWorkoutPlan']);
            Route::get('/workout-plans/{id}', [\App\Http\Controllers\CoachDashboardController::class, 'getWorkoutPlan']);
            Route::put('/workout-plans/{id}', [\App\Http\Controllers\CoachDashboardController::class, 'updateWorkoutPlan']);
            Route::delete('/workout-plans/{id}', [\App\Http\Controllers\CoachDashboardController::class, 'deleteWorkoutPlan']);
            Route::get('/clients/{clientId}/workout-plans', [\App\Http\Controllers\CoachDashboardController::class, 'getClientWorkoutPlans']);
        });

        // Admin Coach Dashboard Tabs - Extended Routes
        Route::prefix('coach-dashboard')->group(function() {
            Route::get('/clients', [\App\Http\Controllers\CoachDashboardController::class, 'getCoachDashboardClients'])->name('getCoachDashboardClients.api');
            Route::get('/calendar', [\App\Http\Controllers\CoachDashboardController::class, 'getCoachDashboardCalendar'])->name('getCoachDashboardCalendar.api');
            Route::get('/analytics', [\App\Http\Controllers\CoachDashboardController::class, 'getCoachDashboardAnalytics'])->name('getCoachDashboardAnalytics.api');
            Route::get('/earnings', [\App\Http\Controllers\CoachDashboardController::class, 'getCoachDashboardEarnings'])->name('getCoachDashboardEarnings.api');
        });

        // PT Studio Management System
        Route::prefix('pt-studio')->group(function() {
            // Dashboard & Overview
            Route::get('/{studioId}/dashboard', [\App\Http\Controllers\PTStudioController::class, 'getDashboard']);
            Route::get('/{studioId}/coaches', [\App\Http\Controllers\PTStudioController::class, 'getCoaches']);
            Route::get('/{studioId}/clients', [\App\Http\Controllers\PTStudioController::class, 'getClients']);
            Route::get('/{studioId}/analytics', [\App\Http\Controllers\PTStudioController::class, 'getAnalytics']);
            Route::get('/{studioId}/calendar', [\App\Http\Controllers\PTStudioController::class, 'getCalendar']);

            // Appointments Management
            Route::get('/appointments', [\App\Http\Controllers\AppointmentController::class, 'index']);
            Route::post('/appointments', [\App\Http\Controllers\AppointmentController::class, 'store']);
            Route::get('/appointments/{id}', [\App\Http\Controllers\AppointmentController::class, 'show']);
            Route::put('/appointments/{id}', [\App\Http\Controllers\AppointmentController::class, 'update']);
            Route::delete('/appointments/{id}', [\App\Http\Controllers\AppointmentController::class, 'destroy']);
            Route::post('/appointments/{id}/cancel', [\App\Http\Controllers\AppointmentController::class, 'cancel']);
            Route::post('/appointments/{id}/no-show', [\App\Http\Controllers\AppointmentController::class, 'markNoShow']);
            Route::post('/appointments/send-reminders', [\App\Http\Controllers\AppointmentController::class, 'sendReminders']);

            // Coach Availability Management
            Route::get('/availability', [\App\Http\Controllers\CoachAvailabilityController::class, 'index']);
            Route::post('/availability', [\App\Http\Controllers\CoachAvailabilityController::class, 'store']);
            Route::get('/availability/{id}', [\App\Http\Controllers\CoachAvailabilityController::class, 'show']);
            Route::put('/availability/{id}', [\App\Http\Controllers\CoachAvailabilityController::class, 'update']);
            Route::delete('/availability/{id}', [\App\Http\Controllers\CoachAvailabilityController::class, 'destroy']);
            Route::get('/coaches/{coachId}/available-slots', [\App\Http\Controllers\CoachAvailabilityController::class, 'getAvailableSlots']);
        });

        // Admin Library Management System
        Route::prefix('library-management')->group(function() {
            // Workouts Library
            Route::get('/workouts', [\App\Http\Controllers\AdminLibraryController::class, 'getWorkoutLibrary']);
            Route::post('/workouts', [\App\Http\Controllers\AdminLibraryController::class, 'storeWorkoutLibrary']);
            Route::put('/workouts/{id}', [\App\Http\Controllers\AdminLibraryController::class, 'updateWorkoutLibrary']);
            Route::delete('/workouts/{id}', [\App\Http\Controllers\AdminLibraryController::class, 'deleteWorkoutLibrary']);

            // Nutrition Plans Library
            Route::get('/nutrition-plans', [\App\Http\Controllers\AdminLibraryController::class, 'getNutritionPlanLibrary']);
            Route::post('/nutrition-plans', [\App\Http\Controllers\AdminLibraryController::class, 'storeNutritionPlanLibrary']);
            Route::put('/nutrition-plans/{id}', [\App\Http\Controllers\AdminLibraryController::class, 'updateNutritionPlanLibrary']);
            Route::delete('/nutrition-plans/{id}', [\App\Http\Controllers\AdminLibraryController::class, 'deleteNutritionPlanLibrary']);

            // Challenges Library
            Route::get('/challenges', [\App\Http\Controllers\AdminLibraryController::class, 'getChallengeLibrary']);
            Route::post('/challenges', [\App\Http\Controllers\AdminLibraryController::class, 'storeChallengeLibrary']);
            Route::put('/challenges/{id}', [\App\Http\Controllers\AdminLibraryController::class, 'updateChallengeLibrary']);
            Route::delete('/challenges/{id}', [\App\Http\Controllers\AdminLibraryController::class, 'deleteChallengeLibrary']);

            // Videos Library (Generic routes for all 4 types: fitness, nutrition, mindset, notification)
            Route::get('/videos/{type}', [\App\Http\Controllers\AdminLibraryController::class, 'getVideoLibrary']);
            Route::post('/videos/{type}', [\App\Http\Controllers\AdminLibraryController::class, 'storeVideoLibrary']);
            Route::put('/videos/{type}/{id}', [\App\Http\Controllers\AdminLibraryController::class, 'updateVideoLibrary']);
            Route::delete('/videos/{type}/{id}', [\App\Http\Controllers\AdminLibraryController::class, 'deleteVideoLibrary']);

            // View all coaches' private content (admin oversight)
            Route::get('/private-content/{type}', [\App\Http\Controllers\AdminLibraryController::class, 'getAllCoachPrivateContent']);
        });

        //Faqs
        Route::post('/add-faq', [\App\Http\Controllers\Admin\AuthController::class, "addFaq"])->name("addFaq.api");
        Route::post('/update-faq/{id?}', [\App\Http\Controllers\Admin\AuthController::class, "updateFaq"])->name("updateFaq.api");
        Route::get('/get-faq/{id?}', [\App\Http\Controllers\Admin\AuthController::class, "getFaq"])->name("getFaq.api");
        Route::get('/get-faqs', [\App\Http\Controllers\Admin\AuthController::class, "getFaqs"])->name("getFaqs.api");
        Route::delete('/delete-faq/{id?}', [\App\Http\Controllers\Admin\AuthController::class, "deleteFaq"])->name("deleteFaq.api");

        // ========== NEW: MISSING ADMIN DASHBOARD ENDPOINTS ==========
        // Admin Dashboard & Analytics
        Route::get('/get-activity-logs', [\App\Http\Controllers\Admin\DashboardController::class, 'getActivityLogs'])->name('getActivityLogs.api');
        Route::get('/get-performance-metrics', [\App\Http\Controllers\Admin\DashboardController::class, 'getPerformanceMetrics'])->name('getPerformanceMetrics.api');
        Route::get('/get-recent-activity', [\App\Http\Controllers\Admin\DashboardController::class, 'getRecentActivity'])->name('getRecentActivity.api');
        Route::get('/get-system-health', [\App\Http\Controllers\Admin\DashboardController::class, 'getSystemHealth'])->name('getSystemHealth.api');
        Route::get('/dashboard-stats', [\App\Http\Controllers\Admin\DashboardController::class, 'getDashboardStats'])->name('getDashboardStats.api');

        // Analytics Dashboard (13 comprehensive endpoints)
        Route::get('/analytics/dashboard-summary', [\App\Http\Controllers\Admin\AnalyticsController::class, 'getDashboardSummary'])->name('analytics.dashboardSummary.api');
        Route::get('/analytics/user-growth', [\App\Http\Controllers\Admin\AnalyticsController::class, 'getUserGrowth'])->name('analytics.userGrowth.api');
        Route::get('/analytics/user-demographics', [\App\Http\Controllers\Admin\AnalyticsController::class, 'getUserDemographics'])->name('analytics.userDemographics.api');
        Route::get('/analytics/user-retention', [\App\Http\Controllers\Admin\AnalyticsController::class, 'getUserRetention'])->name('analytics.userRetention.api');
        Route::get('/analytics/revenue-trends', [\App\Http\Controllers\Admin\AnalyticsController::class, 'getRevenueTrends'])->name('analytics.revenueTrends.api');
        Route::get('/analytics/revenue-by-plan', [\App\Http\Controllers\Admin\AnalyticsController::class, 'getRevenueByPlan'])->name('analytics.revenueByPlan.api');
        Route::get('/analytics/engagement-metrics', [\App\Http\Controllers\Admin\AnalyticsController::class, 'getEngagementMetrics'])->name('analytics.engagementMetrics.api');
        Route::get('/analytics/popular-content', [\App\Http\Controllers\Admin\AnalyticsController::class, 'getPopularContent'])->name('analytics.popularContent.api');
        Route::get('/analytics/activity-heatmap', [\App\Http\Controllers\Admin\AnalyticsController::class, 'getUserActivityHeatmap'])->name('analytics.activityHeatmap.api');
        Route::get('/analytics/system-performance', [\App\Http\Controllers\Admin\AnalyticsController::class, 'getSystemPerformance'])->name('analytics.systemPerformance.api');
        Route::get('/analytics/api-metrics', [\App\Http\Controllers\Admin\AnalyticsController::class, 'getApiMetrics'])->name('analytics.apiMetrics.api');
        Route::get('/analytics/error-rates', [\App\Http\Controllers\Admin\AnalyticsController::class, 'getErrorRates'])->name('analytics.errorRates.api');
        Route::post('/analytics/export', [\App\Http\Controllers\Admin\AnalyticsController::class, 'exportAnalytics'])->name('analytics.export.api');

        // FAQ Analytics (additional to existing FAQ routes)
        Route::get('/faq-analytics', [\App\Http\Controllers\Admin\AuthController::class, 'getFaqAnalytics'])->name('getFaqAnalytics.api');

        // Document Management
        Route::post('/upload-document', [\App\Http\Controllers\Admin\DocumentController::class, 'uploadDocument'])->name('uploadDocument.api');
        Route::get('/get-documents', [\App\Http\Controllers\Admin\DocumentController::class, 'getDocuments'])->name('getDocuments.api');
        Route::get('/download-document/{id}', [\App\Http\Controllers\Admin\DocumentController::class, 'downloadDocument'])->name('downloadDocument.api');
        Route::delete('/delete-document/{id}', [\App\Http\Controllers\Admin\DocumentController::class, 'deleteDocument'])->name('deleteDocument.api');

        // Admin Notifications
        Route::post('/send-notification', [\App\Http\Controllers\Admin\NotificationController::class, 'sendNotification'])->name('sendNotification.api');

        // User Management Helpers
        Route::get('/get-users-drop-down', [\App\Http\Controllers\Admin\UserController::class, 'getUsersDropDown'])->name('getUsersDropDown.api');

        // Admin Nutrition Plan Management
        Route::get('/get-nutrition-plans', [\App\Http\Controllers\Admin\NutritionController::class, 'getNutritionPlans'])->name('getNutritionPlans.api');
        Route::post('/nutrition-plans/create', [\App\Http\Controllers\Admin\NutritionController::class, 'createNutritionPlan'])->name('createNutritionPlan.api');
        Route::get('/nutrition-plans/client/{clientId}', [\App\Http\Controllers\Admin\NutritionController::class, 'getClientNutritionPlans'])->name('getClientNutritionPlans.api');
        Route::put('/nutrition-plans/{id}', [\App\Http\Controllers\Admin\NutritionController::class, 'updateNutritionPlan'])->name('updateNutritionPlan.api');
        Route::delete('/nutrition-plans/{id}', [\App\Http\Controllers\Admin\NutritionController::class, 'deleteNutritionPlan'])->name('deleteNutritionPlan.api');

        }); // SECURITY FIX: Close auth:admin middleware HERE (moved from line 155)
            // All routes above (lines 53-374) now require admin authentication

        // SECURITY FIX: These routes moved inside role middleware (were completely public)
        Route::get('/get-organization-by-token/{token}',[\App\Http\Controllers\Admin\OrganizationController::class,'getOrganizationByToken'])->name('getOrganizationByToken.api');
        Route::post('/update-organization/{id}',[\App\Http\Controllers\Admin\OrganizationController::class,'updateOrganization'])->name('updateOrganization.api');
        Route::get('/get-departments-dropdown',[\App\Http\Controllers\Admin\OrganizationController::class,'getDepartmentDropDown'])->name('getDepartmentDropDown.api');

    }); // Close role middleware

});

Route::group(['namespace' => 'Customer', 'prefix' => 'customer', 'as' => 'customer.'], function () {
    Route::post('/login', [\App\Http\Controllers\Customer\AuthController::class, "login"])->name('login');
    Route::post('/verify-register-otp', [\App\Http\Controllers\Customer\AuthController::class, "verifyOTPRegister"])->name("verifyOTPRegister.api");
    Route::post('/send-register-otp', [\App\Http\Controllers\Customer\AuthController::class, "sendOtpForRegister"])->name("sendOtpForRegister.api");
    Route::post('/register', [\App\Http\Controllers\Customer\AuthController::class, "register"])->name("register.api");

    Route::post('/check-availability', [\App\Http\Controllers\Customer\AuthController::class, "checkAvailability"])->name('checkAvailability.api');
    Route::get('/get-dietary-restrictions',[\App\Http\Controllers\Customer\AuthController::class,"getDietaryDropDown"])->name('getDietaryDropDown.api');
    Route::get('/get-training-preferences',[\App\Http\Controllers\Customer\AuthController::class,"getTrainingDropDown"])->name('getTrainingDropDown.api');
    Route::get('/get-equipment-preferences',[\App\Http\Controllers\Customer\AuthController::class,"getEquipmentDropDown"])->name('getEquipmentDropDown.api');
    Route::get('/get-nutrition-calculations',[\App\Http\Controllers\Customer\AuthController::class,"getNutritionCalculations"])->name('getNutritionCalculations.api');
    //Forgot Password
    Route::post('/send-forgot-password-otp',[\App\Http\Controllers\Customer\AuthController::class, "sendForgotOtp"])->name("sendForgotOtp.api");
    Route::post('/verify-forgot-password-otp',[\App\Http\Controllers\Customer\AuthController::class, "verifyForgotOtp"])->name("verifyForgotOtp.api");
    Route::post('/forgot-password',[\App\Http\Controllers\Customer\AuthController::class, "forgotPassword"])->name("forgotPassword.api");

    //SiteInfo
    Route::get('get-site-info',[\App\Http\Controllers\Customer\AuthController::class,'getSiteInfo'])->name('getSiteInfo.api');
    Route::get('/get-faqs', [\App\Http\Controllers\Customer\AuthController::class, "getFaqs"])->name("getFaqs.api");
    Route::group(['middleware' => ['auth:api']],function(){
        //Auth
        Route::get('/logout', [\App\Http\Controllers\Customer\AuthController::class, "logout"])->name("logout.api");
        Route::get('/get-my-profile', [\App\Http\Controllers\Customer\AuthController::class, "getMyProfile"])->name("getMyProfile.api");
        Route::post('/update-profile', [\App\Http\Controllers\Customer\AuthController::class, "updateProfile"])->name("updateProfile.api");
        Route::post('/change-password',[\App\Http\Controllers\Customer\AuthController::class, "changePassword"])->name("changePassword.api");
        //Delete My Account
        Route::delete('/delete-my-account',[\App\Http\Controllers\Customer\AuthController::class,"deleteMyAccount"])->name('deleteMyAccount.api');

        //Report Content
        Route::post('/report-content',[\App\Http\Controllers\Customer\AuthController::class, "reportContent"])->name("reportContent.api");

        //Workouts
        Route::get('/get-my-plans',[\App\Http\Controllers\Customer\WorkoutController::class,'getMyPlans'])->name("getMyPlans.api");
        Route::get('/get-my-workouts',[\App\Http\Controllers\Customer\WorkoutController::class,'getMyWorkouts'])->name("getMyWorkouts.api");
        Route::get('/get-workout/{id}',[\App\Http\Controllers\Customer\WorkoutController::class,'getWorkout'])->name("getWorkout.api");
        Route::get('/get-workout-status',[\App\Http\Controllers\Customer\WorkoutController::class,'getWorkoutStatus'])->name("getWorkoutStatus.api");
        Route::get('/get-exercise/{id}',[\App\Http\Controllers\Customer\WorkoutController::class,'getExercise'])->name("getExercise.api");
        Route::post('/update-workout-exercise-status',[\App\Http\Controllers\Customer\WorkoutController::class,'updateWorkoutExerciseStatus'])->name("updateWorkoutExerciseStatus.api");
        Route::get('/get-tags',[\App\Http\Controllers\Customer\WorkoutController::class,'getTags'])->name("getTags.api");
        
        // Enhanced Workout Features - Groundwork Inspired
        Route::get('/get-workout-equipment/{id}',[\App\Http\Controllers\Customer\WorkoutController::class,'getWorkoutEquipment'])->name("getWorkoutEquipment.api");
        Route::get('/get-exercise-warmup/{id}',[\App\Http\Controllers\Customer\WorkoutController::class,'getExerciseWarmUp'])->name("getExerciseWarmUp.api");
        Route::get('/get-exercise-cooldown/{id}',[\App\Http\Controllers\Customer\WorkoutController::class,'getExerciseCoolDown'])->name("getExerciseCoolDown.api");
        Route::post('/log-workout-calendar',[\App\Http\Controllers\Customer\WorkoutController::class,'logWorkoutToCalendar'])->name("logWorkoutToCalendar.api");
        Route::get('/get-workout-calendar/{date}',[\App\Http\Controllers\Customer\WorkoutController::class,'getWorkoutCalendar'])->name("getWorkoutCalendar.api");
        Route::get('/get-weekly-workouts/{week}',[\App\Http\Controllers\Customer\WorkoutController::class,'getWeeklyWorkouts'])->name("getWeeklyWorkouts.api");
        Route::get('/history',[\App\Http\Controllers\Customer\WorkoutController::class,'getWorkoutHistory'])->name("getWorkoutHistory.api");
        Route::get('/personal-records',[\App\Http\Controllers\Customer\WorkoutController::class,'getPersonalRecords'])->name("getPersonalRecords.api");
        Route::post('/{id}/rate',[\App\Http\Controllers\Customer\WorkoutController::class,'rateWorkout'])->name("rateWorkout.api");
        Route::post('/{id}/favorite',[\App\Http\Controllers\Customer\WorkoutController::class,'toggleFavorite'])->name("toggleFavorite.api");
        Route::get('/favorites',[\App\Http\Controllers\Customer\WorkoutController::class,'getFavorites'])->name("getFavorites.api");
        Route::get('/statistics',[\App\Http\Controllers\Customer\WorkoutController::class,'getStatistics'])->name("getStatistics.api");

        // Workout Session Management
        Route::post('/workout/save', [\App\Http\Controllers\WorkoutSessionController::class, 'saveWorkout']);
        Route::get('/workout/recent', [\App\Http\Controllers\WorkoutSessionController::class, 'getRecentWorkouts']);
        Route::post('/exercise-sets/save', [\App\Http\Controllers\WorkoutSessionController::class, 'saveExerciseSets']);
        Route::post('/workout/save-session', [\App\Http\Controllers\WorkoutSessionController::class, 'saveWorkoutSession']);
        Route::get('/workouts/history', [\App\Http\Controllers\WorkoutSessionController::class, 'getWorkoutHistory']);
        Route::post('/workouts/{id}/complete', [\App\Http\Controllers\WorkoutSessionController::class, 'completeWorkout']);

        // Plan Completion & Progress
        Route::post('/plans/complete', [\App\Http\Controllers\PlanCompletionController::class, 'completePlan']);
        Route::post('/plans/{id}/progress', [\App\Http\Controllers\PlanCompletionController::class, 'updatePlanProgress']);
        Route::get('/user/plans/history', [\App\Http\Controllers\PlanCompletionController::class, 'getUserPlanHistory']);

        //Nutrition Plan
        Route::get('/get-my-nutrition-plan',[\App\Http\Controllers\Customer\DashboardController::class,"getMyNutritionPlan"])->name('getMyNutritionPlan.api');
        Route::get('/get-my-stats',[\App\Http\Controllers\Customer\DashboardController::class,"getMyDashboardStats"])->name('getMyDashboardStats.api');

        //Notifications
        Route::get('/get-notifications', [\App\Http\Controllers\Customer\NotificationController::class, 'getNotifications'])->name('getNotifications.api');
        Route::get('/read-notification/{id}', [\App\Http\Controllers\Customer\NotificationController::class, 'readNotificaiton'])->name('readNotificaiton.api');
        Route::get('/read-all-notifications', [\App\Http\Controllers\Customer\NotificationController::class, 'readAllNotifications'])->name('readAllNotifications.api');

        //Body Points
        Route::get('/get-body-points-history',[\App\Http\Controllers\Customer\AuthController::class,'getBodyPointsHistory'])->name('getBodyPointsHistory.api');

        // ========== NEW: CBT SYSTEM ROUTES ==========
        // CBT Progress & Dashboard
        Route::prefix('cbt')->group(function() {
            Route::get('/progress', [\App\Http\Controllers\Customer\CBTController::class, 'getProgress'])->name('getCBTProgress.api');
            Route::get('/dashboard', [\App\Http\Controllers\Customer\CBTController::class, 'getDashboard'])->name('getCBTDashboard.api');
            Route::get('/stats', [\App\Http\Controllers\Customer\CBTController::class, 'getStats'])->name('getCBTStats.api');

            // CBT Lessons
            Route::get('/lessons/current-week', [\App\Http\Controllers\Customer\CBTController::class, 'getCurrentWeekLessons'])->name('getCurrentWeekLessons.api');
            Route::get('/lessons/{id}', [\App\Http\Controllers\Customer\CBTController::class, 'getLesson'])->name('getCBTLesson.api');
            Route::post('/lessons/{id}/complete', [\App\Http\Controllers\Customer\CBTController::class, 'completeLesson'])->name('completeCBTLesson.api');
            Route::get('/lessons/week/{weekNumber}', [\App\Http\Controllers\Customer\CBTController::class, 'getWeekLessons'])->name('getWeekLessons.api');

            // CBT Journal
            Route::get('/journal/entries', [\App\Http\Controllers\Customer\CBTController::class, 'getJournalEntries'])->name('getJournalEntries.api');
            Route::post('/journal/entries', [\App\Http\Controllers\Customer\CBTController::class, 'createJournalEntry'])->name('createJournalEntry.api');
            Route::get('/journal/entries/{id}', [\App\Http\Controllers\Customer\CBTController::class, 'getJournalEntry'])->name('getJournalEntry.api');
            Route::put('/journal/entries/{id}', [\App\Http\Controllers\Customer\CBTController::class, 'updateJournalEntry'])->name('updateJournalEntry.api');
            Route::delete('/journal/entries/{id}', [\App\Http\Controllers\Customer\CBTController::class, 'deleteJournalEntry'])->name('deleteJournalEntry.api');

            // CBT Assessments
            Route::get('/assessments', [\App\Http\Controllers\Customer\CBTController::class, 'getAssessments'])->name('getCBTAssessments.api');
            Route::post('/assessments', [\App\Http\Controllers\Customer\CBTController::class, 'submitAssessment'])->name('submitCBTAssessment.api');
            Route::get('/assessments/{id}/results', [\App\Http\Controllers\Customer\CBTController::class, 'getAssessmentResults'])->name('getAssessmentResults.api');

            // CBT Goals
            Route::get('/goals', [\App\Http\Controllers\Customer\CBTController::class, 'getGoals'])->name('getCBTGoals.api');
            Route::post('/goals', [\App\Http\Controllers\Customer\CBTController::class, 'createGoal'])->name('createCBTGoal.api');
            Route::put('/goals/{id}', [\App\Http\Controllers\Customer\CBTController::class, 'updateGoal'])->name('updateCBTGoal.api');
            Route::delete('/goals/{id}', [\App\Http\Controllers\Customer\CBTController::class, 'deleteGoal'])->name('deleteCBTGoal.api');

            // CBT Course Hub
            Route::get('/course-hub', [\App\Http\Controllers\Customer\CBTController::class, 'getCourseHub'])->name('getCBTCourseHub.api');
            Route::get('/course-hub/videos', [\App\Http\Controllers\Customer\CBTController::class, 'getCourseVideos'])->name('getCourseVideos.api');

            // CBT Weekly Check-ins
            Route::get('/check-ins', [\App\Http\Controllers\Customer\CBTController::class, 'getCheckIns'])->name('getCBTCheckIns.api');
            Route::post('/check-ins', [\App\Http\Controllers\Customer\CBTController::class, 'submitCheckIn'])->name('submitCBTCheckIn.api');
        });

        // ========== NEW: COACH DASHBOARD ROUTES ==========
        Route::prefix('coach')->group(function() {
            // Coach Dashboard
            Route::get('/dashboard', [\App\Http\Controllers\Coach\DashboardController::class, 'getDashboard'])->name('getCoachDashboard.api');
            Route::get('/dashboard/overview', [\App\Http\Controllers\Coach\DashboardController::class, 'getOverview'])->name('getCoachOverview.api');
            Route::get('/dashboard/stats', [\App\Http\Controllers\Coach\DashboardController::class, 'getStats'])->name('getCoachStats.api');

            // Client Management
            Route::get('/clients', [\App\Http\Controllers\Coach\ClientController::class, 'getClients'])->name('getCoachClients.api');
            Route::get('/clients/{id}', [\App\Http\Controllers\Coach\ClientController::class, 'getClient'])->name('getCoachClient.api');
            Route::get('/clients/{id}/progress', [\App\Http\Controllers\Coach\ClientController::class, 'getClientProgress'])->name('getClientProgress.api');
            Route::get('/clients/{id}/workouts', [\App\Http\Controllers\Coach\ClientController::class, 'getClientWorkouts'])->name('getClientWorkouts.api');
            Route::get('/clients/{id}/nutrition', [\App\Http\Controllers\Coach\ClientController::class, 'getClientNutrition'])->name('getClientNutrition.api');
            Route::get('/clients/{id}/measurements', [\App\Http\Controllers\Coach\ClientController::class, 'getClientMeasurements'])->name('getClientMeasurements.api');
            Route::post('/clients/{id}/notes', [\App\Http\Controllers\Coach\ClientController::class, 'addClientNote'])->name('addClientNote.api');

            // Availability & Scheduling
            Route::get('/{id}/available-slots', [\App\Http\Controllers\Coach\AvailabilityController::class, 'getAvailableSlots'])->name('getCoachAvailableSlots.api');
            Route::post('/availability', [\App\Http\Controllers\Coach\AvailabilityController::class, 'setAvailability'])->name('setCoachAvailability.api');
            Route::get('/availability', [\App\Http\Controllers\Coach\AvailabilityController::class, 'getAvailability'])->name('getCoachAvailability.api');
            Route::put('/availability/{id}', [\App\Http\Controllers\Coach\AvailabilityController::class, 'updateAvailability'])->name('updateCoachAvailability.api');
            Route::delete('/availability/{id}', [\App\Http\Controllers\Coach\AvailabilityController::class, 'deleteAvailability'])->name('deleteCoachAvailability.api');

            // Appointments & Sessions
            Route::get('/appointments', [\App\Http\Controllers\Coach\AppointmentController::class, 'getAppointments'])->name('getCoachAppointments.api');
            Route::post('/appointments', [\App\Http\Controllers\Coach\AppointmentController::class, 'createAppointment'])->name('createCoachAppointment.api');
            Route::get('/appointments/{id}', [\App\Http\Controllers\Coach\AppointmentController::class, 'getAppointment'])->name('getCoachAppointment.api');
            Route::put('/appointments/{id}', [\App\Http\Controllers\Coach\AppointmentController::class, 'updateAppointment'])->name('updateCoachAppointment.api');
            Route::delete('/appointments/{id}', [\App\Http\Controllers\Coach\AppointmentController::class, 'cancelAppointment'])->name('cancelCoachAppointment.api');
            Route::post('/appointments/{id}/complete', [\App\Http\Controllers\Coach\AppointmentController::class, 'completeAppointment'])->name('completeCoachAppointment.api');

            // Workout Plan Assignment
            Route::post('/clients/{id}/assign-workout', [\App\Http\Controllers\Coach\PlanController::class, 'assignWorkout'])->name('assignWorkoutToClient.api');
            Route::post('/clients/{id}/assign-nutrition', [\App\Http\Controllers\Coach\PlanController::class, 'assignNutritionPlan'])->name('assignNutritionToClient.api');

            // Communication
            Route::get('/messages', [\App\Http\Controllers\Coach\MessageController::class, 'getMessages'])->name('getCoachMessages.api');
            Route::post('/messages/{clientId}', [\App\Http\Controllers\Coach\MessageController::class, 'sendMessage'])->name('sendCoachMessage.api');

            // Analytics
            Route::get('/analytics/revenue', [\App\Http\Controllers\Coach\AnalyticsController::class, 'getRevenue'])->name('getCoachRevenue.api');
            Route::get('/analytics/client-retention', [\App\Http\Controllers\Coach\AnalyticsController::class, 'getClientRetention'])->name('getCoachRetention.api');
        });

        // ========== MESSAGING & CONVERSATIONS ROUTES (Legacy Compatibility) ==========
        // Legacy endpoints for frontend calls to /conversations.php and /index.php
        Route::get('/conversations', [\App\Http\Controllers\Coach\MessageController::class, 'getConversations'])->name('getConversations.api');
        Route::put('/messages/update', [\App\Http\Controllers\Coach\MessageController::class, 'updateMessage'])->name('updateMessage.api');

        // ========== NEW: SOCIAL FEATURES ROUTES ==========
        Route::prefix('social')->group(function() {
            // Friends Management
            Route::get('/friends', [\App\Http\Controllers\Customer\SocialController::class, 'getFriends'])->name('getFriends.api');
            Route::post('/friends/request/{userId}', [\App\Http\Controllers\Customer\SocialController::class, 'sendFriendRequest'])->name('sendFriendRequest.api');
            Route::post('/friends/accept/{requestId}', [\App\Http\Controllers\Customer\SocialController::class, 'acceptFriendRequest'])->name('acceptFriendRequest.api');
            Route::post('/friends/reject/{requestId}', [\App\Http\Controllers\Customer\SocialController::class, 'rejectFriendRequest'])->name('rejectFriendRequest.api');
            Route::delete('/friends/{userId}', [\App\Http\Controllers\Customer\SocialController::class, 'removeFriend'])->name('removeFriend.api');
            Route::get('/friends/requests', [\App\Http\Controllers\Customer\SocialController::class, 'getFriendRequests'])->name('getFriendRequests.api');
            Route::get('/friends/suggestions', [\App\Http\Controllers\Customer\SocialController::class, 'getFriendSuggestions'])->name('getFriendSuggestions.api');
            Route::get('/friends/search', [\App\Http\Controllers\Customer\SocialController::class, 'searchUsers'])->name('searchUsers.api');
            Route::get('/friend-request/sent', [\App\Http\Controllers\Customer\SocialController::class, 'getSentRequests'])->name('getSentRequests.api');

            // Friend Discovery
            Route::post('/discover-friends', [\App\Http\Controllers\Customer\SocialController::class, 'discoverFriends'])->name('discoverFriends.api');
            Route::post('/add-contacts', [\App\Http\Controllers\Customer\SocialController::class, 'addContactsForDiscovery'])->name('addContacts.api');

            // Connection Settings
            Route::put('/connection/{id}/settings', [\App\Http\Controllers\Customer\SocialController::class, 'updateConnectionSettings'])->name('updateConnectionSettings.api');
            Route::post('/block-user', [\App\Http\Controllers\Customer\SocialController::class, 'blockUser'])->name('blockUser.api');

            // Activity Feed
            Route::get('/activity-feed', [\App\Http\Controllers\Customer\SocialController::class, 'getActivityFeed'])->name('getActivityFeed.api');
            Route::post('/activity-feed', [\App\Http\Controllers\Customer\SocialController::class, 'postActivity'])->name('postActivity.api');
            Route::post('/activity-feed/{id}/like', [\App\Http\Controllers\Customer\SocialController::class, 'likeActivity'])->name('likeActivity.api');
            Route::delete('/activity-feed/{id}/like', [\App\Http\Controllers\Customer\SocialController::class, 'unlikeActivity'])->name('unlikeActivity.api');
            Route::get('/activity-feed/{id}/comments', [\App\Http\Controllers\Customer\SocialController::class, 'getComments'])->name('getComments.api');
            Route::post('/activity-feed/{id}/comment', [\App\Http\Controllers\Customer\SocialController::class, 'commentActivity'])->name('commentActivity.api');
            Route::delete('/activity-feed/{id}', [\App\Http\Controllers\Customer\SocialController::class, 'deleteActivity'])->name('deleteActivity.api');

            // User Profiles
            Route::get('/user/{userId}/profile', [\App\Http\Controllers\Customer\SocialController::class, 'getUserProfile'])->name('getUserProfile.api');
            Route::get('/user/{userId}/stats', [\App\Http\Controllers\Customer\SocialController::class, 'getUserStats'])->name('getUserStats.api');
            Route::get('/user/{userId}/achievements', [\App\Http\Controllers\Customer\SocialController::class, 'getUserAchievements'])->name('getUserAchievements.api');

            // Leaderboard
            Route::get('/leaderboard', [\App\Http\Controllers\Customer\SocialController::class, 'getLeaderboard'])->name('getSocialLeaderboard.api');
            Route::get('/leaderboard/friends', [\App\Http\Controllers\Customer\SocialController::class, 'getFriendsLeaderboard'])->name('getFriendsLeaderboard.api');
            Route::get('/leaderboard/organization', [\App\Http\Controllers\Customer\SocialController::class, 'getOrganizationLeaderboard'])->name('getOrgLeaderboard.api');

            // Challenges
            Route::get('/challenges', [\App\Http\Controllers\Customer\SocialController::class, 'getSocialChallenges'])->name('getSocialChallenges.api');
        });

        // ========== NEW: ANALYTICS ROUTES ==========
        Route::prefix('analytics')->group(function() {
            // User Analytics
            Route::get('/overview', [\App\Http\Controllers\Customer\AnalyticsController::class, 'getOverview'])->name('getAnalyticsOverview.api');
            Route::get('/progress', [\App\Http\Controllers\Customer\AnalyticsController::class, 'getProgress'])->name('getUserProgress.api');
            Route::get('/workout-stats', [\App\Http\Controllers\Customer\AnalyticsController::class, 'getWorkoutStats'])->name('getWorkoutStats.api');
            Route::get('/nutrition-stats', [\App\Http\Controllers\Customer\AnalyticsController::class, 'getNutritionStats'])->name('getNutritionStats.api');
            Route::get('/body-composition', [\App\Http\Controllers\Customer\AnalyticsController::class, 'getBodyComposition'])->name('getBodyComposition.api');
            Route::get('/weekly-summary', [\App\Http\Controllers\Customer\AnalyticsController::class, 'getWeeklySummary'])->name('getWeeklySummary.api');
            Route::get('/monthly-report', [\App\Http\Controllers\Customer\AnalyticsController::class, 'getMonthlyReport'])->name('getMonthlyReport.api');

            // Achievements & Goals
            Route::get('/achievements', [\App\Http\Controllers\Customer\AnalyticsController::class, 'getAchievements'])->name('getAchievements.api');
            Route::get('/goals', [\App\Http\Controllers\Customer\AnalyticsController::class, 'getGoals'])->name('getAnalyticsGoals.api');
            Route::post('/goals', [\App\Http\Controllers\Customer\AnalyticsController::class, 'createGoal'])->name('createAnalyticsGoal.api');
            Route::put('/goals/{id}', [\App\Http\Controllers\Customer\AnalyticsController::class, 'updateGoal'])->name('updateAnalyticsGoal.api');
            Route::post('/goals/{id}/complete', [\App\Http\Controllers\Customer\AnalyticsController::class, 'completeGoal'])->name('completeAnalyticsGoal.api');

            // Streaks & Consistency
            Route::get('/streaks', [\App\Http\Controllers\Customer\AnalyticsController::class, 'getStreaks'])->name('getStreaks.api');
            Route::get('/consistency-score', [\App\Http\Controllers\Customer\AnalyticsController::class, 'getConsistencyScore'])->name('getConsistencyScore.api');

            // Comparison & Benchmarks
            Route::get('/compare/period', [\App\Http\Controllers\Customer\AnalyticsController::class, 'comparePeriods'])->name('comparePeriods.api');
            Route::get('/benchmarks', [\App\Http\Controllers\Customer\AnalyticsController::class, 'getBenchmarks'])->name('getBenchmarks.api');

            // Export
            Route::post('/export/pdf', [\App\Http\Controllers\Customer\AnalyticsController::class, 'exportPDF'])->name('exportAnalyticsPDF.api');
            Route::post('/export/csv', [\App\Http\Controllers\Customer\AnalyticsController::class, 'exportCSV'])->name('exportAnalyticsCSV.api');

            // Body Points & Gamification
            Route::get('/body-points', [\App\Http\Controllers\Customer\AnalyticsController::class, 'getBodyPoints'])->name('getAnalyticsBodyPoints.api');
            Route::get('/body-points/history', [\App\Http\Controllers\Customer\AnalyticsController::class, 'getBodyPointsHistory'])->name('getAnalyticsBodyPointsHistory.api');
            Route::get('/badges', [\App\Http\Controllers\Customer\AnalyticsController::class, 'getBadges'])->name('getBadges.api');
            Route::get('/level', [\App\Http\Controllers\Customer\AnalyticsController::class, 'getUserLevel'])->name('getUserLevel.api');
        });

        //Leaderboard
        Route::post('/leaderboard',[\App\Http\Controllers\Customer\DashboardController::class,'getLeaderboard'])->name('getLeaderboard.api');
        Route::post('/user-rank',[\App\Http\Controllers\Customer\DashboardController::class,'getUserRank'])->name('getUserRank.api');

        // Enhanced Workout Plan (Calendar Integration)
        Route::get('/get-user-workout-plan-v2', [\App\Http\Controllers\Customer\WorkoutController::class, 'getUserWorkoutPlanV2'])->name('getUserWorkoutPlanV2.api');

        //Nutrition Tracking
        Route::get('/get-daily-nutrition',[\App\Http\Controllers\Customer\NutritionController::class,'getDailyNutrition'])->name('getDailyNutrition.api');
        Route::post('/update-nutrition-intake',[\App\Http\Controllers\Customer\NutritionController::class,'updateNutritionIntake'])->name('updateNutritionIntake.api');
        Route::post('/sync-health-app-data',[\App\Http\Controllers\Customer\NutritionController::class,'syncHealthAppData'])->name('syncHealthAppData.api');
        Route::get('/get-nutrition-goals',[\App\Http\Controllers\Customer\NutritionController::class,'getNutritionGoals'])->name('getNutritionGoals.api');
        Route::post('/update-nutrition-goals',[\App\Http\Controllers\Customer\NutritionController::class,'updateNutritionGoals'])->name('updateNutritionGoals.api');
        Route::get('/get-nutrition-history',[\App\Http\Controllers\Customer\NutritionController::class,'getNutritionHistory'])->name('getNutritionHistory.api');
        Route::post('/analyze-photo',[\App\Http\Controllers\Customer\NutritionController::class,'analyzePhoto'])->name('analyzePhoto.api');
        Route::post('/swap-meal/{mealId}',[\App\Http\Controllers\Customer\NutritionController::class,'swapMeal'])->name('swapMeal.api');

        // User Profile & Preferences Management - Agent 3
        Route::get('/profile', [\App\Http\Controllers\Customer\ProfileController::class, 'getProfile'])->name('getProfile.api');
        Route::put('/profile', [\App\Http\Controllers\Customer\ProfileController::class, 'updateProfile'])->name('updateProfileNew.api');

        // Dietary Restrictions
        Route::get('/get-dietary-restrictions', [\App\Http\Controllers\Customer\ProfileController::class, 'getDietaryRestrictions'])->name('getUserDietaryRestrictions.api');
        Route::post('/update-dietary-restrictions', [\App\Http\Controllers\Customer\ProfileController::class, 'updateDietaryRestrictions'])->name('updateUserDietaryRestrictions.api');

        // Training Preferences
        Route::get('/get-training-preferences', [\App\Http\Controllers\Customer\ProfileController::class, 'getTrainingPreferences'])->name('getUserTrainingPreferences.api');
        Route::post('/update-training-preferences', [\App\Http\Controllers\Customer\ProfileController::class, 'updateTrainingPreferences'])->name('updateUserTrainingPreferences.api');

        // Equipment Preferences
        Route::get('/get-equipment-preferences', [\App\Http\Controllers\Customer\ProfileController::class, 'getEquipmentPreferences'])->name('getUserEquipmentPreferences.api');
        Route::post('/update-equipment-preferences', [\App\Http\Controllers\Customer\ProfileController::class, 'updateEquipmentPreferences'])->name('updateUserEquipmentPreferences.api');

        // Notification Preferences
        Route::get('/get-notification-preferences', [\App\Http\Controllers\Customer\ProfileController::class, 'getNotificationPreferences'])->name('getNotificationPreferences.api');
        Route::post('/update-notification-preferences', [\App\Http\Controllers\Customer\ProfileController::class, 'updateNotificationPreferences'])->name('updateNotificationPreferences.api');

        // Privacy Settings
        Route::get('/get-privacy-settings', [\App\Http\Controllers\Customer\ProfileController::class, 'getPrivacySettings'])->name('getPrivacySettings.api');
        Route::post('/update-privacy-settings', [\App\Http\Controllers\Customer\ProfileController::class, 'updatePrivacySettings'])->name('updatePrivacySettings.api');

        // Data Export & Account Deletion
        Route::get('/export-data', [\App\Http\Controllers\Customer\ProfileController::class, 'exportData'])->name('exportData.api');
        Route::delete('/account', [\App\Http\Controllers\Customer\ProfileController::class, 'deleteAccount'])->name('deleteAccount.api');

    }); // END Customer auth:api middleware group

}); // END Customer namespace group

// SABOTAGE FIX: These routes were incorrectly placed inside Customer namespace group
// They need their own auth:api group WITHOUT the Customer namespace
Route::middleware(['auth:api'])->group(function() {

    // Gamification Endpoints
    Route::prefix('gamification')->group(function() {
        // Streaks
        Route::get('/streaks', [\App\Http\Controllers\GamificationController::class, 'getStreaks'])->name('getStreaks.api');
        Route::post('/streaks/update', [\App\Http\Controllers\GamificationController::class, 'updateStreak'])->name('updateStreak.api');

        // Achievements
        Route::get('/achievements', [\App\Http\Controllers\GamificationController::class, 'getAchievements'])->name('getAchievements.api');

        // Badges
        Route::get('/badges', [\App\Http\Controllers\GamificationController::class, 'getBadges'])->name('getBadges.api');

        // Leaderboard
        Route::get('/leaderboard', [\App\Http\Controllers\GamificationController::class, 'getLeaderboard'])->name('getLeaderboard.api');

        // Points History
        Route::get('/points/history', [\App\Http\Controllers\GamificationController::class, 'getPointsHistory'])->name('getPointsHistory.api');

        // Dashboard Overview
        Route::get('/dashboard', [\App\Http\Controllers\GamificationController::class, 'getDashboard'])->name('getGamificationDashboard.api');
    });

    // Weight Tracking Endpoints
    Route::prefix('weight')->group(function() {
        Route::get('/history', [\App\Http\Controllers\WeightTrackingController::class, 'getWeightHistory'])->name('getWeightHistory.api');
        Route::post('/log', [\App\Http\Controllers\WeightTrackingController::class, 'logWeight'])->name('logWeight.api');
        Route::get('/stats', [\App\Http\Controllers\WeightTrackingController::class, 'getWeightStats'])->name('getWeightStats.api');
        Route::get('/goals', [\App\Http\Controllers\WeightTrackingController::class, 'getWeightGoals'])->name('getWeightGoals.api');
        Route::post('/goals', [\App\Http\Controllers\WeightTrackingController::class, 'updateWeightGoal'])->name('updateWeightGoal.api');
        Route::delete('/log/{id}', [\App\Http\Controllers\WeightTrackingController::class, 'deleteWeightLog'])->name('deleteWeightLog.api');
    });

    // Progression Photos (Client Self-Upload)
    Route::prefix('progression-photos')->group(function() {
        Route::get('/', [\App\Http\Controllers\WeightTrackingController::class, 'getProgressionPhotos'])->name('getProgressionPhotos.api');
        Route::post('/', [\App\Http\Controllers\WeightTrackingController::class, 'uploadProgressionPhoto'])->name('uploadProgressionPhoto.api')->middleware(['throttle.upload']);
        Route::delete('/{photoId}', [\App\Http\Controllers\WeightTrackingController::class, 'deleteProgressionPhoto'])->name('deleteProgressionPhoto.api');
    });

    // Health Metrics Dashboard Endpoints
    Route::prefix('health')->group(function() {
        Route::get('/today', [\App\Http\Controllers\Api\HealthMetricsController::class, 'getToday'])->name('getHealthToday.api');
        Route::get('/date/{date}', [\App\Http\Controllers\Api\HealthMetricsController::class, 'getForDate'])->name('getHealthForDate.api');
        Route::post('/sync-to-analytics', [\App\Http\Controllers\Api\HealthMetricsController::class, 'syncToCoachAnalytics'])->name('syncToCoachAnalytics.api');
    });

    // Specialized Workout Types - Advanced Tracking
    Route::prefix('specialized-workouts')->group(function() {
        // AMRAP Workouts
        Route::post('/amrap/create', [\App\Http\Controllers\SpecializedWorkoutController::class, 'createAMRAP'])->name('createAMRAP.api');
        Route::post('/amrap/{id}/log', [\App\Http\Controllers\SpecializedWorkoutController::class, 'logAMRAP'])->name('logAMRAP.api');
        Route::get('/amrap/history/{userId}', [\App\Http\Controllers\SpecializedWorkoutController::class, 'getAMRAPHistory'])->name('getAMRAPHistory.api');

        // EMOM Workouts
        Route::post('/emom/create', [\App\Http\Controllers\SpecializedWorkoutController::class, 'createEMOM'])->name('createEMOM.api');
        Route::post('/emom/{id}/log', [\App\Http\Controllers\SpecializedWorkoutController::class, 'logEMOM'])->name('logEMOM.api');
        Route::get('/emom/history/{userId}', [\App\Http\Controllers\SpecializedWorkoutController::class, 'getEMOMHistory'])->name('getEMOMHistory.api');

        // RFT Workouts
        Route::post('/rft/create', [\App\Http\Controllers\SpecializedWorkoutController::class, 'createRFT'])->name('createRFT.api');
        Route::post('/rft/{id}/log', [\App\Http\Controllers\SpecializedWorkoutController::class, 'logRFT'])->name('logRFT.api');
        Route::get('/rft/history/{userId}', [\App\Http\Controllers\SpecializedWorkoutController::class, 'getRFTHistory'])->name('getRFTHistory.api');

        // Tabata Workouts
        Route::post('/tabata/create', [\App\Http\Controllers\SpecializedWorkoutController::class, 'createTabata'])->name('createTabata.api');
        Route::post('/tabata/{id}/log', [\App\Http\Controllers\SpecializedWorkoutController::class, 'logTabata'])->name('logTabata.api');
        Route::get('/tabata/history/{userId}', [\App\Http\Controllers\SpecializedWorkoutController::class, 'getTabataHistory'])->name('getTabataHistory.api');

        // HIIT Workouts
        Route::post('/hiit/create', [\App\Http\Controllers\SpecializedWorkoutController::class, 'createHIIT'])->name('createHIIT.api');
        Route::post('/hiit/{id}/log', [\App\Http\Controllers\SpecializedWorkoutController::class, 'logHIIT'])->name('logHIIT.api');
        Route::get('/hiit/history/{userId}', [\App\Http\Controllers\SpecializedWorkoutController::class, 'getHIITHistory'])->name('getHIITHistory.api');

        // Circuit Workouts
        Route::post('/circuit/create', [\App\Http\Controllers\SpecializedWorkoutController::class, 'createCircuit'])->name('createCircuit.api');
        Route::post('/circuit/{id}/log', [\App\Http\Controllers\SpecializedWorkoutController::class, 'logCircuit'])->name('logCircuit.api');
        Route::get('/circuit/history/{userId}', [\App\Http\Controllers\SpecializedWorkoutController::class, 'getCircuitHistory'])->name('getCircuitHistory.api');

        // Superset Workouts
        Route::post('/superset/create', [\App\Http\Controllers\SpecializedWorkoutController::class, 'createSuperset'])->name('createSuperset.api');
        Route::post('/superset/{id}/log', [\App\Http\Controllers\SpecializedWorkoutController::class, 'logSuperset'])->name('logSuperset.api');
        Route::get('/superset/history/{userId}', [\App\Http\Controllers\SpecializedWorkoutController::class, 'getSupersetHistory'])->name('getSupersetHistory.api');

        // Pyramid Workouts
        Route::post('/pyramid/create', [\App\Http\Controllers\SpecializedWorkoutController::class, 'createPyramid'])->name('createPyramid.api');
        Route::post('/pyramid/{id}/log', [\App\Http\Controllers\SpecializedWorkoutController::class, 'logPyramid'])->name('logPyramid.api');
        Route::get('/pyramid/history/{userId}', [\App\Http\Controllers\SpecializedWorkoutController::class, 'getPyramidHistory'])->name('getPyramidHistory.api');

        // CHIPPER Workouts
        Route::post('/chipper/create', [\App\Http\Controllers\SpecializedWorkoutController::class, 'createChipper'])->name('createChipper.api');
        Route::post('/chipper/{id}/log', [\App\Http\Controllers\SpecializedWorkoutController::class, 'logChipper'])->name('logChipper.api');
        Route::get('/chipper/history/{userId}', [\App\Http\Controllers\SpecializedWorkoutController::class, 'getChipperHistory'])->name('getChipperHistory.api');

        // Drop Set Workouts
        Route::post('/drop-set/create', [\App\Http\Controllers\SpecializedWorkoutController::class, 'createDropSet'])->name('createDropSet.api');
        Route::post('/drop-set/{id}/log', [\App\Http\Controllers\SpecializedWorkoutController::class, 'logDropSet'])->name('logDropSet.api');
        Route::get('/drop-set/history/{userId}', [\App\Http\Controllers\SpecializedWorkoutController::class, 'getDropSetHistory'])->name('getDropSetHistory.api');

        // Unified Endpoints
        Route::get('/all-types/{userId}', [\App\Http\Controllers\SpecializedWorkoutController::class, 'getAllWorkoutTypes'])->name('getAllWorkoutTypes.api');
        Route::get('/stats/{userId}/{type}', [\App\Http\Controllers\SpecializedWorkoutController::class, 'getWorkoutTypeStats'])->name('getWorkoutTypeStats.api');
    });

}); // END Gamification/Weight/Health/Specialized auth:api middleware group

// SECURITY FIX: Chat endpoints now require authentication
// Previously these were public (potential malware leftover) - anyone could read private messages
Route::middleware(['auth:api'])->group(function() {
    Route::get('/get-inbox-chat', [\App\Http\Controllers\ChatController::class, "getInboxChat"])->name("getInboxChat.api");
    Route::get('/get-my-inbox', [\App\Http\Controllers\ChatController::class, "getMyInboxChats"])->name("getMyInboxChats.api");
    Route::get('/get-inbox/{id}', [\App\Http\Controllers\ChatController::class, "getInboxChatbyID"])->name("getInboxChatbyID.api");
    Route::post('/send-message', [\App\Http\Controllers\ChatController::class, "sendMessage"])->name("sendMessage.api");

    // SECURITY: Removed test-broadcast endpoint (production security hardening)
});

// Wearables (HealthKit & Google Fit) Sync API Routes
Route::prefix('wearables')->middleware(['auth:api'])->group(function () {
    Route::post('/sync/activity', [\App\Http\Controllers\Api\WearablesController::class, 'syncActivity']);
    Route::post('/sync/steps', [\App\Http\Controllers\Api\WearablesController::class, 'syncSteps']);
    Route::post('/sync/heart-rate', [\App\Http\Controllers\Api\WearablesController::class, 'syncHeartRate']);
    Route::post('/sync/blood-pressure', [\App\Http\Controllers\Api\WearablesController::class, 'syncBloodPressure']);
    Route::post('/sync/weight', [\App\Http\Controllers\Api\WearablesController::class, 'syncWeight']);
    Route::post('/sync/sleep', [\App\Http\Controllers\Api\WearablesController::class, 'syncSleep']);
    Route::post('/sync/distance', [\App\Http\Controllers\Api\WearablesController::class, 'syncDistance']);
    Route::post('/sync/nutrition', [\App\Http\Controllers\Api\WearablesController::class, 'syncNutrition']);
    Route::post('/sync/bulk', [\App\Http\Controllers\Api\WearablesController::class, 'syncBulk']);
});

// Passio Nutrition API Routes
Route::prefix('passio')->middleware(['throttle.passio'])->group(function () {
    Route::get('/ping', function () {
        return response()->json([
            'status'  => 'ok',
            'service' => 'passio-ping',
            'message' => 'Passio ping endpoint is working',
            'timestamp' => date('Y-m-d H:i:s'),
            'config_loaded' => config('services.passio.base_url') ? 'yes' : 'no',
        ]);
    });
    Route::get('/fetch-meal-plan', [\App\Http\Controllers\PassioNutritionController::class, 'fetchMealPlan']);
    Route::post('/sync-nutrition', [\App\Http\Controllers\PassioNutritionController::class, 'syncNutrition']);
    Route::get('/search-foods', [\App\Http\Controllers\PassioNutritionController::class, 'searchFoods']);
    Route::post('/search-food', [\App\Http\Controllers\PassioNutritionController::class, 'searchFoods']); // Legacy compatibility
    Route::get('/food-by-barcode/{barcode}', [\App\Http\Controllers\PassioNutritionController::class, 'getFoodByBarcode']);
    Route::get('/daily-nutrition/{userId}/{date}', [\App\Http\Controllers\PassioNutritionController::class, 'getDailyNutrition']);
    Route::post('/voice-log-food', [\App\Http\Controllers\PassioNutritionController::class, 'voiceLogFood']);

    // New endpoints for legacy compatibility
    Route::post('/recognize-food', [\App\Http\Controllers\PassioNutritionController::class, 'recognizeFood']);
    Route::post('/generate-meal-plan', [\App\Http\Controllers\PassioNutritionController::class, 'generateMealPlan']);
});

// Passio Advanced Features - CAMERA & Complete Integration
Route::prefix('passio/advanced')->middleware(['auth:api', 'throttle.passio'])->group(function () {
    // CAMERA FOOD RECOGNITION - Main Feature
    Route::post('/recognize-food', [\App\Http\Controllers\PassioAdvancedController::class, 'recognizeFoodFromImage']);

    // Barcode Scanning
    Route::post('/scan-barcode', [\App\Http\Controllers\PassioAdvancedController::class, 'scanBarcode']);

    // Food Database
    Route::post('/search-database', [\App\Http\Controllers\PassioAdvancedController::class, 'searchFoodDatabase']);

    // Food Logging
    Route::post('/log-food', [\App\Http\Controllers\PassioAdvancedController::class, 'logFood']);

    // Recipe Analysis
    Route::post('/analyze-recipe', [\App\Http\Controllers\PassioAdvancedController::class, 'analyzeRecipe']);

    // AI Meal Suggestions
    Route::post('/meal-suggestions', [\App\Http\Controllers\PassioAdvancedController::class, 'getMealSuggestionsAI']);

    // Water Tracking
    Route::post('/log-water', [\App\Http\Controllers\PassioAdvancedController::class, 'logWaterIntake']);

    // Nutrition Goals
    Route::post('/set-goals', [\App\Http\Controllers\PassioAdvancedController::class, 'setNutritionGoals']);

    // Food Alternatives
    Route::post('/food-alternatives', [\App\Http\Controllers\PassioAdvancedController::class, 'getFoodAlternativesAPI']);

    // Nutrition Trends & Insights
    Route::post('/nutrition-trends', [\App\Http\Controllers\PassioAdvancedController::class, 'getNutritionTrends']);
});

// Passio Enhanced Meal Plan & Food Features - Phase 7
Route::prefix('passio/meal-plan')->middleware(['auth:api'])->group(function () {
    // AI Meal Plan Generation
    Route::post('/generate', [\App\Http\Controllers\PassioMealPlanController::class, 'generateAIMealPlan']);

    // Food Substitutions
    Route::post('/food/substitutions', [\App\Http\Controllers\PassioMealPlanController::class, 'getFoodSubstitutions']);
    Route::post('/food/find-by-nutrition', [\App\Http\Controllers\PassioMealPlanController::class, 'findFoodByNutrition']);

    // Enhanced Food Search & Barcode
    Route::get('/food/search', [\App\Http\Controllers\PassioMealPlanController::class, 'searchFood']);
    Route::get('/barcode/scan/{barcode}', [\App\Http\Controllers\PassioMealPlanController::class, 'scanBarcode']);
    Route::get('/nutrition/{foodId}', [\App\Http\Controllers\PassioMealPlanController::class, 'getNutritionData']);

    // Recommendations & Analysis
    Route::post('/recommendations', [\App\Http\Controllers\PassioMealPlanController::class, 'getFoodRecommendations']);
    Route::post('/meal/analyze', [\App\Http\Controllers\PassioMealPlanController::class, 'analyzeMeal']);

    // Popular Foods & Portion Validation
    Route::get('/foods/popular', [\App\Http\Controllers\PassioMealPlanController::class, 'getPopularFoods']);
    Route::post('/portion/validate', [\App\Http\Controllers\PassioMealPlanController::class, 'validatePortion']);

    // Connectivity Test
    Route::get('/ping', [\App\Http\Controllers\PassioMealPlanController::class, 'ping']);
});

// Coach Ken Chat Routes
Route::prefix('coach-ken')->group(function () {
    Route::get('/access', function () {
        return response()->json(['success' => true, 'message' => 'Access granted']);
    });

    Route::get('/history', function () {
        return response()->json([
            'success' => true,
            'data' => ['messages' => []]
        ]);
    });

    Route::post('/chat', function (Request $request) {
        return response()->json([
            'success' => true,
            'message' => 'Hello! I\'m Coach Ken. How can I help you today?'
        ]);
    });

    Route::delete('/clear', function () {
        return response()->json(['success' => true]);
    });
});

// CBT (Cognitive Behavioral Therapy) API Routes
Route::prefix('cbt')->middleware(['auth:api'])->group(function () {
    Route::get('/progress', [\App\Http\Controllers\CBTController::class, 'getCBTProgress']);
    Route::get('/lessons/current-week', [\App\Http\Controllers\CBTController::class, 'getCurrentWeekLessons']);
    Route::get('/lessons/all', [\App\Http\Controllers\CBTController::class, 'getAllLessons']);
    Route::get('/lessons/{id}', [\App\Http\Controllers\CBTController::class, 'getLesson']);
    Route::post('/lessons/{id}/complete', [\App\Http\Controllers\CBTController::class, 'completeLesson']);
    Route::get('/exercises/{lessonId}', [\App\Http\Controllers\CBTController::class, 'getExercises']);
    Route::post('/exercises/{id}/complete', [\App\Http\Controllers\CBTController::class, 'completeExercise']);
    Route::post('/journal/entry', [\App\Http\Controllers\CBTController::class, 'createJournalEntry']);
    Route::get('/journal/entries', [\App\Http\Controllers\CBTController::class, 'getJournalEntries']);
    Route::put('/journal/entry/{id}', [\App\Http\Controllers\CBTController::class, 'updateJournalEntry']);
    Route::delete('/journal/entry/{id}', [\App\Http\Controllers\CBTController::class, 'deleteJournalEntry']);
    Route::get('/assessments', [\App\Http\Controllers\CBTController::class, 'getAssessments']);
    Route::post('/assessments/{id}/submit', [\App\Http\Controllers\CBTController::class, 'submitAssessment']);
    Route::get('/goals', [\App\Http\Controllers\CBTController::class, 'getGoals']);
    Route::post('/goals', [\App\Http\Controllers\CBTController::class, 'createGoal']);
    Route::put('/goals/{id}', [\App\Http\Controllers\CBTController::class, 'updateGoal']);
    Route::get('/insights', [\App\Http\Controllers\CBTController::class, 'getInsights']);

    // New Routes - Daily Lessons, Calendar, Videos, Scheduling
    Route::get('/daily-lesson', [\App\Http\Controllers\CBTController::class, 'getDailyLesson']);
    Route::get('/calendar', [\App\Http\Controllers\CBTController::class, 'getCalendar']);
    Route::get('/calendar/month', [\App\Http\Controllers\CBTController::class, 'getMonthCalendar']);
    Route::get('/videos', [\App\Http\Controllers\CBTController::class, 'getVideoLibrary']);
    Route::get('/videos/{id}', [\App\Http\Controllers\CBTController::class, 'getVideo']);
    Route::get('/schedule', [\App\Http\Controllers\CBTController::class, 'getSchedule']);
    Route::post('/assessments/schedule', [\App\Http\Controllers\CBTController::class, 'scheduleAssessment']);
    Route::get('/assessments/scheduled', [\App\Http\Controllers\CBTController::class, 'getScheduledAssessments']);
    Route::get('/points', [\App\Http\Controllers\CBTController::class, 'getPoints']);
    Route::get('/points/history', [\App\Http\Controllers\CBTController::class, 'getPointsHistory']);
});

// CBT Alias Routes - Frontend compatibility (frontend uses different paths)
Route::middleware(['auth:api'])->group(function () {
    // Alias: /user/progress -> /cbt/progress
    Route::get('/user/progress', [\App\Http\Controllers\CBTController::class, 'getCBTProgress']);

    // Alias: /upcoming-tests -> /cbt/assessments
    Route::get('/upcoming-tests', [\App\Http\Controllers\CBTController::class, 'getAssessments']);

    // Daily lesson and achievements endpoints
    Route::get('/daily-lesson', [\App\Http\Controllers\CBTController::class, 'getDailyLesson']);
    Route::get('/user/achievements', [\App\Http\Controllers\Admin\UserController::class, 'getUserAchievements']);
});

// Mobile App Features - Device Management & Push Notifications
Route::prefix('mobile')->middleware(['auth:api'])->group(function () {
    // Device Registration
    Route::post('/device/register', [\App\Http\Controllers\MobileController::class, 'registerDevice']);

    // Push Notification Settings
    Route::post('/push-settings', [\App\Http\Controllers\MobileController::class, 'updatePushSettings']);

    // App Configuration
    Route::get('/config', [\App\Http\Controllers\MobileController::class, 'getConfig']);

    // Offline Sync
    Route::post('/sync-offline', [\App\Http\Controllers\MobileController::class, 'syncOfflineData']);

    // Updates System
    Route::get('/updates', [\App\Http\Controllers\MobileController::class, 'getUpdates']);

    // Error Logging
    Route::post('/error-log', [\App\Http\Controllers\MobileController::class, 'logError']);

    // App Settings
    Route::post('/settings', [\App\Http\Controllers\MobileController::class, 'updateSettings']);
});

// Social & Community Features
Route::prefix('social')->middleware(['auth:api'])->group(function () {
    // Posts & Feed
    Route::post('/posts', [\App\Http\Controllers\SocialCommunityController::class, 'createPost']);
    Route::get('/feed', [\App\Http\Controllers\SocialCommunityController::class, 'getFeed']);
    Route::post('/like', [\App\Http\Controllers\SocialCommunityController::class, 'toggleLike']);
    Route::post('/comment', [\App\Http\Controllers\SocialCommunityController::class, 'addComment']);

    // Follow System
    Route::post('/follow', [\App\Http\Controllers\SocialCommunityController::class, 'toggleFollow']);
    Route::get('/profile/{userId}', [\App\Http\Controllers\SocialCommunityController::class, 'getUserProfile']);
    Route::post('/search-users', [\App\Http\Controllers\SocialCommunityController::class, 'searchUsers']);

    // Leaderboard & Achievements
    Route::get('/leaderboard', [\App\Http\Controllers\SocialCommunityController::class, 'getLeaderboard']);
    Route::post('/share-achievement', [\App\Http\Controllers\SocialCommunityController::class, 'shareAchievement']);

    // Groups
    Route::post('/groups', [\App\Http\Controllers\SocialCommunityController::class, 'createGroup']);
    Route::post('/groups/join', [\App\Http\Controllers\SocialCommunityController::class, 'joinGroup']);

    // New Social Endpoints (Missing from completeness assessment)
    Route::post('/posts/{id}/report', [\App\Http\Controllers\SocialController::class, 'reportPost']);
    Route::get('/trending', [\App\Http\Controllers\SocialController::class, 'getTrendingPosts']);
    Route::post('/groups/{id}/invite', [\App\Http\Controllers\SocialController::class, 'inviteToGroup']);
    Route::get('/suggested-friends', [\App\Http\Controllers\SocialController::class, 'getSuggestedFriends']);
    Route::post('/profile/visibility-settings', [\App\Http\Controllers\SocialController::class, 'updateVisibilitySettings']);
    Route::delete('/posts/{id}', [\App\Http\Controllers\SocialController::class, 'deletePost']);
});

// Challenge Management
Route::prefix('challenges')->middleware(['auth:api'])->group(function () {
    Route::get('/available', [\App\Http\Controllers\ChallengeManagementController::class, 'getAvailableChallenges']);
    Route::post('/join', [\App\Http\Controllers\ChallengeManagementController::class, 'joinChallenge']);
    Route::post('/update-progress', [\App\Http\Controllers\ChallengeManagementController::class, 'updateProgress']);
    Route::get('/my-active', [\App\Http\Controllers\ChallengeManagementController::class, 'getMyActiveChallenges']);
    Route::get('/{id}/leaderboard', [\App\Http\Controllers\ChallengeManagementController::class, 'getChallengeLeaderboard']);
    Route::post('/leave', [\App\Http\Controllers\ChallengeManagementController::class, 'leaveChallenge']);
    Route::get('/history', [\App\Http\Controllers\ChallengeManagementController::class, 'getChallengeHistory']);
    Route::post('/create-custom', [\App\Http\Controllers\ChallengeManagementController::class, 'createCustomChallenge']);
    Route::post('/{challengeId}/invite', [\App\Http\Controllers\ChallengeManagementController::class, 'inviteFriend']);
    Route::get('/recommended', [\App\Http\Controllers\ChallengeManagementController::class, 'getRecommended']);
});

// Payment & Subscription Management
Route::prefix('payments')->middleware(['auth:api'])->group(function () {
    Route::get('/plans', [\App\Http\Controllers\PaymentSubscriptionController::class, 'getSubscriptionPlans']);
    Route::post('/subscribe', [\App\Http\Controllers\PaymentSubscriptionController::class, 'subscribe']);
    Route::get('/subscription', [\App\Http\Controllers\PaymentSubscriptionController::class, 'getCurrentSubscription']);
    Route::post('/subscription/cancel', [\App\Http\Controllers\PaymentSubscriptionController::class, 'cancelSubscription']);
    Route::post('/subscription/change', [\App\Http\Controllers\PaymentSubscriptionController::class, 'changeSubscription']);
    Route::get('/history', [\App\Http\Controllers\PaymentSubscriptionController::class, 'getPaymentHistory']);
    Route::get('/invoices', [\App\Http\Controllers\PaymentSubscriptionController::class, 'getInvoices']);
    Route::get('/invoices/{id}/download', [\App\Http\Controllers\PaymentSubscriptionController::class, 'downloadInvoice']);
    Route::post('/coupon/validate', [\App\Http\Controllers\PaymentSubscriptionController::class, 'validateCoupon']);
    Route::post('/payment-method/update', [\App\Http\Controllers\PaymentSubscriptionController::class, 'updatePaymentMethod']);
    Route::post('/refund/request', [\App\Http\Controllers\PaymentSubscriptionController::class, 'requestRefund']);
});

Route::prefix('billing')->middleware(['auth:api'])->group(function () {
    // Setup Intents
    Route::post('/setup-intent', [\App\Http\Controllers\Api\StripePaymentController::class, 'createSetupIntent']);

    // Payment Methods
    Route::post('/payment-methods', [\App\Http\Controllers\Api\StripePaymentController::class, 'addPaymentMethod']);
    Route::get('/payment-methods', [\App\Http\Controllers\Api\StripePaymentController::class, 'getPaymentMethods']);
    Route::post('/payment-methods/{id}/default', [\App\Http\Controllers\Api\StripePaymentController::class, 'setDefaultPaymentMethod']);
    Route::delete('/payment-methods/{id}', [\App\Http\Controllers\Api\StripePaymentController::class, 'deletePaymentMethod']);

    // Subscriptions
    Route::post('/subscriptions', [\App\Http\Controllers\Api\StripePaymentController::class, 'createSubscription']);
    Route::get('/subscription', [\App\Http\Controllers\Api\StripePaymentController::class, 'getSubscription']);
    Route::put('/subscription', [\App\Http\Controllers\Api\StripePaymentController::class, 'updateSubscription']);
    Route::delete('/subscription', [\App\Http\Controllers\Api\StripePaymentController::class, 'cancelSubscription']);

    // Invoices
    Route::get('/invoices', [\App\Http\Controllers\Api\StripePaymentController::class, 'getInvoices']);
    Route::get('/invoices/{id}/pdf', [\App\Http\Controllers\Api\StripePaymentController::class, 'downloadInvoicePDF']);
    Route::post('/invoices/{id}/email', [\App\Http\Controllers\Api\StripePaymentController::class, 'emailInvoice']);

    // Surcharging
    Route::post('/calculate-surcharge', [\App\Http\Controllers\Api\StripePaymentController::class, 'calculateSurcharge']);
    Route::get('/surcharge-config', [\App\Http\Controllers\Api\StripePaymentController::class, 'getSurchargeConfig']);

    // Analytics & Reporting
    Route::get('/analytics', [\App\Http\Controllers\Api\StripePaymentController::class, 'getBillingAnalytics']);
    Route::get('/history', [\App\Http\Controllers\Api\StripePaymentController::class, 'getPaymentHistory']);

    // New Billing Endpoints (Missing from completeness assessment)
    Route::post('/payment-methods/{id}/verify', [\App\Http\Controllers\Api\StripePaymentController::class, 'verifyPaymentMethod']);
    Route::get('/subscription/upgrade-options', [\App\Http\Controllers\Api\StripePaymentController::class, 'getUpgradeOptions']);
    Route::post('/subscription/pause', [\App\Http\Controllers\Api\StripePaymentController::class, 'pauseSubscription']);
    Route::post('/subscription/resume', [\App\Http\Controllers\Api\StripePaymentController::class, 'resumeSubscription']);
    Route::get('/invoices/{id}/disputes', [\App\Http\Controllers\Api\StripePaymentController::class, 'getInvoiceDisputes']);
    Route::post('/refund/{id}/request', [\App\Http\Controllers\Api\StripePaymentController::class, 'requestRefund']);
});

// Stripe Webhooks (NO CSRF protection - Stripe signature verification handles security)
Route::post('/stripe/webhook', [\App\Http\Controllers\Api\StripeWebhookController::class, 'handleWebhook']);

Route::prefix('coach')->middleware(['auth:api'])->group(function () {
    Route::post('/connect-stripe', [\App\Http\Controllers\Api\CoachStripeConnectController::class, 'connectStripe']);
    Route::get('/stripe-status', [\App\Http\Controllers\Api\CoachStripeConnectController::class, 'getStripeStatus']);
    Route::get('/earnings', [\App\Http\Controllers\Api\CoachStripeConnectController::class, 'getEarnings']);
    Route::post('/payout', [\App\Http\Controllers\Api\CoachStripeConnectController::class, 'requestPayout']);
    Route::get('/payouts', [\App\Http\Controllers\Api\CoachStripeConnectController::class, 'getPayoutHistory']);
});

// Organization Leader - Payment Access (NO individual user data access)
Route::prefix('organization-leader')->middleware(['auth:api'])->group(function () {
    // Leader Status
    Route::get('/is-leader', [\App\Http\Controllers\Api\OrganizationLeaderController::class, 'isOrganizationLeader']);
    Route::get('/{organizationId}', [\App\Http\Controllers\Api\OrganizationLeaderController::class, 'getOrganizationLeader']);
    Route::post('/{organizationId}/set-leader', [\App\Http\Controllers\Api\OrganizationLeaderController::class, 'setOrganizationLeader']);

    // Aggregate Data ONLY (NO individual user data)
    // Leaders can see overall: fat loss, dieting, CBT metrics
    // Leaders CANNOT see individual users (privacy/legal compliance)
    Route::get('/{organizationId}/aggregate-data', [\App\Http\Controllers\Api\OrganizationLeaderController::class, 'getOrganizationAggregateData']);
});

// Admin Payment Controls
Route::prefix('admin/payment-controls')->middleware(['auth:api', 'role:admin'])->group(function () {
    // Payment Status Dashboards
    Route::get('/organizations', [\App\Http\Controllers\Api\AdminPaymentControlsController::class, 'getOrganizationPaymentStatuses']);
    Route::get('/coaches', [\App\Http\Controllers\Api\AdminPaymentControlsController::class, 'getCoachPaymentStatuses']);

    // Individual Toggles
    Route::post('/organizations/{id}/toggle', [\App\Http\Controllers\Api\AdminPaymentControlsController::class, 'toggleOrganizationStatus']);
    Route::post('/coaches/{id}/toggle', [\App\Http\Controllers\Api\AdminPaymentControlsController::class, 'toggleCoachStatus']);

    // Bulk Actions
    Route::post('/organizations/bulk-toggle', [\App\Http\Controllers\Api\AdminPaymentControlsController::class, 'bulkToggleOrganizationsByPayment']);
    Route::post('/coaches/bulk-toggle', [\App\Http\Controllers\Api\AdminPaymentControlsController::class, 'bulkToggleCoachesByPayment']);

    // Payment Reminders
    Route::post('/send-reminder', [\App\Http\Controllers\Api\AdminPaymentControlsController::class, 'sendPaymentReminder']);

    // Admin Document Storage (ONLY admins, others get email)
    Route::post('/invoices/store-document', [\App\Http\Controllers\Api\AdminPaymentControlsController::class, 'storeInvoiceDocument']);
    Route::get('/invoices/documents', [\App\Http\Controllers\Api\AdminPaymentControlsController::class, 'getAdminInvoiceDocuments']);
    Route::get('/invoices/{id}/download-document', [\App\Http\Controllers\Api\AdminPaymentControlsController::class, 'downloadAdminInvoiceDocument']);
});

// Weekly Check-ins
Route::prefix('weekly-checkins')->middleware(['auth:api'])->group(function () {
    // Client endpoints
    Route::get('/my-checkins', [\App\Http\Controllers\WeeklyCheckinController::class, 'getClientCheckins']);
    Route::post('/', [\App\Http\Controllers\WeeklyCheckinController::class, 'createCheckin']);
    Route::put('/{id}', [\App\Http\Controllers\WeeklyCheckinController::class, 'updateCheckin']);
    Route::post('/{id}/submit', [\App\Http\Controllers\WeeklyCheckinController::class, 'submitCheckin']);
    Route::post('/{id}/upload-photos', [\App\Http\Controllers\WeeklyCheckinController::class, 'uploadPhotos'])->middleware(['throttle.upload']);
    Route::get('/stats/{clientId?}', [\App\Http\Controllers\WeeklyCheckinController::class, 'getClientStats']);

    // Coach endpoints
    Route::get('/coach/checkins', [\App\Http\Controllers\WeeklyCheckinController::class, 'getCoachCheckins']);
    Route::post('/{id}/feedback', [\App\Http\Controllers\WeeklyCheckinController::class, 'provideFeedback']);

    // Shared endpoints
    Route::get('/{id}', [\App\Http\Controllers\WeeklyCheckinController::class, 'getCheckin']);
    Route::delete('/{id}', [\App\Http\Controllers\WeeklyCheckinController::class, 'deleteCheckin']);
});

// Progress Report PDF Generation
Route::prefix('progress-reports')->middleware(['auth:api'])->group(function () {
    Route::post('/generate', [\App\Http\Controllers\ProgressReportController::class, 'generateProgressReport']);
    Route::post('/stream', [\App\Http\Controllers\ProgressReportController::class, 'streamProgressReport']);
});

// Analytics & Reporting
Route::prefix('analytics')->middleware(['auth:api'])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\AnalyticsReportingController::class, 'getDashboardAnalytics']);
    Route::get('/workouts', [\App\Http\Controllers\AnalyticsReportingController::class, 'getWorkoutAnalytics']);
    Route::get('/nutrition', [\App\Http\Controllers\AnalyticsReportingController::class, 'getNutritionAnalyticsReport']);
    Route::get('/body-composition', [\App\Http\Controllers\AnalyticsReportingController::class, 'getBodyCompositionAnalytics']);
    Route::get('/goal-progress', [\App\Http\Controllers\AnalyticsReportingController::class, 'getGoalProgressReport']);
    Route::get('/weekly-report', [\App\Http\Controllers\AnalyticsReportingController::class, 'getWeeklyReport']);
    Route::post('/export', [\App\Http\Controllers\AnalyticsReportingController::class, 'exportData']);
});

// Mobile App Specific
Route::prefix('mobile')->middleware(['auth:api'])->group(function () {
    Route::post('/device/register', [\App\Http\Controllers\MobileAppController::class, 'registerDevice']);
    Route::post('/push-settings', [\App\Http\Controllers\MobileAppController::class, 'updatePushSettings']);
    Route::get('/config', [\App\Http\Controllers\MobileAppController::class, 'getAppConfig']);
    Route::post('/sync-offline', [\App\Http\Controllers\MobileAppController::class, 'syncOfflineData']);
    Route::get('/updates', [\App\Http\Controllers\MobileAppController::class, 'getUpdatesSince']);
    Route::post('/error-log', [\App\Http\Controllers\MobileAppController::class, 'logAppError']);
    Route::post('/cached-data', [\App\Http\Controllers\MobileAppController::class, 'getCachedData']);
    Route::post('/settings', [\App\Http\Controllers\MobileAppController::class, 'updateAppSettings']);
    Route::post('/analytics', [\App\Http\Controllers\MobileAppController::class, 'recordUsageAnalytics']);
});

Route::middleware(['auth:api'])->group(function() {
    Route::post('/passio/recognize-food', [\App\Http\Controllers\PassioProxyController::class, 'recognizeFood']);
    Route::post('/passio/search-food', [\App\Http\Controllers\PassioProxyController::class, 'searchFood']);
    Route::post('/passio/generate-meal-plan', [\App\Http\Controllers\PassioProxyController::class, 'generateMealPlan']);
    Route::post('/api/passio-nutrition-info', [\App\Http\Controllers\PassioProxyController::class, 'getNutritionInfo']);
});

// Nutrition Plan Management (Coach CRUD)
Route::middleware(['auth:api'])->group(function () {
    Route::post('/nutrition-plans', [\App\Http\Controllers\NutritionPlanController::class, 'store']);
    Route::get('/nutrition-plans/{id}', [\App\Http\Controllers\NutritionPlanController::class, 'show']);
    Route::get('/coaches/nutrition-plans', [\App\Http\Controllers\NutritionPlanController::class, 'index']);
    Route::put('/nutrition-plans/{id}', [\App\Http\Controllers\NutritionPlanController::class, 'update']);
    Route::delete('/nutrition-plans/{id}', [\App\Http\Controllers\NutritionPlanController::class, 'destroy']);
    Route::post('/coaches/assign-meal-plan', [\App\Http\Controllers\NutritionPlanController::class, 'assign']);

    // Workout Plan Management (Coach CRUD)
    Route::post('/workout-plans', [\App\Http\Controllers\WorkoutPlanController::class, 'store']);
    Route::get('/workout-plans/{id}', [\App\Http\Controllers\WorkoutPlanController::class, 'show']);
    Route::get('/coaches/workout-plans', [\App\Http\Controllers\WorkoutPlanController::class, 'index']);
    Route::put('/workout-plans/{id}', [\App\Http\Controllers\WorkoutPlanController::class, 'update']);
    Route::delete('/workout-plans/{id}', [\App\Http\Controllers\WorkoutPlanController::class, 'destroy']);
    Route::post('/coaches/assign-workout-plan', [\App\Http\Controllers\WorkoutPlanController::class, 'assign']);

    // Library System - Dual Browse (Public + Private)
    Route::prefix('library')->group(function () {
        // Browse endpoints - merges public library + coach's private content
        Route::get('/workouts', [\App\Http\Controllers\LibraryController::class, 'browseWorkouts']);
        Route::get('/nutrition-plans', [\App\Http\Controllers\LibraryController::class, 'browseNutritionPlans']);
        Route::get('/challenges', [\App\Http\Controllers\LibraryController::class, 'browseChallenges']);
        Route::get('/fitness-videos', [\App\Http\Controllers\LibraryController::class, 'browseFitnessVideos']);
        Route::get('/nutrition-videos', [\App\Http\Controllers\LibraryController::class, 'browseNutritionVideos']);
        Route::get('/mindset-videos', [\App\Http\Controllers\LibraryController::class, 'browseMindsetVideos']);
        Route::get('/notification-videos', [\App\Http\Controllers\LibraryController::class, 'browseNotificationVideos']);

        // Clone from public library to coach's private collection
        Route::post('/clone', [\App\Http\Controllers\LibraryController::class, 'cloneFromLibrary']);
    });

    // Social Networking & Friends System
    Route::prefix('social')->group(function () {
        // Friend Discovery
        Route::post('/discover-friends', [\App\Http\Controllers\SocialController::class, 'discoverFriends']);
        Route::post('/add-contacts', [\App\Http\Controllers\SocialController::class, 'addContactsForDiscovery']);

        // Friend Connections
        Route::post('/friend-request', [\App\Http\Controllers\SocialController::class, 'sendFriendRequest']);
        Route::post('/friend-request/{id}/accept', [\App\Http\Controllers\SocialController::class, 'acceptFriendRequest']);
        Route::post('/friend-request/{id}/reject', [\App\Http\Controllers\SocialController::class, 'rejectFriendRequest']);
        Route::get('/friends', [\App\Http\Controllers\SocialController::class, 'getFriends']);
        Route::get('/friend-requests', [\App\Http\Controllers\SocialController::class, 'getPendingRequests']);
        Route::put('/connection/{id}/settings', [\App\Http\Controllers\SocialController::class, 'updateConnectionSettings']);
        Route::delete('/friend/{id}', [\App\Http\Controllers\SocialController::class, 'removeFriend']);
        Route::post('/block-user', [\App\Http\Controllers\SocialController::class, 'blockUser']);

        // Activity Feed
        Route::get('/activity-feed', [\App\Http\Controllers\SocialController::class, 'getActivityFeed']);
    });

    // Social Sharing & Rewards
    Route::prefix('share')->group(function () {
        // Create and manage shares
        Route::post('/', [\App\Http\Controllers\SocialShareController::class, 'createShare']);
        Route::get('/my-shares', [\App\Http\Controllers\SocialShareController::class, 'getMyShares']);
        Route::get('/friends-shares', [\App\Http\Controllers\SocialShareController::class, 'getFriendsShares']);
        Route::post('/{id}/like', [\App\Http\Controllers\SocialShareController::class, 'likeShare']);
        Route::get('/stats', [\App\Http\Controllers\SocialShareController::class, 'getShareStats']);
        Route::delete('/{id}', [\App\Http\Controllers\SocialShareController::class, 'deleteShare']);
    });

    // Avatar & 3D Customization
    Route::prefix('avatar')->group(function () {
        // Catalog and inventory
        Route::get('/catalog', [\App\Http\Controllers\AvatarController::class, 'getCatalog']);
        Route::get('/my-items', [\App\Http\Controllers\AvatarController::class, 'getMyItems']);
        Route::get('/equipped', [\App\Http\Controllers\AvatarController::class, 'getEquippedAvatar']);
        Route::get('/equipped/{userId}', [\App\Http\Controllers\AvatarController::class, 'getEquippedAvatar']);
        Route::get('/stats', [\App\Http\Controllers\AvatarController::class, 'getAvatarStats']);

        // Item management
        Route::post('/unlock', [\App\Http\Controllers\AvatarController::class, 'unlockItem']);
        Route::post('/equip', [\App\Http\Controllers\AvatarController::class, 'equipItem']);
        Route::post('/unequip', [\App\Http\Controllers\AvatarController::class, 'unequipItem']);

        // Social sharing rewards
        Route::post('/share/reward/claim', [\App\Http\Controllers\AvatarController::class, 'claimSocialShareReward']);
        Route::get('/share/stats', [\App\Http\Controllers\AvatarController::class, 'getSocialShareStats']);
    });

    // Video Streaming - Secure signed URLs with access control
    Route::prefix('videos')->group(function () {
        Route::get('/stream/{id}', [\App\Http\Controllers\Admin\VideoController::class, 'streamVideo']);
        Route::get('/{id}/thumbnail', [\App\Http\Controllers\Admin\VideoController::class, 'getThumbnail']);
    });
});

// Admin routes for Avatar item management
Route::middleware(['auth:admin', 'role'])->group(function () {
    Route::prefix('admin/avatar')->group(function () {
        Route::post('/items', [\App\Http\Controllers\AvatarController::class, 'adminCreateItem']);
        Route::put('/items/{id}', [\App\Http\Controllers\AvatarController::class, 'adminUpdateItem']);
        Route::delete('/items/{id}', [\App\Http\Controllers\AvatarController::class, 'adminDeleteItem']);
    });
});

/*
|--------------------------------------------------------------------------
| AI Assistant Routes (BodyF1rst CRM Integration)
|--------------------------------------------------------------------------
|
| Unified AI endpoints for workout creation, nutrition planning, client analytics,
| scheduling, and messaging. Integrates CRM AI agents with main backend API.
|
| Mobile & Web Compatible - All endpoints support both platforms
|
*/

Route::middleware(['auth:api'])->prefix('ai')->group(function () {

    // Main AI Chat Interface (Web + Mobile)
    Route::post('/chat', [\App\Http\Controllers\AiAssistantController::class, 'chat'])
        ->name('ai.chat');

    // AI Capabilities Discovery
    Route::get('/capabilities', [\App\Http\Controllers\AiAssistantController::class, 'getCapabilities'])
        ->name('ai.capabilities');

    // Voice Commands (Mobile App)
    Route::post('/voice', [\App\Http\Controllers\AiAssistantController::class, 'processVoiceCommand'])
        ->name('ai.voice');

    // Workout AI
    Route::prefix('workout')->group(function () {
        Route::post('/create', [\App\Http\Controllers\AiAssistantController::class, 'createWorkout'])
            ->name('ai.workout.create');
    });

    // Nutrition AI
    Route::prefix('nutrition')->group(function () {
        Route::post('/create', [\App\Http\Controllers\AiAssistantController::class, 'createNutritionPlan'])
            ->name('ai.nutrition.create');
    });

    // Client Analytics AI
    Route::prefix('analytics')->group(function () {
        Route::get('/client/{clientId}', [\App\Http\Controllers\AiAssistantController::class, 'getClientAnalytics'])
            ->name('ai.analytics.client');
    });

    // Scheduling AI
    Route::prefix('schedule')->group(function () {
        Route::post('/book', [\App\Http\Controllers\AiAssistantController::class, 'scheduleAppointment'])
            ->name('ai.schedule.book');
    });

    // Messaging AI
    Route::prefix('messages')->group(function () {
        Route::post('/draft', [\App\Http\Controllers\AiAssistantController::class, 'draftMessage'])
            ->name('ai.messages.draft');
    });

    // Legacy AI Coach Endpoints (for backward compatibility with frontend)
    Route::post('/process-message', [\App\Http\Controllers\AiAssistantController::class, 'processMessage'])
        ->name('ai.process-message');
    Route::post('/save-conversation', [\App\Http\Controllers\AiAssistantController::class, 'saveConversation'])
        ->name('ai.save-conversation');
    Route::get('/get-chat-history', [\App\Http\Controllers\AiAssistantController::class, 'getChatHistory'])
        ->name('ai.get-chat-history');

    // AI Scheduling Endpoints
    Route::post('/schedule-workout', [\App\Http\Controllers\AiAssistantController::class, 'scheduleWorkout'])
        ->name('ai.schedule-workout');
    Route::post('/schedule-meal', [\App\Http\Controllers\AiAssistantController::class, 'scheduleMeal'])
        ->name('ai.schedule-meal');
    Route::post('/schedule-task', [\App\Http\Controllers\AiAssistantController::class, 'scheduleTask'])
        ->name('ai.schedule-task');
    Route::post('/parse-scheduling-command', [\App\Http\Controllers\AiAssistantController::class, 'parseSchedulingCommand'])
        ->name('ai.parse-scheduling-command');
    Route::post('/process-workout-command', [\App\Http\Controllers\AiAssistantController::class, 'processWorkoutCommand'])
        ->name('ai.process-workout-command');

    // Calendar Integration
    Route::post('/sync-apple-calendar', [\App\Http\Controllers\AiAssistantController::class, 'syncAppleCalendar'])
        ->name('ai.sync-apple-calendar');
    Route::get('/get-scheduled-events', [\App\Http\Controllers\AiAssistantController::class, 'getScheduledEvents'])
        ->name('ai.get-scheduled-events');
});

/*
|--------------------------------------------------------------------------
| Analytics Routes (AGENT-4)
|--------------------------------------------------------------------------
|
| Analytics dashboards, reports, and data exports
|
*/

Route::middleware(['auth:api'])->prefix('analytics')->group(function () {
    // Core Analytics
    Route::get('/dashboard', [\App\Http\Controllers\AnalyticsController::class, 'getDashboardAnalytics']);
    Route::get('/workouts', [\App\Http\Controllers\AnalyticsController::class, 'getWorkoutAnalytics']);
    Route::get('/nutrition', [\App\Http\Controllers\AnalyticsController::class, 'getNutritionAnalytics']);
    Route::get('/body-composition', [\App\Http\Controllers\AnalyticsController::class, 'getBodyCompositionAnalytics']);

    // Advanced Analytics
    Route::get('/goal-progress', [\App\Http\Controllers\AnalyticsController::class, 'getGoalProgressReport']);
    Route::get('/weekly-report', [\App\Http\Controllers\AnalyticsController::class, 'getWeeklyReport']);

    // Export
    Route::post('/export', [\App\Http\Controllers\AnalyticsController::class, 'exportAnalyticsData']);
});

/*
|--------------------------------------------------------------------------
| Organization & Department Routes (AGENT-4)
|--------------------------------------------------------------------------
|
| Department management, rewards, and organization analytics
|
*/

Route::middleware(['auth:admin', 'role'])->prefix('admin')->group(function () {
    // Department Management
    Route::get('/get-departments', [\App\Http\Controllers\Admin\DepartmentController::class, 'getDepartments']);
    Route::post('/add-department', [\App\Http\Controllers\Admin\DepartmentController::class, 'addDepartment']);
    Route::post('/update-department/{id}', [\App\Http\Controllers\Admin\DepartmentController::class, 'updateDepartment']);
    Route::get('/get-department/{id}', [\App\Http\Controllers\Admin\DepartmentController::class, 'getDepartmentDetails']);
    Route::delete('/delete-department/{id}', [\App\Http\Controllers\Admin\DepartmentController::class, 'deleteDepartment']);

    // Rewards Management
    Route::get('/get-rewards', [\App\Http\Controllers\Admin\DepartmentController::class, 'getRewards']);
    Route::post('/add-reward', [\App\Http\Controllers\Admin\DepartmentController::class, 'addReward']);
    Route::post('/update-reward/{id}', [\App\Http\Controllers\Admin\DepartmentController::class, 'updateReward']);
    Route::get('/get-reward/{id}', [\App\Http\Controllers\Admin\DepartmentController::class, 'getRewardDetails']);

    // Organization Analytics
    Route::get('/get-analytics-dashboard/{organizationId}', [\App\Http\Controllers\Admin\DepartmentController::class, 'getOrganizationAnalyticsDashboard']);

    // Dashboard Enhancements
    Route::get('/get-activity-logs', [\App\Http\Controllers\Admin\DashboardController::class, 'getActivityLogs']);
    Route::get('/get-performance-metrics', [\App\Http\Controllers\Admin\DashboardController::class, 'getPerformanceMetrics']);
    Route::get('/get-recent-activity', [\App\Http\Controllers\Admin\DashboardController::class, 'getRecentActivity']);
    Route::get('/get-system-health', [\App\Http\Controllers\Admin\DashboardController::class, 'getSystemHealth']);
    Route::get('/dashboard-stats', [\App\Http\Controllers\Admin\DashboardController::class, 'getExtendedDashboardStats']);

    // File Management
    Route::post('/upload-document', [\App\Http\Controllers\FileManagementController::class, 'uploadDocument']);
    Route::get('/get-documents', [\App\Http\Controllers\FileManagementController::class, 'getDocuments']);
    Route::get('/download-document/{id}', [\App\Http\Controllers\FileManagementController::class, 'downloadDocument'])->name('document.download');
    Route::delete('/delete-document/{id}', [\App\Http\Controllers\FileManagementController::class, 'deleteDocument']);

    // Notifications
    Route::post('/send-notification', [\App\Http\Controllers\NotificationController::class, 'sendNotification']);
    Route::get('/get-users-drop-down', [\App\Http\Controllers\NotificationController::class, 'getUsersDropDown']);

    // FAQ Management
    Route::get('/faq-analytics', [\App\Http\Controllers\FAQController::class, 'getFAQAnalytics']);
    Route::post('/faqs', [\App\Http\Controllers\FAQController::class, 'createFAQ']);
    Route::put('/faqs/{id}', [\App\Http\Controllers\FAQController::class, 'updateFAQ']);
    Route::delete('/faqs/{id}', [\App\Http\Controllers\FAQController::class, 'deleteFAQ']);
});

/*
|--------------------------------------------------------------------------
| Chat & Messaging Routes (AGENT-4)
|--------------------------------------------------------------------------
|
| Enhanced chat and messaging with pagination
|
*/

Route::middleware(['auth:api'])->prefix('chat')->group(function () {
    Route::get('/get-messages', [\App\Http\Controllers\ChatController::class, 'getMessages']);
});

/*
|--------------------------------------------------------------------------
| Avatar System Routes (AGENT-4)
|--------------------------------------------------------------------------
|
| 3D Avatar creation and customization
|
*/

Route::middleware(['auth:api'])->prefix('avatar')->group(function () {
    Route::post('/create-3d-avatar', [\App\Http\Controllers\AvatarController::class, 'create3DAvatar']);
    Route::put('/update-3d-avatar', [\App\Http\Controllers\AvatarController::class, 'update3DAvatar']);
});

/*
|--------------------------------------------------------------------------
| Wearables Routes (AGENT-4)
|--------------------------------------------------------------------------
|
| Enhanced wearable data sync
|
*/

Route::middleware(['auth:api'])->prefix('wearables')->group(function () {
    Route::post('/sync/bulk-multi-day', [\App\Http\Controllers\Api\WearablesController::class, 'syncBulkMultiDay']);
    Route::get('/sync-status', [\App\Http\Controllers\Api\WearablesController::class, 'getSyncStatus']);
});

/*
|--------------------------------------------------------------------------
| FAQ Routes (AGENT-4)
|--------------------------------------------------------------------------
|
| Public FAQ access with analytics
|
*/

Route::prefix('faqs')->group(function () {
    Route::get('/', [\App\Http\Controllers\FAQController::class, 'getFAQs']);
    Route::get('/{id}', [\App\Http\Controllers\FAQController::class, 'getFAQById']);
    Route::post('/{id}/feedback', [\App\Http\Controllers\FAQController::class, 'submitFeedback'])->middleware('auth:api');
});

/*
|--------------------------------------------------------------------------
| Calendar Routes - Comprehensive Calendar System
|--------------------------------------------------------------------------
|
| Enhanced calendar features for both coaches and customers
| Includes: events, scheduling, availability, streaks, external sync
|
*/

// Coach Calendar Routes
Route::prefix('calendar/coach')->middleware(['auth:coach'])->group(function () {
    Route::get('/overview', [\App\Http\Controllers\CalendarController::class, 'coachOverview'])->name('calendar.coach.overview');
    Route::get('/month/{year}/{month}', [\App\Http\Controllers\CalendarController::class, 'coachMonthView'])->name('calendar.coach.month');
    Route::get('/week', [\App\Http\Controllers\CalendarController::class, 'coachWeekView'])->name('calendar.coach.week');
    Route::get('/day/{date}', [\App\Http\Controllers\CalendarController::class, 'coachDayView'])->name('calendar.coach.day');
    Route::get('/events', [\App\Http\Controllers\CalendarController::class, 'coachEvents'])->name('calendar.coach.events');
    Route::post('/block-time', [\App\Http\Controllers\CalendarController::class, 'blockTime'])->name('calendar.coach.blockTime');
    Route::get('/availability', [\App\Http\Controllers\CalendarController::class, 'coachAvailability'])->name('calendar.coach.availability');
    Route::post('/reschedule/{appointmentId}', [\App\Http\Controllers\CalendarController::class, 'rescheduleAppointment'])->name('calendar.coach.reschedule');
});

// Customer/Mobile Calendar Routes
Route::prefix('calendar')->middleware(['auth:api'])->group(function () {
    Route::get('/my-calendar', [\App\Http\Controllers\CalendarController::class, 'myCalendar'])->name('calendar.myCalendar');
    Route::get('/month/{year}/{month}', [\App\Http\Controllers\CalendarController::class, 'monthView'])->name('calendar.month');
    Route::get('/week', [\App\Http\Controllers\CalendarController::class, 'weekView'])->name('calendar.week');
    Route::get('/day/{date}', [\App\Http\Controllers\CalendarController::class, 'dayView'])->name('calendar.day');
    Route::post('/add-event', [\App\Http\Controllers\CalendarController::class, 'addEvent'])->name('calendar.addEvent');
    Route::get('/upcoming', [\App\Http\Controllers\CalendarController::class, 'upcoming'])->name('calendar.upcoming');
    Route::get('/streaks', [\App\Http\Controllers\CalendarController::class, 'streaks'])->name('calendar.streaks');
    Route::post('/sync', [\App\Http\Controllers\CalendarController::class, 'syncExternalCalendar'])->name('calendar.sync');
});

/*
|--------------------------------------------------------------------------
| Coach Photo Processing Routes - AI Enhancement System
|--------------------------------------------------------------------------
|
| Process real coach photos with AI enhancements:
| - Background removal (Remove.bg API)
| - Person removal (AI inpainting)
| - BodyF1rst logo overlay
| - Algorithmic art backgrounds
| - Full composition pipeline
|
*/

Route::prefix('coach-photos')->group(function () {
    // Complete processing workflow
    Route::post('/process', [\App\Http\Controllers\CoachPhotoController::class, 'processPhoto'])
        ->name('coach-photos.process');

    // Batch process multiple coaches
    Route::post('/process-batch', [\App\Http\Controllers\CoachPhotoController::class, 'processBatch'])
        ->name('coach-photos.processBatch');

    // Upload and process in one request
    Route::post('/upload-and-process', [\App\Http\Controllers\CoachPhotoController::class, 'uploadAndProcess'])
        ->name('coach-photos.uploadAndProcess');

    // Individual processing steps
    Route::post('/remove-background', [\App\Http\Controllers\CoachPhotoController::class, 'removeBackground'])
        ->name('coach-photos.removeBackground');

    Route::post('/generate-background', [\App\Http\Controllers\CoachPhotoController::class, 'generateBackground'])
        ->name('coach-photos.generateBackground');

    Route::post('/add-logo', [\App\Http\Controllers\CoachPhotoController::class, 'addLogo'])
        ->name('coach-photos.addLogo');

    Route::post('/composite', [\App\Http\Controllers\CoachPhotoController::class, 'composite'])
        ->name('coach-photos.composite');

    // Utility endpoints
    Route::get('/credits', [\App\Http\Controllers\CoachPhotoController::class, 'getCredits'])
        ->name('coach-photos.credits');
});

/*
|--------------------------------------------------------------------------
| Avatar Animation Routes
|--------------------------------------------------------------------------
|
| Routes for animating coach/owner avatars using AIML API (ByteDance Models):
| - OmniHuman 1.5: Audio-driven lip-sync animation
| - Seedance 1.0 Pro: Text-to-video and image-to-video animation
| - Batch processing for multiple avatars
|
*/

Route::prefix('avatar-animation')->group(function () {
    // Primary animation endpoint (OmniHuman 1.5)
    Route::post('/animate', [\App\Http\Controllers\AvatarAnimationController::class, 'animateAvatar'])
        ->name('avatar-animation.animate');

    // Upload files and animate
    Route::post('/upload-and-animate', [\App\Http\Controllers\AvatarAnimationController::class, 'uploadAndAnimate'])
        ->name('avatar-animation.uploadAndAnimate');

    // Batch animate multiple avatars
    Route::post('/batch', [\App\Http\Controllers\AvatarAnimationController::class, 'batchAnimate'])
        ->name('avatar-animation.batch');

    // Alternative: Seedance animation (image-to-video)
    Route::post('/seedance', [\App\Http\Controllers\AvatarAnimationController::class, 'animateWithSeedance'])
        ->name('avatar-animation.seedance');

    // Check generation status
    Route::get('/status/{generationId}', [\App\Http\Controllers\AvatarAnimationController::class, 'getStatus'])
        ->name('avatar-animation.status');

    // Get available animation presets
    Route::get('/presets', [\App\Http\Controllers\AvatarAnimationController::class, 'getPresets'])
        ->name('avatar-animation.presets');

    // Get API credits/usage
    Route::get('/credits', [\App\Http\Controllers\AvatarAnimationController::class, 'getCredits'])
        ->name('avatar-animation.credits');
});



/*
|--------------------------------------------------------------------------
| NEW ENHANCED BACKEND ENDPOINTS - 2025 EXPANSION
|--------------------------------------------------------------------------
| Added to support frontend features that were missing backend implementation
*/

// ===== COACH NUTRITION ANALYTICS =====
Route::prefix('coaches/analytics/nutrition')->middleware('auth:api')->group(function () {
    Route::get('/overview', [\App\Http\Controllers\CoachNutritionAnalyticsController::class, 'getOverview']);
    Route::get('/compliance', [\App\Http\Controllers\CoachNutritionAnalyticsController::class, 'getComplianceAnalytics']);
    Route::get('/macros', [\App\Http\Controllers\CoachNutritionAnalyticsController::class, 'getMacroAnalytics']);
});

// ===== COACH CLIENT NUTRITION TRACKING =====
Route::prefix('coaches/clients')->middleware('auth:api')->group(function () {
    Route::get('/{id}/nutrition/daily', [\App\Http\Controllers\CoachClientNutritionController::class, 'getDailyLogs']);
    Route::get('/{id}/nutrition/weekly', [\App\Http\Controllers\CoachClientNutritionController::class, 'getWeeklySummary']);
    Route::get('/{id}/nutrition/trends', [\App\Http\Controllers\CoachClientNutritionController::class, 'getTrends']);
    Route::post('/{id}/nutrition/log', [\App\Http\Controllers\CoachClientNutritionController::class, 'addNutritionLog']);
    Route::put('/{id}/nutrition/goals', [\App\Http\Controllers\CoachClientNutritionController::class, 'updateNutritionGoals']);
});

// ===== ACTIVITY FEED =====
Route::prefix('activity-feed')->middleware('auth:api')->group(function () {
    Route::get('/', [\App\Http\Controllers\ActivityFeedController::class, 'getActivityFeed']);
    Route::post('/', [\App\Http\Controllers\ActivityFeedController::class, 'createActivity']);
    Route::post('/like/{id}', [\App\Http\Controllers\ActivityFeedController::class, 'likeActivity']);
    Route::post('/comment/{id}', [\App\Http\Controllers\ActivityFeedController::class, 'commentOnActivity']);
    Route::delete('/{id}', [\App\Http\Controllers\ActivityFeedController::class, 'deleteActivity']);
    Route::delete('/comment/{id}', [\App\Http\Controllers\ActivityFeedController::class, 'deleteComment']);
});

// ===== LEADERBOARD =====
Route::prefix('leaderboard')->middleware('auth:api')->group(function () {
    Route::get('/global', [\App\Http\Controllers\LeaderboardController::class, 'getGlobalLeaderboard']);
    Route::get('/organization/{id}', [\App\Http\Controllers\LeaderboardController::class, 'getOrganizationLeaderboard']);
    Route::get('/user/{id}/rank', [\App\Http\Controllers\LeaderboardController::class, 'getUserRank']);
    Route::get('/friends', [\App\Http\Controllers\LeaderboardController::class, 'getFriendsLeaderboard']);
});

// ===== AVATAR & GAMIFICATION ENHANCED =====
Route::prefix('avatar')->middleware('auth:api')->group(function () {
    Route::get('/catalog', [\App\Http\Controllers\AvatarGamificationEnhancedController::class, 'getCatalog']);
    Route::get('/inventory', [\App\Http\Controllers\AvatarGamificationEnhancedController::class, 'getInventory']);
    Route::get('/equipped', [\App\Http\Controllers\AvatarGamificationEnhancedController::class, 'getEquipped']);
    Route::post('/purchase', [\App\Http\Controllers\AvatarGamificationEnhancedController::class, 'purchaseItem']);
    Route::post('/equip', [\App\Http\Controllers\AvatarGamificationEnhancedController::class, 'equipItem']);
    Route::post('/unequip', [\App\Http\Controllers\AvatarGamificationEnhancedController::class, 'unequipItem']);
});

Route::prefix('gamification')->middleware('auth:api')->group(function () {
    Route::get('/body-points', [\App\Http\Controllers\AvatarGamificationEnhancedController::class, 'getBodyPoints']);
    Route::post('/award-points', [\App\Http\Controllers\AvatarGamificationEnhancedController::class, 'awardBodyPoints']);
});

// ===== PASSIO AI INTEGRATION ENHANCED =====
Route::prefix('nutrition/passio')->middleware('auth:api')->group(function () {
    Route::post('/recognize', [\App\Http\Controllers\PassioIntegrationEnhancedController::class, 'recognizeFoodFromImage']);
    Route::post('/barcode', [\App\Http\Controllers\PassioIntegrationEnhancedController::class, 'lookupBarcode']);
    Route::post('/recipe', [\App\Http\Controllers\PassioIntegrationEnhancedController::class, 'analyzeRecipe']);
});

// ===== FRIENDS & SOCIAL =====
Route::prefix('friends')->middleware('auth:api')->group(function () {
    Route::get('/', [\App\Http\Controllers\FriendshipController::class, 'getFriends']);
    Route::get('/requests/pending', [\App\Http\Controllers\FriendshipController::class, 'getPendingRequests']);
    Route::get('/requests/sent', [\App\Http\Controllers\FriendshipController::class, 'getSentRequests']);
    Route::post('/request', [\App\Http\Controllers\FriendshipController::class, 'sendFriendRequest']);
    Route::post('/accept/{id}', [\App\Http\Controllers\FriendshipController::class, 'acceptFriendRequest']);
    Route::post('/reject/{id}', [\App\Http\Controllers\FriendshipController::class, 'rejectFriendRequest']);
    Route::delete('/{id}', [\App\Http\Controllers\FriendshipController::class, 'unfriend']);
});

Route::get('/users/search', [\App\Http\Controllers\FriendshipController::class, 'searchUsers'])->middleware('auth:api');

// ===== ADMIN ANALYTICS DASHBOARD =====
Route::prefix('admin/analytics')->middleware('auth:api')->group(function () {
    Route::get('/dashboard-summary', [\App\Http\Controllers\AdminAnalyticsDashboardController::class, 'getDashboardSummary']);
    Route::get('/user-growth', [\App\Http\Controllers\AdminAnalyticsDashboardController::class, 'getUserGrowth']);
    Route::get('/user-demographics', [\App\Http\Controllers\AdminAnalyticsDashboardController::class, 'getUserDemographics']);
    Route::get('/user-retention', [\App\Http\Controllers\AdminAnalyticsDashboardController::class, 'getUserRetention']);
    Route::get('/revenue-trends', [\App\Http\Controllers\AdminAnalyticsDashboardController::class, 'getRevenueTrends']);
    Route::get('/revenue-by-plan', [\App\Http\Controllers\AdminAnalyticsDashboardController::class, 'getRevenueByPlan']);
    Route::get('/engagement-metrics', [\App\Http\Controllers\AdminAnalyticsDashboardController::class, 'getEngagementMetrics']);
    Route::get('/popular-content', [\App\Http\Controllers\AdminAnalyticsDashboardController::class, 'getPopularContent']);
    Route::get('/activity-heatmap', [\App\Http\Controllers\AdminAnalyticsDashboardController::class, 'getActivityHeatmap']);
    Route::get('/system-performance', [\App\Http\Controllers\AdminAnalyticsDashboardController::class, 'getSystemPerformance']);
    Route::get('/api-metrics', [\App\Http\Controllers\AdminAnalyticsDashboardController::class, 'getApiMetrics']);
    Route::get('/error-rates', [\App\Http\Controllers\AdminAnalyticsDashboardController::class, 'getErrorRates']);
    Route::post('/export', [\App\Http\Controllers\AdminAnalyticsDashboardController::class, 'exportAnalytics']);
});
