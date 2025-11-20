<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReplicateService
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.replicate.api_key');
        $this->baseUrl = config('services.replicate.base_url', 'https://api.replicate.com/v1');
    }

    /**
     * Run a model prediction
     *
     * @param string $model Model identifier (e.g., "stability-ai/sdxl")
     * @param array $input Input parameters for the model
     * @param string|null $webhook Webhook URL for async predictions
     * @return array
     */
    public function run(string $model, array $input, ?string $webhook = null): array
    {
        try {
            $payload = [
                'version' => $this->getModelVersion($model),
                'input' => $input,
            ];

            if ($webhook) {
                $payload['webhook'] = $webhook;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Token ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/predictions', $payload);

            if ($response->successful()) {
                $result = $response->json();

                Log::info('Replicate prediction created', [
                    'id' => $result['id'] ?? null,
                    'status' => $result['status'] ?? 'unknown',
                ]);

                return [
                    'success' => true,
                    'id' => $result['id'] ?? null,
                    'status' => $result['status'] ?? 'starting',
                    'output' => $result['output'] ?? null,
                    'urls' => [
                        'get' => $result['urls']['get'] ?? null,
                        'cancel' => $result['urls']['cancel'] ?? null,
                    ],
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['detail'] ?? 'Failed to run prediction',
            ];
        } catch (\Exception $e) {
            Log::error('Replicate prediction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get prediction status and results
     *
     * @param string $predictionId
     * @return array
     */
    public function getPrediction(string $predictionId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Token ' . $this->apiKey,
            ])->get($this->baseUrl . '/predictions/' . $predictionId);

            if ($response->successful()) {
                $result = $response->json();

                return [
                    'success' => true,
                    'status' => $result['status'] ?? 'unknown',
                    'output' => $result['output'] ?? null,
                    'error' => $result['error'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to get prediction',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Cancel a running prediction
     *
     * @param string $predictionId
     * @return array
     */
    public function cancelPrediction(string $predictionId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Token ' . $this->apiKey,
            ])->post($this->baseUrl . '/predictions/' . $predictionId . '/cancel');

            if ($response->successful()) {
                return [
                    'success' => true,
                    'status' => 'canceled',
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to cancel prediction',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate workout avatar/image using Stable Diffusion
     *
     * @param string $prompt Description of the workout image
     * @param array $options Additional options (width, height, etc.)
     * @return array
     */
    public function generateWorkoutImage(string $prompt, array $options = []): array
    {
        $input = array_merge([
            'prompt' => $prompt,
            'width' => $options['width'] ?? 1024,
            'height' => $options['height'] ?? 1024,
            'num_outputs' => $options['num_outputs'] ?? 1,
            'negative_prompt' => $options['negative_prompt'] ?? 'blurry, low quality',
        ], $options);

        return $this->run('stability-ai/sdxl', $input);
    }

    /**
     * Generate AI video for CBT or workout demonstrations
     *
     * @param string $prompt Video description
     * @param array $options Additional options
     * @return array
     */
    public function generateVideo(string $prompt, array $options = []): array
    {
        $input = array_merge([
            'prompt' => $prompt,
            'num_frames' => $options['num_frames'] ?? 24,
            'fps' => $options['fps'] ?? 8,
        ], $options);

        return $this->run('stability-ai/stable-video-diffusion', $input);
    }

    /**
     * Upscale an image for better quality
     *
     * @param string $imageUrl URL of the image to upscale
     * @param int $scale Upscale factor (2 or 4)
     * @return array
     */
    public function upscaleImage(string $imageUrl, int $scale = 2): array
    {
        $input = [
            'image' => $imageUrl,
            'scale' => $scale,
        ];

        return $this->run('nightmareai/real-esrgan', $input);
    }

    /**
     * Remove background from an image
     *
     * @param string $imageUrl
     * @return array
     */
    public function removeBackground(string $imageUrl): array
    {
        $input = [
            'image' => $imageUrl,
        ];

        return $this->run('cjwbw/rembg', $input);
    }

    /**
     * Get model version (this should be configured per model)
     * In production, store these in config or database
     *
     * @param string $model
     * @return string
     */
    private function getModelVersion(string $model): string
    {
        // These are example versions - update with actual versions you want to use
        $versions = [
            'stability-ai/sdxl' => '39ed52f2a78e934b3ba6e2a89f5b1c712de7dfea535525255b1aa35c5565e08b',
            'stability-ai/stable-video-diffusion' => '3f0457e4619daac51203dedb472816fd4af51f3149fa7a9e0b5ffcf1b8172438',
            'nightmareai/real-esrgan' => '42fed1c4974146d4d2414e2be2c5277c7fcf05fcc3a73abf41610695738c1d7b',
            'cjwbw/rembg' => 'fb8af171cfa1616ddcf1242c093f9c46bcada5ad4cf6f2fbe8b81b330ec5c003',
        ];

        return $versions[$model] ?? $model;
    }

    /**
     * Test Replicate connectivity
     *
     * @return array
     */
    public function ping(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Token ' . $this->apiKey,
            ])->get($this->baseUrl . '/predictions?limit=1');

            if ($response->successful()) {
                return [
                    'status' => 'success',
                    'message' => 'Successfully connected to Replicate',
                ];
            }

            return [
                'status' => 'failed',
                'message' => 'Failed to connect to Replicate',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
}
