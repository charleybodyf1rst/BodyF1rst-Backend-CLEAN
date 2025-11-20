<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AIMLAnimationService
{
    private string $apiKey;
    private string $baseUrl;
    private int $pollingInterval;
    private int $timeout;

    public function __construct()
    {
        $this->apiKey = config('services.aiml.api_key');
        $this->baseUrl = config('services.aiml.base_url', 'https://api.aimlapi.com/v2');
        $this->pollingInterval = 10; // Poll every 10 seconds
        $this->timeout = 600; // 10 minute timeout
    }

    /**
     * Animate avatar using OmniHuman 1.5
     * Creates a video from a single image with audio-driven lip-sync
     *
     * @param string $imageUrl URL of the avatar/coach photo
     * @param string $audioUrl URL of the audio file (max 30 seconds)
     * @param array $options Additional options
     * @return array
     */
    public function animateAvatar(string $imageUrl, string $audioUrl, array $options = []): array
    {
        try {
            Log::info('AIML: Starting avatar animation', [
                'image_url' => $imageUrl,
                'audio_url' => $audioUrl,
            ]);

            // Step 1: Create generation task
            $generationResult = $this->createGeneration($imageUrl, $audioUrl);

            if (!$generationResult['success']) {
                return $generationResult;
            }

            $generationId = $generationResult['id'];
            Log::info('AIML: Generation task created', ['generation_id' => $generationId]);

            // Step 2: Poll for completion
            $result = $this->pollForCompletion($generationId);

            if ($result['success'] && isset($result['video']['url'])) {
                Log::info('AIML: Animation completed successfully', [
                    'generation_id' => $generationId,
                    'video_url' => $result['video']['url'],
                    'duration' => $result['video']['duration'] ?? null,
                ]);

                // Optionally upload to S3
                if ($options['upload_to_s3'] ?? false) {
                    $s3Result = $this->uploadVideoToS3($result['video']['url'], $options['s3_path'] ?? 'avatars/animated');
                    if ($s3Result['success']) {
                        $result['s3_url'] = $s3Result['url'];
                    }
                }
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('AIML: Avatar animation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create video generation task
     *
     * @param string $imageUrl
     * @param string $audioUrl
     * @return array
     */
    private function createGeneration(string $imageUrl, string $audioUrl): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/video/generations', [
                'model' => 'bytedance/omnihuman/v1.5',
                'image_url' => $imageUrl,
                'audio_url' => $audioUrl,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'id' => $data['id'] ?? null,
                    'status' => $data['status'] ?? 'queued',
                ];
            }

            $errorData = $response->json();
            Log::error('AIML: Failed to create generation', [
                'status' => $response->status(),
                'error' => $errorData,
            ]);

            return [
                'success' => false,
                'error' => $errorData['error'] ?? 'Failed to create generation task',
                'status_code' => $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Poll generation status until completion
     *
     * @param string $generationId
     * @return array
     */
    private function pollForCompletion(string $generationId): array
    {
        $startTime = time();

        while (true) {
            // Check timeout
            if ((time() - $startTime) > $this->timeout) {
                Log::warning('AIML: Generation timeout', ['generation_id' => $generationId]);
                return [
                    'success' => false,
                    'error' => 'Generation timeout after ' . $this->timeout . ' seconds',
                    'generation_id' => $generationId,
                ];
            }

            // Get status
            $statusResult = $this->getGenerationStatus($generationId);

            if (!$statusResult['success']) {
                return $statusResult;
            }

            $status = $statusResult['status'];
            Log::info('AIML: Polling status', [
                'generation_id' => $generationId,
                'status' => $status,
            ]);

            // Check if completed
            if ($status === 'completed') {
                return [
                    'success' => true,
                    'status' => 'completed',
                    'video' => $statusResult['video'] ?? null,
                    'meta' => $statusResult['meta'] ?? null,
                    'generation_id' => $generationId,
                ];
            }

            // Check if failed
            if (in_array($status, ['failed', 'error', 'cancelled'])) {
                return [
                    'success' => false,
                    'status' => $status,
                    'error' => $statusResult['error'] ?? 'Generation failed',
                    'generation_id' => $generationId,
                ];
            }

            // Wait before next poll
            sleep($this->pollingInterval);
        }
    }

    /**
     * Get generation status
     *
     * @param string $generationId
     * @return array
     */
    public function getGenerationStatus(string $generationId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get($this->baseUrl . '/video/generations', [
                'generation_id' => $generationId,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'status' => $data['status'] ?? 'unknown',
                    'video' => $data['video'] ?? null,
                    'error' => $data['error'] ?? null,
                    'meta' => $data['meta'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to get generation status',
                'status_code' => $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Animate with Seedance 1.0 Pro (Image-to-Video)
     * Alternative method for general video generation
     *
     * @param string $imageUrl
     * @param string $prompt Description of the animation/action
     * @param array $options
     * @return array
     */
    public function animateWithSeedance(string $imageUrl, string $prompt, array $options = []): array
    {
        try {
            Log::info('AIML: Starting Seedance animation', [
                'image_url' => $imageUrl,
                'prompt' => $prompt,
            ]);

            $payload = [
                'model' => 'bytedance/seedance-1-0-pro-i2v',
                'image_url' => $imageUrl,
                'prompt' => $prompt,
                'resolution' => $options['resolution'] ?? '720p',
                'duration' => $options['duration'] ?? 5,
            ];

            if (isset($options['seed'])) {
                $payload['seed'] = $options['seed'];
            }

            if (isset($options['camera_fixed'])) {
                $payload['camera_fixed'] = $options['camera_fixed'];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.aimlapi.com/v2/generate/video/bytedance/generation', $payload);

            if ($response->successful()) {
                $data = $response->json();
                $generationId = $data['id'] ?? null;

                if (!$generationId) {
                    return [
                        'success' => false,
                        'error' => 'No generation ID returned',
                    ];
                }

                Log::info('AIML: Seedance generation created', ['generation_id' => $generationId]);

                // Poll for completion
                $result = $this->pollForSeedanceCompletion($generationId);

                if ($result['success'] && isset($options['upload_to_s3']) && $options['upload_to_s3']) {
                    $s3Result = $this->uploadVideoToS3(
                        $result['video']['url'],
                        $options['s3_path'] ?? 'avatars/animated'
                    );
                    if ($s3Result['success']) {
                        $result['s3_url'] = $s3Result['url'];
                    }
                }

                return $result;
            }

            return [
                'success' => false,
                'error' => $response->json()['error'] ?? 'Failed to create Seedance generation',
            ];
        } catch (\Exception $e) {
            Log::error('AIML: Seedance animation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Poll Seedance generation status
     *
     * @param string $generationId
     * @return array
     */
    private function pollForSeedanceCompletion(string $generationId): array
    {
        $startTime = time();

        while (true) {
            if ((time() - $startTime) > $this->timeout) {
                return [
                    'success' => false,
                    'error' => 'Generation timeout',
                ];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get('https://api.aimlapi.com/v2/generate/video/bytedance/generation', [
                'generation_id' => $generationId,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $status = $data['status'] ?? 'unknown';

                Log::info('AIML: Seedance polling', [
                    'generation_id' => $generationId,
                    'status' => $status,
                ]);

                if ($status === 'completed') {
                    return [
                        'success' => true,
                        'status' => 'completed',
                        'video' => $data['video'] ?? null,
                        'meta' => $data['meta'] ?? null,
                    ];
                }

                if (in_array($status, ['failed', 'error', 'cancelled'])) {
                    return [
                        'success' => false,
                        'status' => $status,
                        'error' => $data['error'] ?? 'Generation failed',
                    ];
                }
            }

            sleep($this->pollingInterval);
        }
    }

    /**
     * Upload generated video to S3
     *
     * @param string $videoUrl
     * @param string $s3Path
     * @return array
     */
    private function uploadVideoToS3(string $videoUrl, string $s3Path): array
    {
        try {
            $videoContent = file_get_contents($videoUrl);
            $fileName = uniqid('animated-avatar-') . '.mp4';
            $fullPath = $s3Path . '/' . $fileName;

            Storage::disk('s3')->put($fullPath, $videoContent, 'public');

            $s3Url = Storage::disk('s3')->url($fullPath);

            Log::info('AIML: Video uploaded to S3', ['s3_url' => $s3Url]);

            return [
                'success' => true,
                'url' => $s3Url,
                'path' => $fullPath,
            ];
        } catch (\Exception $e) {
            Log::error('AIML: S3 upload failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Batch animate multiple avatars
     *
     * @param array $avatars Array of ['name' => '', 'image_url' => '', 'audio_url' => '']
     * @param array $options
     * @return array
     */
    public function batchAnimateAvatars(array $avatars, array $options = []): array
    {
        $results = [];
        $successCount = 0;
        $failCount = 0;

        foreach ($avatars as $avatar) {
            $name = $avatar['name'] ?? 'Unknown';
            Log::info('AIML: Processing avatar', ['name' => $name]);

            $result = $this->animateAvatar(
                $avatar['image_url'],
                $avatar['audio_url'],
                $options
            );

            $results[$name] = $result;

            if ($result['success']) {
                $successCount++;
            } else {
                $failCount++;
            }

            // Wait between requests to avoid rate limiting
            if (count($avatars) > 1) {
                sleep(2);
            }
        }

        return [
            'success' => $failCount === 0,
            'total' => count($avatars),
            'successful' => $successCount,
            'failed' => $failCount,
            'results' => $results,
        ];
    }

    /**
     * Get available credits/usage
     *
     * @return array
     */
    public function getCredits(): array
    {
        try {
            // Note: AIML API may not have a credits endpoint
            // This is a placeholder for potential future implementation
            return [
                'success' => true,
                'message' => 'Check your AIML dashboard at https://aimlapi.com/dashboard',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
