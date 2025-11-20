<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class S3MediaService
{
    private $mediaDisk;
    private $reportsDisk;

    public function __construct()
    {
        $this->mediaDisk = Storage::disk('s3-media');
        $this->reportsDisk = Storage::disk('s3-reports');
    }

    /**
     * Upload media file (images, videos) to S3 media bucket
     */
    public function uploadMedia(UploadedFile $file, string $folder = 'uploads', array $metadata = []): array
    {
        // Validate file before processing
        $this->validateMediaFile($file);
        
        $filename = $this->generateUniqueFilename($file);
        $path = $folder . '/' . $filename;
        
        // Upload file to S3
        $uploaded = $this->mediaDisk->putFileAs($folder, $file, $filename, [
            'visibility' => 'private',
            'Metadata' => $metadata
        ]);

        if (!$uploaded) {
            throw new \Exception('Failed to upload file to S3');
        }

        return [
            'path' => $path,
            'url' => $this->getSignedUrl($path, 'media'),
            'filename' => $filename,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'bucket' => config('filesystems.disks.media.bucket', config('filesystems.disks.s3.bucket'))
        ];
    }

    /**
     * Validate media file before upload
     */
    private function validateMediaFile(UploadedFile $file): void
    {
        // Define allowed MIME types for media uploads
        $allowedMimeTypes = [
            // Images
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'image/bmp',
            'image/tiff',
            // Videos
            'video/mp4',
            'video/x-msvideo', // .avi
            'video/quicktime', // .mov
            'video/x-ms-wmv',  // .wmv
            'video/x-flv',     // .flv
            'video/webm',
            'video/x-matroska', // .mkv
            // Audio (for media content)
            'audio/mpeg',      // .mp3
            'audio/wav',
            'audio/ogg',
            'audio/aac',
            'audio/flac',
        ];

        // Define allowed extensions
        $allowedExtensions = [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff',
            'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv',
            'mp3', 'wav', 'ogg', 'aac', 'flac'
        ];

        $mimeType = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());

        // Validate MIME type
        if (!in_array($mimeType, $allowedMimeTypes)) {
            throw new \Illuminate\Validation\ValidationException(
                \Illuminate\Validation\Validator::make([], []),
                [
                    'file' => [
                        "Unsupported file type. MIME type '{$mimeType}' is not allowed for media uploads. " .
                        "Allowed types: " . implode(', ', $allowedMimeTypes)
                    ]
                ]
            );
        }

        // Validate extension
        if (!in_array($extension, $allowedExtensions)) {
            throw new \Illuminate\Validation\ValidationException(
                \Illuminate\Validation\Validator::make([], []),
                [
                    'file' => [
                        "Unsupported file extension. Extension '{$extension}' is not allowed for media uploads. " .
                        "Allowed extensions: " . implode(', ', $allowedExtensions)
                    ]
                ]
            );
        }

        // Cross-validate MIME type and extension
        $expectedMimeTypes = $this->getExpectedMimeTypes($extension);
        if (!empty($expectedMimeTypes) && !in_array($mimeType, $expectedMimeTypes)) {
            throw new \Illuminate\Validation\ValidationException(
                \Illuminate\Validation\Validator::make([], []),
                [
                    'file' => [
                        "File extension '{$extension}' does not match MIME type '{$mimeType}'. " .
                        "This may indicate a spoofed or corrupted file."
                    ]
                ]
            );
        }

        // Validate file size (optional - can be configured)
        $maxSize = config('media.max_upload_size', 50 * 1024 * 1024); // 50MB default
        if ($file->getSize() > $maxSize) {
            $maxSizeMB = round($maxSize / (1024 * 1024), 2);
            throw new \Illuminate\Validation\ValidationException(
                \Illuminate\Validation\Validator::make([], []),
                [
                    'file' => [
                        "File size exceeds maximum allowed size of {$maxSizeMB}MB."
                    ]
                ]
            );
        }
    }

    /**
     * Upload report file to S3 reports bucket
     */
    public function uploadReport(string $content, string $filename, string $folder = 'reports', array $metadata = []): array
    {
        $path = $folder . '/' . $filename;
        
        // Upload content to S3
        $uploaded = $this->reportsDisk->put($path, $content, [
            'visibility' => 'private',
            'Metadata' => $metadata
        ]);

        if (!$uploaded) {
            throw new \Exception('Failed to upload report to S3');
        }

        return [
            'path' => $path,
            'url' => $this->getSignedUrl($path, 'reports'),
            'filename' => $filename,
            'size' => strlen($content),
            'bucket' => config('filesystems.disks.s3-reports.bucket', config('filesystems.disks.s3.bucket'))
        ];
    }

    /**
     * Generate signed URL for private file access
     */
    public function getSignedUrl(string $path, string $bucket = 'media', int $expiresInMinutes = 60): string
    {
        $disk = $bucket === 'reports' ? $this->reportsDisk : $this->mediaDisk;
        return $disk->temporaryUrl($path, now()->addMinutes($expiresInMinutes));
    }

    /**
     * Delete file from S3
     */
    public function deleteFile(string $path, string $bucket = 'media'): bool
    {
        $disk = $bucket === 'reports' ? $this->reportsDisk : $this->mediaDisk;
        return $disk->delete($path);
    }

    /**
     * List files in a folder
     */
    public function listFiles(string $folder = '', string $bucket = 'media'): array
    {
        $disk = $bucket === 'reports' ? $this->reportsDisk : $this->mediaDisk;
        $files = $disk->files($folder);
        
        return array_map(function ($file) use ($disk, $bucket) {
            return [
                'path' => $file,
                'url' => $this->getSignedUrl($file, $bucket),
                'size' => $disk->size($file),
                'last_modified' => $disk->lastModified($file)
            ];
        }, $files);
    }

    /**
     * Generate unique filename with validated extension
     */
    private function generateUniqueFilename(UploadedFile $file): string
    {
        $extension = $this->getValidatedExtension($file);
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $sanitized = Str::slug($name);
        
        return $sanitized . '_' . time() . '_' . Str::random(8) . '.' . $extension;
    }

    /**
     * Get validated file extension
     */
    private function getValidatedExtension(UploadedFile $file): string
    {
        // Define allowed extensions by category
        $allowedExtensions = [
            // Images
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff',
            // Videos
            'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv',
            // Documents
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf',
            // Audio
            'mp3', 'wav', 'ogg', 'aac', 'flac',
        ];

        // Get client extension and normalize
        $clientExtension = strtolower($file->getClientOriginalExtension());
        
        // Validate against allowlist
        if (in_array($clientExtension, $allowedExtensions)) {
            // Double-check with MIME type validation
            $mimeType = $file->getMimeType();
            $expectedMimeTypes = $this->getExpectedMimeTypes($clientExtension);
            
            if (in_array($mimeType, $expectedMimeTypes)) {
                return $clientExtension;
            }
        }
        
        // If client extension is not trusted, derive from MIME type
        $safeExtension = $this->getExtensionFromMimeType($file->getMimeType());
        
        if ($safeExtension && in_array($safeExtension, $allowedExtensions)) {
            \Log::warning('File extension mismatch, using MIME-derived extension', [
                'original_name' => $file->getClientOriginalName(),
                'client_extension' => $clientExtension,
                'mime_type' => $file->getMimeType(),
                'safe_extension' => $safeExtension
            ]);
            
            return $safeExtension;
        }
        
        // Reject if no safe extension can be determined
        throw new \InvalidArgumentException(
            "Unsupported file type. Original: {$file->getClientOriginalName()}, " .
            "Extension: {$clientExtension}, MIME: {$file->getMimeType()}"
        );
    }

    /**
     * Get expected MIME types for extension
     */
    private function getExpectedMimeTypes(string $extension): array
    {
        $mimeMap = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'svg' => ['image/svg+xml'],
            'bmp' => ['image/bmp'],
            'tiff' => ['image/tiff'],
            'mp4' => ['video/mp4'],
            'avi' => ['video/x-msvideo'],
            'mov' => ['video/quicktime'],
            'wmv' => ['video/x-ms-wmv'],
            'flv' => ['video/x-flv'],
            'webm' => ['video/webm'],
            'mkv' => ['video/x-matroska'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'ppt' => ['application/vnd.ms-powerpoint'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            'txt' => ['text/plain'],
            'rtf' => ['application/rtf'],
            'mp3' => ['audio/mpeg'],
            'wav' => ['audio/wav'],
            'ogg' => ['audio/ogg'],
            'aac' => ['audio/aac'],
            'flac' => ['audio/flac'],
        ];

        return $mimeMap[$extension] ?? [];
    }

    /**
     * Get safe extension from MIME type
     */
    private function getExtensionFromMimeType(string $mimeType): ?string
    {
        $mimeToExtension = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'image/bmp' => 'bmp',
            'image/tiff' => 'tiff',
            'video/mp4' => 'mp4',
            'video/x-msvideo' => 'avi',
            'video/quicktime' => 'mov',
            'video/x-ms-wmv' => 'wmv',
            'video/x-flv' => 'flv',
            'video/webm' => 'webm',
            'video/x-matroska' => 'mkv',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/plain' => 'txt',
            'application/rtf' => 'rtf',
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/ogg' => 'ogg',
            'audio/aac' => 'aac',
            'audio/flac' => 'flac',
        ];

        return $mimeToExtension[$mimeType] ?? null;
    }

    /**
     * Generate nutrition report
     */
    public function generateNutritionReport(array $data, string $format = 'json'): array
    {
        $reportData = [
            'user_id' => $data['user_id'] ?? null,
            'period' => $data['period'] ?? 'weekly',
            'generated_at' => now()->toISOString(),
            'summary' => [
                'total_calories' => $data['total_calories'] ?? 0,
                'avg_daily_calories' => $data['avg_daily_calories'] ?? 0,
                'total_protein' => $data['total_protein'] ?? 0,
                'total_carbs' => $data['total_carbs'] ?? 0,
                'total_fat' => $data['total_fat'] ?? 0,
                'goal_adherence' => $data['goal_adherence'] ?? 0
            ],
            'daily_breakdown' => $data['daily_breakdown'] ?? [],
            'meal_analysis' => $data['meal_analysis'] ?? [],
            'recommendations' => $data['recommendations'] ?? []
        ];

        $filename = 'nutrition_report_' . ($data['user_id'] ?? 'user') . '_' . date('Y-m-d_H-i-s') . '.' . $format;
        
        $content = $format === 'json' 
            ? json_encode($reportData, JSON_PRETTY_PRINT)
            : $this->generateCSVReport($reportData);

        return $this->uploadReport($content, $filename, 'nutrition-reports', [
            'user_id' => $data['user_id'] ?? 'unknown',
            'report_type' => 'nutrition',
            'format' => $format
        ]);
    }

    /**
     * Generate CSV format report
     */
    private function generateCSVReport(array $data): string
    {
        $lines = [
            'Nutrition Report',
            'Generated: ' . ($data['generated_at'] ?? now()->toISOString()),
            '',
            'Summary',
            'Total Calories,' . ($data['summary']['total_calories'] ?? 0),
            'Avg Daily Calories,' . ($data['summary']['avg_daily_calories'] ?? 0),
            'Total Protein,' . ($data['summary']['total_protein'] ?? 0) . 'g',
            'Total Carbs,' . ($data['summary']['total_carbs'] ?? 0) . 'g',
            'Total Fat,' . ($data['summary']['total_fat'] ?? 0) . 'g',
            'Goal Adherence,' . ($data['summary']['goal_adherence'] ?? 0) . '%',
            ''
        ];

        $csv = implode("\n", $lines) . "\n";

        if (!empty($data['daily_breakdown'])) {
            $csv .= "Daily Breakdown\n";
            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, ['Date', 'Calories', 'Protein', 'Carbs', 'Fat']);

            foreach ($data['daily_breakdown'] as $day) {
                fputcsv($handle, [
                    $day['date'] ?? '',
                    $day['calories'] ?? 0,
                    $day['protein'] ?? 0,
                    $day['carbs'] ?? 0,
                    $day['fat'] ?? 0,
                ]);
            }

            rewind($handle);
            $csv .= stream_get_contents($handle);
            fclose($handle);
        }

        return $csv;
    }
}
