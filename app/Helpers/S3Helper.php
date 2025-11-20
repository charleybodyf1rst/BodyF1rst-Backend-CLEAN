<?php

namespace App\Helpers;

use App\Services\S3FileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Exception;

class S3Helper
{
    protected static $s3Service;

    protected static function getS3Service(): S3FileService
    {
        if (!self::$s3Service) {
            self::$s3Service = new S3FileService();
        }
        return self::$s3Service;
    }

    /**
     * Upload image with S3 support and fallback to local
     *
     * @param string $path
     * @param string $filename
     * @param UploadedFile $file
     * @param string|null $before
     * @return string|null
     */
    public static function uploadedImage($path, $filename, $file, $before = null)
    {
        // Remove old file if exists
        if ($before != null) {
            self::removeImage($path, $before);
        }

        // Try S3 upload first if configured
        if (config('filesystems.default') === 's3') {
            try {
                // Clean path for S3 (remove leading slash and public path)
                $s3Path = ltrim(str_replace('upload/', 'uploads/', $path), '/');
                $url = self::getS3Service()->uploadFile($file, $s3Path, $filename);
                
                if ($url) {
                    return $url;
                }
                
                \Log::warning('S3 upload failed, falling back to local storage');
            } catch (Exception $e) {
                \Log::error('S3 upload error: ' . $e->getMessage());
            }
        }

        // Fallback to local storage with image processing
        return self::uploadImageLocal($path, $filename, $file);
    }

    /**
     * Local image upload with compression (original Helper logic)
     *
     * @param string $path
     * @param string $filename
     * @param UploadedFile $file
     * @return string
     */
    protected static function uploadImageLocal($path, $filename, $file)
    {
        // Ensure directory exists
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        $src_path = $file->getRealPath();
        $info = getimagesize($src_path);

        if ($info !== false) {
            switch ($info['mime']) {
                case 'image/jpeg':
                    $src_image = imagecreatefromjpeg($src_path);
                    break;
                case 'image/png':
                    $src_image = imagecreatefrompng($src_path);
                    break;
                case 'image/gif':
                    $src_image = imagecreatefromgif($src_path);
                    break;
                default:
                    throw new Exception('Unsupported image type');
            }

            $width = imagesx($src_image);
            $height = imagesy($src_image);

            $dst_image = imagecreatetruecolor($width, $height);

            if ($info['mime'] == 'image/png' || $info['mime'] == 'image/gif') {
                imagealphablending($dst_image, false);
                imagesavealpha($dst_image, true);
                $transparent = imagecolorallocatealpha($dst_image, 0, 0, 0, 127);
                imagefilledrectangle($dst_image, 0, 0, $width, $height, $transparent);
            }

            imagecopyresampled($dst_image, $src_image, 0, 0, 0, 0, $width, $height, $width, $height);

            $dest_path = $path . '/' . $filename;

            switch ($info['mime']) {
                case 'image/jpeg':
                    imagejpeg($dst_image, $dest_path, 85); // Better quality than original
                    break;
                case 'image/png':
                    imagepng($dst_image, $dest_path, 6); // Better compression
                    break;
                case 'image/gif':
                    imagegif($dst_image, $dest_path);
                    break;
            }

            imagedestroy($src_image);
            imagedestroy($dst_image);

        } else {
            // If not an image, just move the file
            $file->move($path, $filename);
        }

        // Return URL for local files
        $publicPath = str_replace(public_path() . '/', '', $path . '/' . $filename);
        return url($publicPath);
    }

    /**
     * Upload video/file with S3 support
     *
     * @param string $path
     * @param string $filename
     * @param UploadedFile $file
     * @param string|null $before
     * @return string|null
     */
    public static function uploadedFile($path, $filename, $file, $before = null)
    {
        // Remove old file if exists
        if ($before != null) {
            self::removeFile($path, $before);
        }

        // Try S3 upload first if configured
        if (config('filesystems.default') === 's3') {
            try {
                $s3Path = ltrim(str_replace('upload/', 'uploads/', $path), '/');
                $url = self::getS3Service()->uploadFile($file, $s3Path, $filename);
                
                if ($url) {
                    return $url;
                }
                
                \Log::warning('S3 file upload failed, falling back to local storage');
            } catch (Exception $e) {
                \Log::error('S3 file upload error: ' . $e->getMessage());
            }
        }

        // Fallback to local storage
        return self::uploadFileLocal($path, $filename, $file);
    }

    /**
     * Local file upload
     *
     * @param string $path
     * @param string $filename
     * @param UploadedFile $file
     * @return string
     */
    protected static function uploadFileLocal($path, $filename, $file)
    {
        // Ensure directory exists
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        $file->move($path, $filename);

        // Return URL for local files
        $publicPath = str_replace(public_path() . '/', '', $path . '/' . $filename);
        return url($publicPath);
    }

