<?php

namespace App\Traits;

use App\Services\S3FileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait HandlesFileUploads
{
    protected $s3FileService;

    /**
     * Get S3 file service instance
     */
    protected function getS3FileService(): S3FileService
    {
        if (!$this->s3FileService) {
            $this->s3FileService = new S3FileService();
        }

        return $this->s3FileService;
    }

    /**
     * Handle file upload with fallback to local storage
     *
     * @param UploadedFile $file
     * @param string $folder
     * @param string|null $filename
     * @return string|false
     */
    protected function handleFileUpload(UploadedFile $file, string $folder = 'uploads', ?string $filename = null): string|false
    {
        // Try S3 upload first
        if (config('filesystems.default') === 's3') {
            $url = $this->getS3FileService()->uploadFile($file, $folder, $filename);
            if ($url) {
                return $url;
            }
            
            // Log S3 failure and fallback to local
            \Log::warning('S3 upload failed, falling back to local storage');
        }

        // Fallback to local storage
        return $this->handleLocalFileUpload($file, $folder, $filename);
    }

    /**
     * Handle local file upload
     *
     * @param UploadedFile $file
     * @param string $folder
     * @param string|null $filename
     * @return string|false
     */
    protected function handleLocalFileUpload(UploadedFile $file, string $folder = 'uploads', ?string $filename = null): string|false
    {
        try {
            if (!$filename) {
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            }

            $path = $file->storeAs($folder, $filename, 'public');
            
            if ($path) {
                return Storage::disk('public')->url($path);
            }

            return false;
        } catch (\Exception $e) {
            \Log::error('Local file upload error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete file from current storage
     *
     * @param string $url
     * @return bool
     */
    protected function deleteFile(string $url): bool
    {
        if (config('filesystems.default') === 's3' && $this->isS3Url($url)) {
            return $this->getS3FileService()->deleteFile($url);
        }

        // Handle local file deletion
        $path = str_replace(Storage::disk('public')->url(''), '', $url);
        return Storage::disk('public')->delete($path);
    }

    /**
     * Check if URL is from S3
     *
     * @param string $url
     * @return bool
     */
    protected function isS3Url(string $url): bool
    {
        return strpos($url, 's3.amazonaws.com') !== false || 
               strpos($url, '.s3.') !== false ||
               strpos($url, config('filesystems.disks.s3.url', '')) === 0;
    }

    /**
     * Validate file upload
     *
     * @param UploadedFile $file
     * @param array $allowedTypes
     * @param int $maxSize (in KB)
     * @return array
     */
    protected function validateFileUpload(UploadedFile $file, array $allowedTypes = [], int $maxSize = 10240): array
    {
        $errors = [];

        // Check file size (convert KB to bytes)
        if ($file->getSize() > ($maxSize * 1024)) {
            $errors[] = "File size must be less than {$maxSize}KB";
        }

        // Check file type
        if (!empty($allowedTypes)) {
            $extension = strtolower($file->getClientOriginalExtension());
            if (!in_array($extension, $allowedTypes)) {
                $errors[] = "File type must be one of: " . implode(', ', $allowedTypes);
            }
        }

        // Check if file is valid
        if (!$file->isValid()) {
            $errors[] = "Invalid file upload";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Handle multiple file uploads
     *
     * @param array $files
     * @param string $folder
     * @return array
     */
    protected function handleMultipleFileUploads(array $files, string $folder = 'uploads'): array
    {
        $uploadedFiles = [];
        $errors = [];

        foreach ($files as $key => $file) {
            if ($file instanceof UploadedFile) {
                $url = $this->handleFileUpload($file, $folder);
                if ($url) {
                    $uploadedFiles[$key] = $url;
                } else {
                    $errors[$key] = 'Failed to upload file';
                }
            }
        }

        return [
            'uploaded' => $uploadedFiles,
            'errors' => $errors
        ];
    }

    /**
     * Get file type category
     *
     * @param string $extension
     * @return string
     */
    protected function getFileTypeCategory(string $extension): string
    {
        $extension = strtolower($extension);

        $imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
        $videoTypes = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'];
        $documentTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];

        if (in_array($extension, $imageTypes)) {
            return 'image';
        } elseif (in_array($extension, $videoTypes)) {
            return 'video';
        } elseif (in_array($extension, $documentTypes)) {
            return 'document';
        }

        return 'other';
    }

    /**
     * Generate organized folder path
     *
     * @param string $baseFolder
     * @param string $fileType
     * @param int|null $userId
     * @return string
     */
    protected function generateFolderPath(string $baseFolder = 'uploads', string $fileType = 'other', ?int $userId = null): string
    {
        $path = $baseFolder . '/' . $fileType;
        
        if ($userId) {
            $path .= '/user_' . $userId;
        }
        
        $path .= '/' . date('Y/m');
        
        return $path;
    }
}
