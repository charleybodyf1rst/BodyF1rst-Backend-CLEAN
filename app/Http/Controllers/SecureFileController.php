<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class SecureFileController extends Controller
{
    /**
     * Generate temporary signed URL for secure file access
     */
    public function getSignedUrl(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file_path' => 'required|string',
            'expires_in' => 'nullable|integer|min:1|max:10080', // Max 7 days in minutes
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $filePath = $request->input('file_path');
            $expiresIn = $request->input('expires_in', 60); // Default 1 hour
            
            // Remove any leading slashes and ensure proper S3 path
            $filePath = ltrim($filePath, '/');
            
            // Check if file exists in S3
            if (!Storage::disk('s3')->exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            // Generate signed URL
            $url = Storage::disk('s3')->temporaryUrl(
                $filePath, 
                now()->addMinutes($expiresIn)
            );

            return response()->json([
                'success' => true,
                'signed_url' => $url,
                'expires_at' => now()->addMinutes($expiresIn)->toISOString(),
                'expires_in_minutes' => $expiresIn
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate signed URL: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate signed URLs for multiple files
     */
    public function getMultipleSignedUrls(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file_paths' => 'required|array',
            'file_paths.*' => 'required|string',
            'expires_in' => 'nullable|integer|min:1|max:10080',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $filePaths = $request->input('file_paths');
            $expiresIn = $request->input('expires_in', 60);
            $signedUrls = [];
            $errors = [];

            foreach ($filePaths as $filePath) {
                $filePath = ltrim($filePath, '/');
                
                if (Storage::disk('s3')->exists($filePath)) {
                    $signedUrls[] = [
                        'file_path' => $filePath,
                        'signed_url' => Storage::disk('s3')->temporaryUrl(
                            $filePath, 
                            now()->addMinutes($expiresIn)
                        ),
                        'expires_at' => now()->addMinutes($expiresIn)->toISOString()
                    ];
                } else {
                    $errors[] = "File not found: {$filePath}";
                }
            }

            return response()->json([
                'success' => count($signedUrls) > 0,
                'signed_urls' => $signedUrls,
                'errors' => $errors,
                'expires_in_minutes' => $expiresIn
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate signed URLs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get file metadata and signed URL
     */
    public function getFileInfo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file_path' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $filePath = ltrim($request->input('file_path'), '/');
            
            if (!Storage::disk('s3')->exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            // Get file metadata
            $size = Storage::disk('s3')->size($filePath);
            $lastModified = Storage::disk('s3')->lastModified($filePath);
            $mimeType = Storage::disk('s3')->mimeType($filePath);
            
            // Generate signed URL (valid for 1 hour)
            $signedUrl = Storage::disk('s3')->temporaryUrl(
                $filePath, 
                now()->addHour()
            );

            return response()->json([
                'success' => true,
                'file_info' => [
                    'path' => $filePath,
                    'size' => $size,
                    'size_human' => $this->formatBytes($size),
                    'mime_type' => $mimeType,
                    'last_modified' => Carbon::createFromTimestamp($lastModified)->toISOString(),
                    'signed_url' => $signedUrl,
                    'expires_at' => now()->addHour()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get file info: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List files in S3 directory with signed URLs
     */
    public function listFiles(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'directory' => 'nullable|string',
            'include_signed_urls' => 'nullable|boolean',
            'expires_in' => 'nullable|integer|min:1|max:1440', // Max 24 hours
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $directory = $request->input('directory', '');
            $includeSignedUrls = $request->input('include_signed_urls', false);
            $expiresIn = $request->input('expires_in', 60);
            
            $files = Storage::disk('s3')->files($directory);
            $fileList = [];

            foreach ($files as $file) {
                $fileInfo = [
                    'path' => $file,
                    'name' => basename($file),
                    'size' => Storage::disk('s3')->size($file),
                    'last_modified' => Carbon::createFromTimestamp(
                        Storage::disk('s3')->lastModified($file)
                    )->toISOString(),
                ];

                if ($includeSignedUrls) {
                    $fileInfo['signed_url'] = Storage::disk('s3')->temporaryUrl(
                        $file, 
                        now()->addMinutes($expiresIn)
                    );
                    $fileInfo['expires_at'] = now()->addMinutes($expiresIn)->toISOString();
                }

                $fileList[] = $fileInfo;
            }

            return response()->json([
                'success' => true,
                'directory' => $directory,
                'file_count' => count($fileList),
                'files' => $fileList
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to list files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download file directly (for small files)
     */
    public function downloadFile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file_path' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $filePath = ltrim($request->input('file_path'), '/');
            
            if (!Storage::disk('s3')->exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            // For large files, return signed URL instead
            $size = Storage::disk('s3')->size($filePath);
            if ($size > 10 * 1024 * 1024) { // 10MB
                $signedUrl = Storage::disk('s3')->temporaryUrl(
                    $filePath, 
                    now()->addMinutes(30)
                );

                return response()->json([
                    'success' => true,
                    'message' => 'File is large, use signed URL for download',
                    'signed_url' => $signedUrl,
                    'file_size' => $this->formatBytes($size)
                ]);
            }

            // For small files, return file content
            $content = Storage::disk('s3')->get($filePath);
            $mimeType = Storage::disk('s3')->mimeType($filePath);

            return response($content)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'attachment; filename="' . basename($filePath) . '"');

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
