<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ElevenLabsService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.elevenlabs.io/v1';
    private string $defaultVoiceId;

    public function __construct()
    {
        $this->apiKey = config('services.elevenlabs.api_key');
        $this->defaultVoiceId = config('services.elevenlabs.voice_id', 'EXAVITQu4vr4xnSDxMaL'); // Sarah voice
    }

    /**
     * Convert text to speech
     *
     * @param string $text Text to convert
     * @param string|null $voiceId Voice ID (null uses default)
     * @param array $options Additional options
     * @return array
     */
    public function textToSpeech(string $text, ?string $voiceId = null, array $options = []): array
    {
        try {
            $voiceId = $voiceId ?? $this->defaultVoiceId;

            $payload = [
                'text' => $text,
                'model_id' => $options['model_id'] ?? 'eleven_monolingual_v1',
                'voice_settings' => [
                    'stability' => $options['stability'] ?? 0.5,
                    'similarity_boost' => $options['similarity_boost'] ?? 0.75,
                ],
            ];

            $response = Http::withHeaders([
                'xi-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'audio/mpeg',
            ])->post($this->baseUrl . "/text-to-speech/{$voiceId}", $payload);

            if ($response->successful()) {
                // Save audio file to S3
                $filename = 'audio/tts_' . time() . '_' . uniqid() . '.mp3';
                $audioContent = $response->body();

                Storage::disk('s3-media')->put($filename, $audioContent);
                $audioUrl = Storage::disk('s3-media')->url($filename);

                Log::info('ElevenLabs TTS generated', [
                    'voice_id' => $voiceId,
                    'text_length' => strlen($text),
                    'audio_url' => $audioUrl,
                ]);

                return [
                    'success' => true,
                    'audio_url' => $audioUrl,
                    'filename' => $filename,
                    'size' => strlen($audioContent),
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to generate speech',
            ];
        } catch (\Exception $e) {
            Log::error('ElevenLabs TTS failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate workout instruction audio
     *
     * @param string $instructions Workout instructions text
     * @param string|null $voiceId Optional voice ID
     * @return array
     */
    public function generateWorkoutAudio(string $instructions, ?string $voiceId = null): array
    {
        return $this->textToSpeech($instructions, $voiceId, [
            'model_id' => 'eleven_monolingual_v1',
            'stability' => 0.6,
            'similarity_boost' => 0.8,
        ]);
    }

    /**
     * Generate CBT program audio content
     *
     * @param string $content CBT program text content
     * @param string|null $voiceId Optional voice ID (use calming voice)
     * @return array
     */
    public function generateCBTAudio(string $content, ?string $voiceId = null): array
    {
        return $this->textToSpeech($content, $voiceId, [
            'model_id' => 'eleven_monolingual_v1',
            'stability' => 0.7, // More stable/calm for CBT content
            'similarity_boost' => 0.7,
        ]);
    }

    /**
     * Generate meditation/relaxation audio
     *
     * @param string $script Meditation script
     * @param string|null $voiceId Optional voice ID
     * @return array
     */
    public function generateMeditationAudio(string $script, ?string $voiceId = null): array
    {
        return $this->textToSpeech($script, $voiceId, [
            'model_id' => 'eleven_monolingual_v1',
            'stability' => 0.8, // Very stable for meditation
            'similarity_boost' => 0.6,
        ]);
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
                'xi-api-key' => $this->apiKey,
            ])->get($this->baseUrl . '/voices');

            if ($response->successful()) {
                $result = $response->json();

                return [
                    'success' => true,
                    'voices' => $result['voices'] ?? [],
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
     * Get voice details
     *
     * @param string $voiceId
     * @return array
     */
    public function getVoice(string $voiceId): array
    {
        try {
            $response = Http::withHeaders([
                'xi-api-key' => $this->apiKey,
            ])->get($this->baseUrl . "/voices/{$voiceId}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'voice' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to get voice details',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get user subscription info
     *
     * @return array
     */
    public function getSubscriptionInfo(): array
    {
        try {
            $response = Http::withHeaders([
                'xi-api-key' => $this->apiKey,
            ])->get($this->baseUrl . '/user/subscription');

            if ($response->successful()) {
                $result = $response->json();

                return [
                    'success' => true,
                    'character_count' => $result['character_count'] ?? 0,
                    'character_limit' => $result['character_limit'] ?? 0,
                    'can_extend_character_limit' => $result['can_extend_character_limit'] ?? false,
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to get subscription info',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test ElevenLabs connectivity
     *
     * @return array
     */
    public function ping(): array
    {
        try {
            $response = Http::withHeaders([
                'xi-api-key' => $this->apiKey,
            ])->get($this->baseUrl . '/voices');

            if ($response->successful()) {
                return [
                    'status' => 'success',
                    'message' => 'Successfully connected to ElevenLabs',
                ];
            }

            return [
                'status' => 'failed',
                'message' => 'Failed to connect to ElevenLabs',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
}
