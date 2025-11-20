<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class S3FileService
{
    protected $disk;

    public function __construct()
    {
        $this->disk = Storage::disk('s3');
    }

    /**
     * Upload file to S3
     *
     * @param UploadedFile $file
     * @param string $folder
     * @param string|null $filename
     * @return string|false
     */
    public function uploadFile(UploadedFile $file, string $folder = 'uploads', ?string $filename = null): string|false
    {
        try {
            // Generate unique filename if not provided
            if (!$filename) {
                $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            }

            $path = $folder . '/' . $filename;

            // Upload file to S3
            $uploaded = $this->disk->putFileAs($folder, $file, $filename, 'public');

            if ($uploaded) {
                return $this->disk->url($uploaded);
            }

            return false;
        } catch (\Exception $e) {
            \Log::error('S3 Upload Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Upload multiple files to S3
     *
     * @param array $files
     * @param string $folder
     * @return array
     */
    public function uploadMultipleFiles(array $files, string $folder = 'uploads'): array
    {
        $uploadedFiles = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $url = $this->uploadFile($file, $folder);
                if ($url) {
                    $uploadedFiles[] = $url;
                }
            }
        }

        return $uploadedFiles;
    }

    /**
     * Delete file from S3
     *
     * @param string $url
     * @return bool
     */
    public function deleteFile(string $url): bool
    {
        try {
            // Extract path from URL
            $path = $this->getPathFromUrl($url);
            
            if ($path && $this->disk->exists($path)) {
                return $this->disk->delete($path);
            }

            return false;
        } catch (\Exception $e) {
            \Log::error('S3 Delete Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete multiple files from S3
     *
     * @param array $urls
     * @return array
     */
    public function deleteMultipleFiles(array $urls): array
    {
        $results = [];

        foreach ($urls as $url) {
            $results[$url] = $this->deleteFile($url);
        }

        return $results;
    }

    /**
     * Get file contents from S3
     *
     * @param string $path
     * @return string|null
     */
    public function getFileContents(string $path): ?string
    {
        try {
            if ($this->disk->exists($path)) {
                return $this->disk->get($path);
            }

            return null;
        } catch (\Exception $e) {
            \Log::error('S3 Get File Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if file exists in S3
     *
     * @param string $path
     * @return bool
     */
    public function fileExists(string $path): bool
    {
        try {
            return $this->disk->exists($path);
        } catch (\Exception $e) {
            \Log::error('S3 File Exists Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get signed URL for private files
     *
     * @param string $path
     * @param int $expiration (minutes)
     * @return string
     */
    public function getSignedUrl(string $path, int $expiration = 60): string
    {
        try {
            return $this->disk->temporaryUrl($path, now()->addMinutes($expiration));
        } catch (\Exception $e) {
            \Log::error('S3 Signed URL Error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Upload file with custom content
     *
     * @param string $content
     * @param string $path
     * @param string $visibility
     * @return string|false
     */
    public function uploadContent(string $content, string $path, string $visibility = 'public'): string|false
    {
        try {
            $uploaded = $this->disk->put($path, $content, $visibility);

            if ($uploaded) {
                return $this->disk->url($path);
            }

            return false;
        } catch (\Exception $e) {
            \Log::error('S3 Upload Content Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Extract path from S3 URL
     *
     * @param string $url
     * @return string|null
     */
    private function getPathFromUrl(string $url): ?string
    {
        $bucketUrl = config('filesystems.disks.s3.url');
        
        if (strpos($url, $bucketUrl) === 0) {
            return substr($url, strlen($bucketUrl) + 1);
        }

        // Handle different URL formats
        $pattern = '/https?:\/\/[^\/]+\.s3[^\/]*\.amazonaws\.com\/(.+)/';
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get file size from S3
     *
     * @param string $path
     * @return int|null
     */
    public function getFileSize(string $path): ?int
    {
        try {
            if ($this->disk->exists($path)) {
                return $this->disk->size($path);
            }

            return null;
        } catch (\Exception $e) {
            \Log::error('S3 Get File Size Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Copy file within S3
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function copyFile(string $from, string $to): bool
    {
        try {
            return $this->disk->copy($from, $to);
        } catch (\Exception $e) {
            \Log::error('S3 Copy File Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Move file within S3
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function moveFile(string $from, string $to): bool
    {
        try {
            return $this->disk->move($from, $to);
        } catch (\Exception $e) {
            \Log::error('S3 Move File Error: ' . $e->getMessage());
            return false;
        }
    }
}
