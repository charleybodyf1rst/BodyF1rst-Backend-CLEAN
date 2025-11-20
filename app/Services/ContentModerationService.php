<?php

namespace App\Services;

use App\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ContentModerationService
{
    /**
     * Profanity filter word list
     * In production, this should be loaded from a comprehensive database or external service
     */
    private $profanityList = [
        'badword1', 'badword2', 'profanity', 'offensive', 'inappropriate',
        // Add comprehensive profanity list here
    ];

    /**
     * Check message for profanity
     *
     * @param string $message
     * @return array
     */
    public function checkProfanity(string $message): array
    {
        $foundWords = [];
        $cleanMessage = $message;

        foreach ($this->profanityList as $word) {
            $pattern = '/\b' . preg_quote($word, '/') . '\b/i';
            if (preg_match($pattern, $message)) {
                $foundWords[] = $word;
                // Replace with asterisks
                $cleanMessage = preg_replace($pattern, str_repeat('*', strlen($word)), $cleanMessage);
            }
        }

        return [
            'has_profanity' => count($foundWords) > 0,
            'found_words' => $foundWords,
            'clean_message' => $cleanMessage,
            'severity' => $this->calculateSeverity(count($foundWords))
        ];
    }

    /**
     * Detect nudity in images using AI (AWS Rekognition or similar)
     *
     * @param string $imagePath
     * @return array
     */
    public function detectNudity(string $imagePath): array
    {
        try {
            // AWS Rekognition example
            // In production, configure AWS SDK and use actual Rekognition service

            // Placeholder response structure
            $response = [
                'has_nudity' => false,
                'confidence' => 0,
                'labels' => [],
                'safe_for_work' => true
            ];

            // Example using AWS Rekognition (requires AWS SDK)
            /*
            $client = new \Aws\Rekognition\RekognitionClient([
                'version' => 'latest',
                'region'  => env('AWS_DEFAULT_REGION'),
                'credentials' => [
                    'key'    => env('AWS_ACCESS_KEY_ID'),
                    'secret' => env('AWS_SECRET_ACCESS_KEY'),
                ]
            ]);

            $result = $client->detectModerationLabels([
                'Image' => [
                    'S3Object' => [
                        'Bucket' => env('AWS_BUCKET'),
                        'Name' => $imagePath,
                    ],
                ],
                'MinConfidence' => 60,
            ]);

            $moderationLabels = $result['ModerationLabels'];

            foreach ($moderationLabels as $label) {
                if (in_array($label['Name'], ['Explicit Nudity', 'Suggestive'])) {
                    $response['has_nudity'] = true;
                    $response['confidence'] = $label['Confidence'];
                    $response['labels'][] = $label['Name'];
                    $response['safe_for_work'] = false;
                }
            }
            */

            // Alternative: Use third-party API like Sightengine
            // $this->checkWithSightengine($imagePath);

            return $response;
        } catch (\Exception $e) {
            Log::error('Nudity detection failed: ' . $e->getMessage());
            return [
                'has_nudity' => false,
                'confidence' => 0,
                'labels' => [],
                'safe_for_work' => true,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Alternative: Use Sightengine API for image moderation
     *
     * @param string $imageUrl
     * @return array
     */
    private function checkWithSightengine(string $imageUrl): array
    {
        try {
            $apiUser = env('SIGHTENGINE_API_USER');
            $apiSecret = env('SIGHTENGINE_API_SECRET');

            if (!$apiUser || !$apiSecret) {
                return ['error' => 'Sightengine API credentials not configured'];
            }

            $response = Http::get('https://api.sightengine.com/1.0/check.json', [
                'url' => $imageUrl,
                'models' => 'nudity,wad,offensive,text-content',
                'api_user' => $apiUser,
                'api_secret' => $apiSecret,
            ]);

            $data = $response->json();

            return [
                'has_nudity' => ($data['nudity']['safe'] ?? 1) < 0.5,
                'confidence' => (1 - ($data['nudity']['safe'] ?? 1)) * 100,
                'labels' => $data['nudity'] ?? [],
                'safe_for_work' => ($data['nudity']['safe'] ?? 1) >= 0.5,
                'raw_response' => $data
            ];
        } catch (\Exception $e) {
            Log::error('Sightengine API failed: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Moderate message content
     *
     * @param Message $message
     * @return array
     */
    public function moderateMessage(Message $message): array
    {
        $flags = [];

        // Check text for profanity
        if ($message->message) {
            $profanityCheck = $this->checkProfanity($message->message);

            if ($profanityCheck['has_profanity']) {
                $flags[] = [
                    'type' => 'profanity',
                    'severity' => $profanityCheck['severity'],
                    'details' => $profanityCheck,
                    'auto_action' => $this->getAutoAction('profanity', $profanityCheck['severity'])
                ];

                // Update message with clean version
                $message->message = $profanityCheck['clean_message'];
            }
        }

        // Check attachments for inappropriate content
        if ($message->attachments && is_array($message->attachments)) {
            foreach ($message->attachments as $attachment) {
                if ($this->isImage($attachment['type'] ?? '')) {
                    $nudityCheck = $this->detectNudity($attachment['url'] ?? '');

                    if ($nudityCheck['has_nudity']) {
                        $flags[] = [
                            'type' => 'nudity',
                            'severity' => $this->calculateNuditySeverity($nudityCheck['confidence']),
                            'details' => $nudityCheck,
                            'auto_action' => $this->getAutoAction('nudity', $nudityCheck['confidence'])
                        ];
                    }
                }
            }
        }

        return [
            'needs_review' => count($flags) > 0,
            'flags' => $flags,
            'auto_flagged' => count($flags) > 0
        ];
    }

    /**
     * Calculate severity based on profanity count
     *
     * @param int $count
     * @return string
     */
    private function calculateSeverity(int $count): string
    {
        if ($count >= 5) return 'critical';
        if ($count >= 3) return 'high';
        if ($count >= 1) return 'medium';
        return 'low';
    }

    /**
     * Calculate nudity severity based on confidence
     *
     * @param float $confidence
     * @return string
     */
    private function calculateNuditySeverity(float $confidence): string
    {
        if ($confidence >= 90) return 'critical';
        if ($confidence >= 70) return 'high';
        if ($confidence >= 50) return 'medium';
        return 'low';
    }

    /**
     * Determine auto action based on flag type and severity
     *
     * @param string $flagType
     * @param mixed $severity
     * @return string
     */
    private function getAutoAction(string $flagType, $severity): string
    {
        if ($flagType === 'nudity') {
            if ($severity >= 90) return 'block';
            if ($severity >= 70) return 'blur';
            return 'flag';
        }

        if ($flagType === 'profanity') {
            if ($severity === 'critical') return 'filter';
            if ($severity === 'high') return 'warn';
            return 'flag';
        }

        return 'flag';
    }

    /**
     * Check if file is an image
     *
     * @param string $mimeType
     * @return bool
     */
    private function isImage(string $mimeType): bool
    {
        return in_array($mimeType, [
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'
        ]);
    }

    /**
     * Detect spam patterns in message
     *
     * @param string $message
     * @param int $userId
     * @return array
     */
    public function detectSpam(string $message, int $userId): array
    {
        $spamIndicators = 0;
        $reasons = [];

        // Check for excessive URLs
        $urlCount = preg_match_all('/https?:\/\//', $message);
        if ($urlCount > 3) {
            $spamIndicators++;
            $reasons[] = 'Excessive URLs';
        }

        // Check for excessive capitalization
        $upperCaseRatio = strlen(preg_replace('/[^A-Z]/', '', $message)) / max(strlen($message), 1);
        if ($upperCaseRatio > 0.5 && strlen($message) > 10) {
            $spamIndicators++;
            $reasons[] = 'Excessive capitalization';
        }

        // Check for repeated characters
        if (preg_match('/(.)\1{4,}/', $message)) {
            $spamIndicators++;
            $reasons[] = 'Repeated characters';
        }

        // Check message frequency (requires checking recent messages)
        // This would query the database for recent messages from the user

        return [
            'is_spam' => $spamIndicators >= 2,
            'confidence' => min(($spamIndicators / 5) * 100, 100),
            'reasons' => $reasons,
            'spam_score' => $spamIndicators
        ];
    }
}