    /**
     * Generate thumbnail with S3 support
     *
     * @param string $path
     * @param string $filename
     * @param UploadedFile $file
     * @param int $targetWidth
     * @param int $targetHeight
     * @param int $quality
     * @param string|null $before
     * @return string|null
     */
    public static function generateThumbnail($path, $filename, $file, $targetWidth, $targetHeight, $quality = 90, $before = null)
    {
        if ($before != null) {
            self::removeImage($path, $before);
        }

        // For S3, we'll generate thumbnail locally first then upload
        if (config('filesystems.default') === 's3') {
            try {
                $tempPath = sys_get_temp_dir() . '/' . $filename;
                $thumbnailGenerated = self::generateThumbnailLocal($tempPath, $filename, $file, $targetWidth, $targetHeight, $quality);
                
                if ($thumbnailGenerated && file_exists($tempPath)) {
                    // Create a temporary UploadedFile from the generated thumbnail
                    $tempFile = new \Illuminate\Http\File($tempPath);
                    $s3Path = ltrim(str_replace('upload/', 'uploads/', $path), '/');
                    $url = self::getS3Service()->uploadContent(file_get_contents($tempPath), $s3Path . '/' . $filename);
                    
                    // Clean up temp file
                    unlink($tempPath);
                    
                    if ($url) {
                        return $url;
                    }
                }
                
                \Log::warning('S3 thumbnail upload failed, falling back to local storage');
            } catch (Exception $e) {
                \Log::error('S3 thumbnail upload error: ' . $e->getMessage());
            }
        }

        // Fallback to local thumbnail generation
        return self::generateThumbnailLocal($path, $filename, $file, $targetWidth, $targetHeight, $quality);
    }

    /**
     * Generate thumbnail locally (original Helper logic)
     */
    protected static function generateThumbnailLocal($path, $filename, $file, $targetWidth, $targetHeight, $quality)
    {
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $src_path = $file->getRealPath();
        $info = getimagesize($src_path);

        if ($info !== false) {
            switch ($info['mime']) {
                case 'image/jpeg':
                    $src_image = imagecreatefromjpeg($src_path);
                    break;
                case 'image/png':
                    $src_image = imagecreatefrompng($src_path);
                    break;
                case 'image/gif':
                    $src_image = imagecreatefromgif($src_path);
                    break;
                default:
                    throw new Exception('Unsupported image type');
            }

            $width = imagesx($src_image);
            $height = imagesy($src_image);

            // Calculate aspect ratio
            $aspectRatio = $width / $height;
            if ($targetWidth / $targetHeight > $aspectRatio) {
                $targetWidth = $targetHeight * $aspectRatio;
            } else {
                $targetHeight = $targetWidth / $aspectRatio;
            }

            $dst_image = imagecreatetruecolor($targetWidth, $targetHeight);

            if ($info['mime'] == 'image/png' || $info['mime'] == 'image/gif') {
                imagealphablending($dst_image, false);
                imagesavealpha($dst_image, true);
                $transparent = imagecolorallocatealpha($dst_image, 0, 0, 0, 127);
                imagefilledrectangle($dst_image, 0, 0, $targetWidth, $targetHeight, $transparent);
            }

            imagecopyresampled($dst_image, $src_image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

            $dest_path = is_dir($path) ? $path . '/' . $filename : $path;

            switch ($info['mime']) {
                case 'image/jpeg':
                    imagejpeg($dst_image, $dest_path, $quality);
                    break;
                case 'image/png':
                    imagepng($dst_image, $dest_path, 9 - ($quality / 10));
                    break;
                case 'image/gif':
                    imagegif($dst_image, $dest_path);
                    break;
            }

            imagedestroy($src_image);
            imagedestroy($dst_image);

            if (config('filesystems.default') !== 's3') {
                $publicPath = str_replace(public_path() . '/', '', $dest_path);
                return url($publicPath);
            }

            return $dest_path;
        }

        return null;
    }

    /**
     * Remove image from storage
     *
     * @param string $path
     * @param string $filename
     * @return bool
     */
    public static function removeImage($path, $filename)
    {
        if (config('filesystems.default') === 's3') {
            // For S3, filename might be a full URL
            if (filter_var($filename, FILTER_VALIDATE_URL)) {
                return self::getS3Service()->deleteFile($filename);
            } else {
                $s3Path = ltrim(str_replace('upload/', 'uploads/', $path), '/') . '/' . $filename;
                return Storage::disk('s3')->delete($s3Path);
            }
        }

        // Local file removal
        $filePath = $path . '/' . $filename;
        if (file_exists($filePath)) {
            return unlink($filePath);
        }

        return false;
    }

    /**
     * Remove file from storage
     *
     * @param string $path
     * @param string $filename
     * @return bool
     */
    public static function removeFile($path, $filename)
    {
        return self::removeImage($path, $filename);
    }

    /**
     * Get file URL (works for both S3 and local)
     *
     * @param string $path
     * @return string
     */
    public static function getFileUrl($path)
    {
        if (config('filesystems.default') === 's3') {
            // If it's already a full URL, return as is
            if (filter_var($path, FILTER_VALIDATE_URL)) {
                return $path;
            }
            
            // Generate S3 URL
            $s3Path = ltrim(str_replace('upload/', 'uploads/', $path), '/');
            return Storage::disk('s3')->url($s3Path);
        }

        // Local file URL
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return url($path);
    }
}
