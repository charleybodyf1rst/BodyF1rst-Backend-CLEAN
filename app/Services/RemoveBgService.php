<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Helpers\S3Helper;

/**
 * Remove.bg API Service
 * Professional background removal for coach photos and avatars
 *
 * API Documentation: https://www.remove.bg/api
 *
 * @author BodyF1rst Development Team
 * @version 1.0.0
 */
class RemoveBgService
{
    private $apiKey;
    private $baseUrl = 'https://api.remove.bg/v1.0';

    public function __construct()
    {
        $this->apiKey = env('REMOVE_BG_API_KEY');

        if (!$this->apiKey) {
            Log::error('Remove.bg API key not configured');
            throw new \Exception('Remove.bg API key not found in .env file');
        }
    }

    /**
     * Remove background from image URL
     *
     * @param string $imageUrl URL of the image to process
     * @param array $options Additional options (size, type, format)
     * @return array ['success' => bool, 'image_url' => string, 'filename' => string]
     */
    public function removeBackground(string $imageUrl, array $options = []): array
    {
        try {
            Log::info('Remove.bg: Processing image from URL', ['url' => $imageUrl]);

            $response = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
            ])
            ->timeout(60)
            ->asForm()
            ->post($this->baseUrl . '/removebg', [
                'image_url' => $imageUrl,
                'size' => $options['size'] ?? 'full', // 'auto', 'preview', 'full', '4k'
                'format' => $options['format'] ?? 'png',
                'type' => $options['type'] ?? 'person', // 'auto', 'person', 'product', 'car'
                'crop' => $options['crop'] ?? false,
                'scale' => $options['scale'] ?? '100%',
            ]);

            if ($response->successful()) {
                // Get image data
                $imageData = $response->body();

                // Generate unique filename
                $filename = 'coaches/nobg/' . uniqid('coach-nobg-') . '.png';

                // Upload to S3 using S3Helper
                $s3Result = S3Helper::uploadFile($imageData, $filename, 'image/png');

                if ($s3Result['success']) {
                    Log::info('Remove.bg: Background removed successfully', [
                        'filename' => $filename,
                        'url' => $s3Result['url']
                    ]);

                    return [
                        'success' => true,
                        'image_url' => $s3Result['url'],
                        'filename' => $filename,
                        'credits_charged' => $response->header('X-Credits-Charged'),
                        'rate_limit_remaining' => $response->header('X-RateLimit-Remaining'),
                    ];
                }

                return [
                    'success' => false,
                    'error' => 'Failed to upload processed image to S3',
                ];
            }

            // Handle API errors
            $errorData = $response->json();
            $errorMessage = $errorData['errors'][0]['title'] ?? 'Unknown error';

            Log::error('Remove.bg API error', [
                'status' => $response->status(),
                'error' => $errorMessage,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('Remove.bg service exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Remove background from local file
     *
     * @param string $filePath Path to local file
     * @param array $options Additional options
     * @return array ['success' => bool, 'image_url' => string]
     */
    public function removeBackgroundFromFile(string $filePath, array $options = []): array
    {
        try {
            if (!file_exists($filePath)) {
                return [
                    'success' => false,
                    'error' => 'File not found: ' . $filePath,
                ];
            }

            Log::info('Remove.bg: Processing local file', ['path' => $filePath]);

            $response = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
            ])
            ->timeout(60)
            ->attach(
                'image_file',
                file_get_contents($filePath),
                basename($filePath)
            )
            ->post($this->baseUrl . '/removebg', [
                'size' => $options['size'] ?? 'full',
                'format' => $options['format'] ?? 'png',
                'type' => $options['type'] ?? 'person',
            ]);

            if ($response->successful()) {
                $imageData = $response->body();
                $filename = 'coaches/nobg/' . uniqid('coach-nobg-') . '.png';

                // Upload to S3
                $s3Result = S3Helper::uploadFile($imageData, $filename, 'image/png');

                if ($s3Result['success']) {
                    Log::info('Remove.bg: File processed successfully', [
                        'filename' => $filename,
                    ]);

                    return [
                        'success' => true,
                        'image_url' => $s3Result['url'],
                        'filename' => $filename,
                        'credits_charged' => $response->header('X-Credits-Charged'),
                    ];
                }

                return [
                    'success' => false,
                    'error' => 'Failed to upload to S3',
                ];
            }

            $errorData = $response->json();
            return [
                'success' => false,
                'error' => $errorData['errors'][0]['title'] ?? 'Failed to remove background',
            ];
        } catch (\Exception $e) {
            Log::error('Remove.bg file processing error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Remove background from base64 encoded image
     *
     * @param string $base64Image Base64 encoded image data
     * @param array $options Additional options
     * @return array ['success' => bool, 'image_url' => string]
     */
    public function removeBackgroundFromBase64(string $base64Image, array $options = []): array
    {
        try {
            // Remove data URI scheme if present
            if (strpos($base64Image, 'data:image') === 0) {
                $base64Image = preg_replace('/^data:image\/\w+;base64,/', '', $base64Image);
            }

            // Decode base64
            $imageData = base64_decode($base64Image);

            if ($imageData === false) {
                return [
                    'success' => false,
                    'error' => 'Invalid base64 image data',
                ];
            }

            // Save to temporary file
            $tempPath = storage_path('app/temp/' . uniqid('temp-') . '.png');
            file_put_contents($tempPath, $imageData);

            // Process the file
            $result = $this->removeBackgroundFromFile($tempPath, $options);

            // Clean up temp file
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Remove.bg base64 processing error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get account info (credits remaining, etc.)
     *
     * @return array ['success' => bool, 'data' => array]
     */
    public function getAccountInfo(): array
    {
        try {
            $response = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
            ])
            ->get($this->baseUrl . '/account');

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to get account info',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Batch process multiple images (remove background from multiple URLs)
     *
     * @param array $imageUrls Array of image URLs
     * @param array $options Options for each image
     * @return array Array of results
     */
    public function batchRemoveBackground(array $imageUrls, array $options = []): array
    {
        $results = [];

        foreach ($imageUrls as $key => $imageUrl) {
            Log::info('Remove.bg batch: Processing image ' . ($key + 1) . ' of ' . count($imageUrls));

            $result = $this->removeBackground($imageUrl, $options);
            $results[$key] = array_merge($result, ['original_url' => $imageUrl]);

            // Rate limiting: Wait 1 second between requests
            if ($key < count($imageUrls) - 1) {
                sleep(1);
            }
        }

        return [
            'success' => true,
            'total' => count($imageUrls),
            'results' => $results,
        ];
    }

    /**
     * Process coach progression photo (remove background for comparison views)
     *
     * @param string $imageUrl URL of progression photo
     * @return array ['success' => bool, 'image_url' => string]
     */
    public function processProgressionPhoto(string $imageUrl): array
    {
        return $this->removeBackground($imageUrl, [
            'type' => 'person',
            'size' => 'full',
            'format' => 'png',
        ]);
    }

    /**
     * Process coach avatar (optimized for full-body coach photos)
     *
     * @param string $imageUrl URL of coach photo
     * @return array ['success' => bool, 'image_url' => string]
     */
    public function processCoachAvatar(string $imageUrl): array
    {
        return $this->removeBackground($imageUrl, [
            'type' => 'person',
            'size' => 'full',
            'format' => 'png',
            'crop' => false, // Keep full body, don't crop
        ]);
    }
}
