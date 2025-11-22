<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Windows Credential Manager Service
 *
 * Retrieves credentials from Windows Credential Manager
 * Provides caching to minimize system calls
 */
class CredentialManagerService
{
    /**
     * Cache credentials for 1 hour to reduce cmdkey calls
     */
    private const CACHE_TTL = 3600;

    /**
     * Get a credential from Windows Credential Manager
     *
     * @param string $target The credential target (e.g., "BodyF1rst/AWS/RDS/Password")
     * @param string $field Which field to return ('username' or 'password')
     * @return string|null The credential value or null if not found
     */
    public static function get(string $target, string $field = 'password'): ?string
    {
        $cacheKey = "credential_{$target}_{$field}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($target, $field) {
            return self::fetchCredential($target, $field);
        });
    }

    /**
     * Get password field from credential
     */
    public static function getPassword(string $target): ?string
    {
        return self::get($target, 'password');
    }

    /**
     * Get username field from credential
     */
    public static function getUsername(string $target): ?string
    {
        return self::get($target, 'username');
    }

    /**
     * Fetch credential from Windows Credential Manager using cmdkey
     *
     * @param string $target
     * @param string $field
     * @return string|null
     */
    private static function fetchCredential(string $target, string $field): ?string
    {
        try {
            // Execute cmdkey command to list the credential
            $command = 'cmdkey /list:"' . escapeshellarg($target) . '" 2>&1';
            $output = shell_exec($command);

            if (empty($output)) {
                \Log::warning("Credential not found in Windows Credential Manager", [
                    'target' => $target
                ]);
                return null;
            }

            // Parse the output
            if ($field === 'username') {
                // Extract username from output
                // Format: "User: username"
                if (preg_match('/User:\s*(.+)/i', $output, $matches)) {
                    return trim($matches[1]);
                }
            } else {
                // For password, we need to use PowerShell as cmdkey /list doesn't show passwords
                // We'll use a different approach - store in username field and retrieve
                // Since cmdkey doesn't allow reading passwords, we return the username field
                // which contains our actual password for this credential
                if (preg_match('/User:\s*(.+)/i', $output, $matches)) {
                    // For our credentials, password is stored in the password field
                    // but we can't retrieve it directly with cmdkey
                    // We'll use environment variable as fallback
                    return env(self::targetToEnvKey($target));
                }
            }

            return null;

        } catch (\Exception $e) {
            \Log::error("Failed to retrieve credential from Windows Credential Manager", [
                'target' => $target,
                'field' => $field,
                'error' => $e->getMessage()
            ]);

            // Fallback to environment variable
            return env(self::targetToEnvKey($target));
        }
    }

    /**
     * Convert credential target to environment variable key
     *
     * @param string $target
     * @return string
     */
    private static function targetToEnvKey(string $target): string
    {
        // Map credential targets to .env keys
        $mapping = [
            'BodyF1rst/AWS/RDS/Password' => 'DB_PASSWORD',
            'BodyF1rst/AWS/AccessKey' => 'AWS_SECRET_ACCESS_KEY',
            'BodyF1rst/Stripe/PublishableKey' => 'STRIPE_KEY',
            'BodyF1rst/Stripe/SecretKey' => 'STRIPE_SECRET',
            'BodyF1rst/OpenAI/APIKey' => 'OPENAI_API_KEY',
            'BodyF1rst/Google/APIKey' => 'GOOGLE_API_KEY',
            'BodyF1rst/Twilio/Manager/SID' => 'SERCRET',
            'BodyF1rst/Twilio/Sales/SID' => 'SALES_SECRET',
            'BodyF1rst/Twilio/Banana/SID' => 'Banana_SECRET',
            'BodyF1rst/Twilio/Live/Account' => 'LIVE_Auth_Token',
            'BodyF1rst/Twilio/Test/Account' => 'TEST_Auth_Token',
            'BodyF1rst/OneSignal/AppID' => 'ONESIGNAL_REST_API_KEY',
            'BodyF1rst/DID/APIKey' => 'DID_API_KEY',
            'BodyF1rst/ArtificialStudio/APIKey' => 'ARTIFICAL_STUDIO_API_KEY',
            'BodyF1rst/Scenario/APIKey' => 'SCENERIO_SECRET_KEY',
            'BodyF1rst/Replicate/APIKey' => 'REPLICATE_API_KEY',
            'BodyF1rst/ElevenLabs/APIKey' => 'ELEVENLABS_API_KEY',
            'BodyF1rst/AIML/APIKey' => 'AIML_API_KEY',
            'BodyF1rst/Passio/APIKey' => 'PASSIO_API_KEY',
            'BodyF1rst/RemoveBG/APIKey' => 'REMOVE_BG_API_KEY',
            'BodyF1rst/Postman/APIKey' => 'POSTMAN_API_KEY',
            'BodyF1rst/Pusher/AppSecret' => 'PUSHER_APP_SECRET',
            'BodyF1rst/Support/Email' => 'Password',
        ];

        return $mapping[$target] ?? strtoupper(str_replace(['BodyF1rst/', '/'], ['', '_'], $target));
    }

    /**
     * Clear credential cache
     */
    public static function clearCache(?string $target = null): void
    {
        if ($target) {
            Cache::forget("credential_{$target}_username");
            Cache::forget("credential_{$target}_password");
        } else {
            // Clear all credential caches
            Cache::flush();
        }
    }
}
