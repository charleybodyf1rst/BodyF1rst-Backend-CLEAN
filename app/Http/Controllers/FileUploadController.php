<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Helpers\S3Helper;
use Illuminate\Support\Facades\Validator;

class FileUploadController extends Controller
{
    /**
     * Upload image file to S3
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $file = $request->file('image');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            
            // Upload to S3 with automatic fallback to local
            $path = S3Helper::uploadedImage(
                'upload/images/',           // Local path (for fallback)
                $filename,                  // Generated filename
                $file                       // Uploaded file
            );

            if ($path) {
                return response()->json([
                    'success' => true,
                    'message' => 'Image uploaded successfully',
                    'path' => $path,
                    'filename' => $filename
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image'
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload video file to S3
     */
    public function uploadVideo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'video' => 'required|mimes:mp4,avi,mov,wmv|max:102400', // 100MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $file = $request->file('video');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            
            // Upload video file to S3
            $path = S3Helper::uploadedFile(
                'upload/videos/',           // Local path (for fallback)
                $filename,                  // Generated filename
                $file                       // Uploaded file
            );

            if ($path) {
                return response()->json([
                    'success' => true,
                    'message' => 'Video uploaded successfully',
                    'path' => $path,
                    'filename' => $filename
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload video'
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload profile image with thumbnail generation
     */
    public function uploadProfile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'profile_image' => 'required|image|mimes:jpeg,png,jpg|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $file = $request->file('profile_image');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $thumbnailFilename = time() . '_' . uniqid() . '_thumbnail.' . $file->getClientOriginalExtension();
            
            // Upload main profile image
            $imagePath = S3Helper::uploadedImage(
                'upload/user_profiles/',
                $filename,
                $file
            );

            // Generate and upload thumbnail
            $thumbnailPath = S3Helper::generateThumbnail(
                'upload/user_profiles/thumbnails/',
                $thumbnailFilename,
                $file,
                150,  // width
                150,  // height
                90    // quality
            );

            if ($imagePath) {
                return response()->json([
                    'success' => true,
                    'message' => 'Profile image uploaded successfully',
                    'image_path' => $imagePath,
                    'thumbnail_path' => $thumbnailPath,
                    'filename' => $filename
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload profile image'
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload multiple files
     */
    public function uploadMultiple(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'files' => 'required|array',
            'files.*' => 'file|max:10240', // 10MB max per file
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $uploadedFiles = [];
            $errors = [];

            foreach ($request->file('files') as $index => $file) {
                $filename = time() . '_' . $index . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                
                // Determine upload path based on file type
                $fileType = strtolower($file->getClientOriginalExtension());
                $uploadPath = in_array($fileType, ['jpg', 'jpeg', 'png', 'gif']) 
                    ? 'upload/images/' 
                    : 'upload/documents/';

                $path = S3Helper::uploadedFile($uploadPath, $filename, $file);

                if ($path) {
                    $uploadedFiles[] = [
                        'original_name' => $file->getClientOriginalName(),
                        'filename' => $filename,
                        'path' => $path,
                        'size' => $file->getSize(),
                        'type' => $file->getMimeType()
                    ];
                } else {
                    $errors[] = "Failed to upload: " . $file->getClientOriginalName();
                }
            }

            return response()->json([
                'success' => count($uploadedFiles) > 0,
                'message' => count($uploadedFiles) . ' files uploaded successfully',
                'uploaded_files' => $uploadedFiles,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete file from S3
     */
    public function deleteFile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file_url' => 'required|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $fileUrl = $request->input('file_url');
            $deleted = S3Helper::removeFile('', basename($fileUrl));

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'File deleted successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete file'
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Delete error: ' . $e->getMessage()
            ], 500);
        }
    }
}
