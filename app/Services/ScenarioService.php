<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScenarioService
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.scenario.api_key');
        $this->baseUrl = config('services.scenario.base_url', 'https://api.scenario.gg/v1');
    }

    /**
     * Generate images using Scenario AI
     *
     * @param string $modelId Model ID from Scenario
     * @param string $prompt Text prompt for generation
     * @param array $options Additional generation options
     * @return array
     */
    public function generateImage(string $modelId, string $prompt, array $options = []): array
    {
        try {
            $payload = array_merge([
                'modelId' => $modelId,
                'prompt' => $prompt,
                'numSamples' => $options['num_samples'] ?? 1,
                'quality' => $options['quality'] ?? 'high',
                'width' => $options['width'] ?? 1024,
                'height' => $options['height'] ?? 1024,
            ], $options);

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode('api:' . $this->apiKey),
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/images/generate', $payload);

            if ($response->successful()) {
                $result = $response->json();

                Log::info('Scenario image generation started', [
                    'inference_id' => $result['inference']['id'] ?? null,
                ]);

                return [
                    'success' => true,
                    'inference_id' => $result['inference']['id'] ?? null,
                    'status' => $result['inference']['status'] ?? 'pending',
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Failed to generate image',
            ];
        } catch (\Exception $e) {
            Log::error('Scenario generation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get inference results
     *
     * @param string $inferenceId
     * @return array
     */
    public function getInference(string $inferenceId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode('api:' . $this->apiKey),
            ])->get($this->baseUrl . '/inferences/' . $inferenceId);

            if ($response->successful()) {
                $result = $response->json();

                return [
                    'success' => true,
                    'status' => $result['inference']['status'] ?? 'unknown',
                    'images' => $result['inference']['images'] ?? [],
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to get inference',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate workout illustration
     *
     * @param string $modelId
     * @param string $exerciseName
     * @param string $description
     * @return array
     */
    public function generateWorkoutIllustration(string $modelId, string $exerciseName, string $description): array
    {
        $prompt = "Professional fitness illustration of {$exerciseName}, {$description}, clean background, instructional style";

        return $this->generateImage($modelId, $prompt, [
            'width' => 1024,
            'height' => 768,
            'quality' => 'high',
        ]);
    }

    /**
     * Generate meal/food illustration
     *
     * @param string $modelId
     * @param string $foodName
     * @param string $description
     * @return array
     */
    public function generateMealIllustration(string $modelId, string $foodName, string $description): array
    {
        $prompt = "Professional food photography of {$foodName}, {$description}, appetizing, high quality, clean plate";

        return $this->generateImage($modelId, $prompt, [
            'width' => 1024,
            'height' => 1024,
            'quality' => 'high',
        ]);
    }

    /**
     * List available models
     *
     * @return array
     */
    public function listModels(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode('api:' . $this->apiKey),
            ])->get($this->baseUrl . '/models');

            if ($response->successful()) {
                return [
                    'success' => true,
                    'models' => $response->json()['models'] ?? [],
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to list models',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test Scenario connectivity
     *
     * @return array
     */
    public function ping(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode('api:' . $this->apiKey),
            ])->get($this->baseUrl . '/models?limit=1');

            if ($response->successful()) {
                return [
                    'status' => 'success',
                    'message' => 'Successfully connected to Scenario',
                ];
            }

            return [
                'status' => 'failed',
                'message' => 'Failed to connect to Scenario',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
}
