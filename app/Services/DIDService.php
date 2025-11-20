<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DIDService
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.did.api_key');
        $this->baseUrl = config('services.did.base_url', 'https://api.d-id.com');
    }

    /**
     * Create a talking photo video
     *
     * @param string $sourceUrl URL of the image
     * @param string $script Text to speak
     * @param array $options Additional options
     * @return array
     */
    public function createTalkingPhoto(string $sourceUrl, string $script, array $options = []): array
    {
        try {
            $payload = [
                'source_url' => $sourceUrl,
                'script' => [
                    'type' => 'text',
                    'input' => $script,
                    'provider' => [
                        'type' => $options['voice_provider'] ?? 'microsoft',
                        'voice_id' => $options['voice_id'] ?? 'en-US-JennyNeural',
                    ],
                ],
                'config' => [
                    'fluent' => $options['fluent'] ?? true,
                    'pad_audio' => $options['pad_audio'] ?? 0,
                ],
            ];

            if (isset($options['webhook_url'])) {
                $payload['webhook'] = $options['webhook_url'];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode('api:' . $this->apiKey),
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/talks', $payload);

            if ($response->successful()) {
                $result = $response->json();

                Log::info('D-ID video creation started', [
                    'id' => $result['id'] ?? null,
                    'status' => $result['status'] ?? 'created',
                ]);

                return [
                    'success' => true,
                    'id' => $result['id'] ?? null,
                    'status' => $result['status'] ?? 'created',
                    'created_at' => $result['created_at'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['error'] ?? 'Failed to create video',
            ];
        } catch (\Exception $e) {
            Log::error('D-ID video creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get video status and result
     *
     * @param string $videoId
     * @return array
     */
    public function getVideo(string $videoId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode('api:' . $this->apiKey),
            ])->get($this->baseUrl . '/talks/' . $videoId);

            if ($response->successful()) {
                $result = $response->json();

                return [
                    'success' => true,
                    'status' => $result['status'] ?? 'unknown',
                    'result_url' => $result['result_url'] ?? null,
                    'duration' => $result['duration'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to get video',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete a video
     *
     * @param string $videoId
     * @return array
     */
    public function deleteVideo(string $videoId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode('api:' . $this->apiKey),
            ])->delete($this->baseUrl . '/talks/' . $videoId);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Video deleted successfully',
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to delete video',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate CBT program video with talking therapist
     *
     * @param string $therapistImageUrl URL of therapist image
     * @param string $script CBT content script
     * @return array
     */
    public function generateCBTVideo(string $therapistImageUrl, string $script): array
    {
        return $this->createTalkingPhoto($therapistImageUrl, $script, [
            'voice_provider' => 'microsoft',
            'voice_id' => 'en-US-AriaNeural', // Calm, professional female voice
            'fluent' => true,
            'pad_audio' => 0.5,
        ]);
    }

    /**
     * Generate workout instruction video
     *
     * @param string $trainerImageUrl
     * @param string $instructions
     * @return array
     */
    public function generateWorkoutInstructionVideo(string $trainerImageUrl, string $instructions): array
    {
        return $this->createTalkingPhoto($trainerImageUrl, $instructions, [
            'voice_provider' => 'microsoft',
            'voice_id' => 'en-US-GuyNeural', // Energetic male voice
            'fluent' => true,
            'pad_audio' => 0.3,
        ]);
    }

    /**
     * Generate nutrition advice video
     *
     * @param string $nutritionistImageUrl
     * @param string $advice
     * @return array
     */
    public function generateNutritionAdviceVideo(string $nutritionistImageUrl, string $advice): array
    {
        return $this->createTalkingPhoto($nutritionistImageUrl, $advice, [
            'voice_provider' => 'microsoft',
            'voice_id' => 'en-US-JennyNeural', // Friendly female voice
            'fluent' => true,
            'pad_audio' => 0.4,
        ]);
    }

    /**
     * List all videos
     *
     * @param int $limit
     * @return array
     */
    public function listVideos(int $limit = 100): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode('api:' . $this->apiKey),
            ])->get($this->baseUrl . '/talks', [
                'limit' => $limit,
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'videos' => $response->json()['talks'] ?? [],
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to list videos',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get available voices
     *
     * @return array
     */
    public function getVoices(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode('api:' . $this->apiKey),
            ])->get($this->baseUrl . '/tts/voices');

            if ($response->successful()) {
                return [
                    'success' => true,
                    'voices' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to get voices',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test D-ID connectivity
     *
     * @return array
     */
    public function ping(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode('api:' . $this->apiKey),
            ])->get($this->baseUrl . '/talks?limit=1');

            if ($response->successful()) {
                return [
                    'status' => 'success',
                    'message' => 'Successfully connected to D-ID',
                ];
            }

            return [
                'status' => 'failed',
                'message' => 'Failed to connect to D-ID',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
}
