<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileUploadController;

/*
|--------------------------------------------------------------------------
| File Upload API Routes
|--------------------------------------------------------------------------
|
| These routes handle file uploads to S3 using the S3Helper service.
| All routes require authentication and proper middleware.
|
| SECURITY: All file uploads are validated for type, size, and content.
| Files are uploaded to AWS S3 with automatic local fallback.
|
*/

Route::prefix('upload')->middleware(['auth:api', 'throttle:uploads'])->group(function () {

    // Image uploads - Profile pictures, exercise images, meal photos
    Route::post('/image', [FileUploadController::class, 'uploadImage'])
        ->name('upload.image');

    // Profile image with thumbnail generation
    Route::post('/profile', [FileUploadController::class, 'uploadProfile'])
        ->name('upload.profile');

    // Video uploads - Workout videos, exercise demonstrations
    Route::post('/video', [FileUploadController::class, 'uploadVideo'])
        ->name('upload.video');

    // Multiple file uploads - Batch upload support
    Route::post('/multiple', [FileUploadController::class, 'uploadMultiple'])
        ->name('upload.multiple');

    // File deletion - Remove files from S3
    Route::delete('/file', [FileUploadController::class, 'deleteFile'])
        ->name('upload.delete');
});
