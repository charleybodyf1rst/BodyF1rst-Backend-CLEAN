<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Aws\SecretsManager\SecretsManagerClient;
use Aws\Exception\AwsException;

class PassioTokenService
{
    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly string $tokenCacheKey;

    public function __construct()
    {
        $this->apiKey = $this->getPassioApiKey();
        $this->baseUrl = config('services.passio.base_url');
        $this->tokenCacheKey = 'passio_access_token';
    }

    /**
     * Get Passio API key from AWS Secrets Manager
     */
    private function getPassioApiKey(): string
    {
        try {
            $client = new SecretsManagerClient([
                'version' => 'latest',
                'region' => config('aws.region', 'us-east-1')
            ]);

            $result = $client->getSecretValue([
                'SecretId' => 'bodyf1rst/prod/passio-api-key'
            ]);

            $secret = json_decode($result['SecretString'], true);
            return $secret['api_key'];
        } catch (AwsException $e) {
            Log::error('Failed to retrieve Passio API key from Secrets Manager', [
                'error' => $e->getMessage()
            ]);
            // Fallback to environment variable for local development
            return config('services.passio.api_key', '');
        }
    }

    /**
     * Get a valid access token, refreshing if necessary
     */
    public function getAccessToken(): string
    {
        $cachedToken = Cache::get($this->tokenCacheKey);
        
        if ($cachedToken && $this->isTokenValid($cachedToken)) {
            return $cachedToken['access_token'];
        }

        return $this->refreshAccessToken();
    }

    /**
     * Refresh the access token from Passio API
     */
    private function refreshAccessToken(): string
    {
        try {
            $tokenData = $this->fetchNewToken();
            return $tokenData['access_token'];

        } catch (\Exception $e) {
            Log::error('Error refreshing Passio access token', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Fetch a new access token from Passio API v2
     */
    private function fetchNewToken(): array
    {
        try {
            $response = Http::timeout(30)
                ->post("https://api.passiolife.com/v2/token-cache/unified/oauth/token/{$this->apiKey}");

            if (!$response->successful()) {
                throw new \Exception('Failed to fetch Passio access token: ' . $response->body());
            }

            $tokenData = $response->json();
            
            // Cache the token with a buffer (expire 5 minutes before actual expiry)
            $expiresIn = ($tokenData['expires_in'] ?? 3600) - 300;
            Cache::put($this->tokenCacheKey, $tokenData, $expiresIn);
            
            Log::info('Passio access token refreshed', [
                'expires_in' => $tokenData['expires_in'] ?? 3600,
                'scope' => $tokenData['scope'] ?? 'unknown',
                'token_type' => $tokenData['token_type'] ?? 'Bearer'
            ]);

            return $tokenData;
        } catch (\Exception $e) {
            Log::error('Failed to fetch Passio access token', [
                'error' => $e->getMessage(),
                'api_key_prefix' => substr($this->apiKey, 0, 8) . '...'
            ]);
            throw $e;
        }
    }

    /**
     * Check if the cached token is still valid
     */
    private function isTokenValid(?array $tokenData): bool
    {
        if (!$tokenData || !isset($tokenData['access_token'])) {
            return false;
        }

        // Check if token has expired (with 5 minute buffer)
        if (isset($tokenData['expires_at'])) {
            return now()->addMinutes(5)->isBefore($tokenData['expires_at']);
        }

        return true;
    }

    /**
     * Get token usage information from response headers
     */
    public function getTokenUsage(array $responseHeaders): array
    {
        return [
            'budget_cap' => $responseHeaders['X-Budget-Cap'][0] ?? null,
            'period_usage' => $responseHeaders['X-Period-Usage'][0] ?? null,
            'request_usage' => $responseHeaders['X-Request-Usage'][0] ?? null,
        ];
    }

    /**
     * Make authenticated request to Passio API
     */
    public function makeAuthenticatedRequest(string $method, string $endpoint, array $data = []): array
    {
        $token = $this->getAccessToken();
        
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => '*/*',
            'Content-Type' => 'application/json'
        ])->{strtolower($method)}($this->baseUrl . $endpoint, $data);

        // Log token usage
        $tokenUsage = $this->getTokenUsage($response->headers());
        Log::info('Passio API request completed', [
            'endpoint' => $endpoint,
            'token_usage' => $tokenUsage
        ]);

        if (!$response->successful()) {
            Log::error('Passio API request failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'response' => $response->body()
            ]);
            throw new \Exception("Passio API request failed: {$response->status()}");
        }

        return $response->json();
    }
}
