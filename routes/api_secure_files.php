<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SecureFileController;

/*
|--------------------------------------------------------------------------
| Secure File API Routes
|--------------------------------------------------------------------------
|
| These routes handle secure file access with temporary signed URLs.
| All routes require authentication.
|
| SECURITY: Generated signed URLs expire after specified time.
| AWS S3 pre-signed URLs provide temporary access without exposing credentials.
|
*/

Route::prefix('secure-files')->middleware(['auth:api'])->group(function () {

    // Generate temporary signed URL for single file
    Route::post('/signed-url', [SecureFileController::class, 'getSignedUrl'])
        ->name('secure-files.signed-url');

    // Generate signed URLs for multiple files
    Route::post('/multiple-signed-urls', [SecureFileController::class, 'getMultipleSignedUrls'])
        ->name('secure-files.multiple-signed-urls');

    // Get file metadata and signed URL
    Route::get('/info', [SecureFileController::class, 'getFileInfo'])
        ->name('secure-files.info');

    // List files in directory with optional signed URLs
    Route::get('/list', [SecureFileController::class, 'listFiles'])
        ->name('secure-files.list');

    // Download file (returns signed URL for large files)
    Route::get('/download', [SecureFileController::class, 'downloadFile'])
        ->name('secure-files.download');
});
