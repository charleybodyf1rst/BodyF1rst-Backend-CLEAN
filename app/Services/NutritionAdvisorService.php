<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NutritionAdvisorService
{
    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly PassioTokenService $tokenService;

    public function __construct(PassioTokenService $tokenService)
    {
        $this->apiKey = config('services.passio.api_key');
        $this->baseUrl = 'https://api.passiolife.com/v2/products/nutrition-advisor';
        $this->tokenService = $tokenService;
    }

    /**
     * Create a new thread with the Nutrition Advisor
     */
    public function createThread(bool $plainText = false): array
    {
        try {
            $token = $this->tokenService->getAccessToken();
            
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json'
                ])
                ->post('https://api.passiolife.com/v2/products/nutrition-advisor/threads', [
                    'plainText' => $plainText
                ]);

            if (!$response->successful()) {
                throw new \Exception('Failed to create nutrition advisor thread: ' . $response->body());
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Failed to create nutrition advisor thread', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Add a message to an existing thread
     */
    public function addMessage(string $threadId, string $message, array $inputSensors = []): array
    {
        try {
            $token = $this->tokenService->getAccessToken();
            
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json'
            ])->post("{$this->baseUrl}/threads/{$threadId}/messages", [
                'message' => $message,
                'inputSensors' => $inputSensors
            ]);

            if (!$response->successful()) {
                throw new \Exception('Failed to add message to thread: ' . $response->body());
            }

            $responseData = $response->json();
            
            // Log token usage if available
            if (isset($responseData['usage'])) {
                Log::info('Nutrition Advisor API usage', [
                    'thread_id' => $threadId,
                    'tokens_used' => $responseData['usage']['tokensUsed'] ?? 0,
                    'model' => $responseData['usage']['model'] ?? 'unknown'
                ]);
            }

            return $responseData;
        } catch (\Exception $e) {
            Log::error('Failed to add message to nutrition advisor thread', [
                'thread_id' => $threadId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Execute a vision tool on an image
     */
    public function executeVisionTool(string $threadId, string $toolName, $imageFile): array
    {
        try {
            $token = $this->tokenService->getAccessToken();
            
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}"
            ])->attach('video_file', $imageFile, 'image.jpg')
              ->post("{$this->baseUrl}/threads/{$threadId}/messages/tools/vision/{$toolName}");

            if (!$response->successful()) {
                throw new \Exception('Failed to execute vision tool: ' . $response->body());
            }

            $responseData = $response->json();
            
            Log::info('Vision tool executed', [
                'thread_id' => $threadId,
                'tool_name' => $toolName,
                'message_id' => $responseData['messageId'] ?? 'unknown'
            ]);

            return $responseData;
        } catch (\Exception $e) {
            Log::error('Failed to execute vision tool', [
                'thread_id' => $threadId,
                'tool_name' => $toolName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Execute a target tool on a message
     */
    public function executeTargetTool(string $threadId, string $toolName, string $messageId): array
    {
        try {
            $token = $this->tokenService->getAccessToken();
            
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json'
            ])->post("{$this->baseUrl}/threads/{$threadId}/messages/tools/target/{$toolName}", [
                'messageId' => $messageId
            ]);

            if (!$response->successful()) {
                throw new \Exception('Failed to execute target tool: ' . $response->body());
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Failed to execute target tool', [
                'thread_id' => $threadId,
                'tool_name' => $toolName,
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Fulfill a data request from the advisor
     */
    public function fulfillDataRequest(string $threadId, string $messageId, string $runId, string $toolCallId, $data): array
    {
        try {
            $token = $this->tokenService->getAccessToken();
            
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json'
            ])->post("{$this->baseUrl}/threads/{$threadId}/messages/{$messageId}/respond", [
                'data' => $data,
                'runId' => $runId,
                'toolCallId' => $toolCallId
            ]);

            if (!$response->successful()) {
                throw new \Exception('Failed to fulfill data request: ' . $response->body());
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Failed to fulfill data request', [
                'thread_id' => $threadId,
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get available tools
     */
    public function getAvailableTools(): array
    {
        try {
            $token = $this->tokenService->getAccessToken();
            
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}"
            ])->get("{$this->baseUrl}/tools");

            if (!$response->successful()) {
                throw new \Exception('Failed to get available tools: ' . $response->body());
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Failed to get available tools', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Generate intelligence profile from user input
     */
    public function generateIntelligenceProfile(string $content): array
    {
        try {
            $token = $this->tokenService->getAccessToken();
            
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json'
            ])->post('https://api.passiolife.com/v2/products/nutrition-advisor/sdk/intelligence-profile', [
                'content' => $content
            ]);

            if (!$response->successful()) {
                throw new \Exception('Failed to generate intelligence profile: ' . $response->body());
            }

            $profileData = $response->json();
            
            Log::info('Intelligence profile generated', [
                'profile_id' => $profileData['profileId'] ?? 'unknown'
            ]);

            return $profileData;
        } catch (\Exception $e) {
            Log::error('Failed to generate intelligence profile', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
