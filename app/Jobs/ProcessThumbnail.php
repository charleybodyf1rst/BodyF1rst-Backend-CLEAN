<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use App\Models\Media;
use Psr\Log\LoggerInterface;

class ProcessThumbnail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $mediaId;
    protected $originalPath;
    protected LoggerInterface $logger;

    // Maximum file size (10MB)
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;
    
    // Allowed image MIME types
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg', 
        'image/png',
        'image/gif',
        'image/webp'
    ];

    // Allowed file extensions
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    // Maximum safe dimension before resizing (pixels)
    private const MAX_DIMENSION = 6000;

    /**
     * Create a new job instance.
     */
    public function __construct($mediaId, $originalPath, LoggerInterface $logger = null)
    {
        $this->mediaId = $mediaId;
        $this->originalPath = $originalPath;
        $this->logger = $logger ?? app(LoggerInterface::class);
        $this->onQueue('thumbnails');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $media = Media::find($this->mediaId);
        $mediaType = $media ? ($media->type ?? null) : null;

        if ($media === null || $mediaType !== 'image') {
            $this->logger->warning('Invalid media for thumbnail processing', [
                'media_id' => $this->mediaId,
                'media_type' => $mediaType ?? 'not_found'
            ]);
            return;
        }

        if (!$this->isValidPath($this->originalPath)) {
            $this->logger->warning('Rejected thumbnail generation due to invalid path', [
                'media_id' => $this->mediaId,
                'path' => $this->originalPath
            ]);
            return;
        }

        $disk = Storage::disk('s3-media');

        if (!$disk->exists($this->originalPath)) {
            $this->logger->warning('Thumbnail skipped because source file does not exist', [
                'media_id' => $this->mediaId,
                'path' => $this->originalPath
            ]);
            return;
        }

        try {
            $mimeType = $disk->mimeType($this->originalPath);
            $fileSize = $disk->size($this->originalPath);
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to read metadata for thumbnail source', [
                'media_id' => $this->mediaId,
                'path' => $this->originalPath,
                'error' => $exception->getMessage(),
            ]);
            return;
        }

        $extension = strtolower(pathinfo($this->originalPath, PATHINFO_EXTENSION) ?? '');
        $normalizedMime = strtolower((string) $mimeType);

        if (!in_array($normalizedMime, self::ALLOWED_MIME_TYPES, true)) {
            $this->logger->warning('Thumbnail skipped due to unsupported MIME type', [
                'media_id' => $this->mediaId,
                'path' => $this->originalPath,
                'mime_type' => $mimeType
            ]);
            return;
        }

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $this->logger->warning('Thumbnail skipped due to unsupported file extension', [
                'media_id' => $this->mediaId,
                'path' => $this->originalPath,
                'extension' => $extension
            ]);
            return;
        }

        if ($fileSize > self::MAX_FILE_SIZE) {
            $this->logger->warning('Thumbnail skipped because source file exceeds size limit', [
                'media_id' => $this->mediaId,
                'path' => $this->originalPath,
                'bytes' => $fileSize
            ]);
            return;
        }

        try {
            $originalContent = $disk->get($this->originalPath);
            $image = Image::make($originalContent);

            if ($image->width() > self::MAX_DIMENSION || $image->height() > self::MAX_DIMENSION) {
                $this->logger->info('Resizing source image prior to thumbnail generation', [
                    'media_id' => $this->mediaId,
                    'path' => $this->originalPath,
                    'width' => $image->width(),
                    'height' => $image->height()
                ]);

                $image->resize(self::MAX_DIMENSION, self::MAX_DIMENSION, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
            }

            $image->fit(300, 300, function ($constraint) {
                $constraint->upsize();
            });

            $pathInfo = pathinfo($this->originalPath);
            $thumbnailPath = $pathInfo['dirname'] . '/thumbnails/' . $pathInfo['filename'] . '_thumb.' . $extension;

            $disk->put($thumbnailPath, $image->encode());

            $media->update([
                'metadata' => array_merge($media->metadata ?? [], [
                    'thumbnail_path' => $thumbnailPath,
                    'thumbnail_url' => $disk->url($thumbnailPath)
                ])
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Thumbnail processing failed', [
                'media_id' => $this->mediaId,
                'path' => $this->originalPath,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Validate file path to prevent directory traversal attacks.
     */
    private function isValidPath(string $path): bool
    {
        // Check for directory traversal attempts
        if (strpos($path, '..') !== false) {
            return false;
        }

        // Check for null bytes
        if (strpos($path, "\0") !== false) {
            return false;
        }

        // Must be a reasonable path length
        if (strlen($path) > 1000) {
            return false;
        }

        return true;
    }
}
