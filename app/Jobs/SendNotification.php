<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Firebase\JWT\JWT;
use App\Models\User;

class SendNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $userId;
    public $type;
    public $data;
    public $channels;

    /**
     * Create a new job instance.
     */
    public function __construct($userId, $type, $data, $channels = ['email'])
    {
        $this->userId = $userId;
        $this->type = $type;
        $this->data = $data;
        $this->channels = $channels;
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = User::find($this->userId);
        
        if (!$user) {
            \Log::warning("User {$this->userId} not found for notification");
            return;
        }

        try {
            foreach ($this->channels as $channel) {
                switch ($channel) {
                    case 'email':
                        $this->sendEmail($user);
                        break;
                    case 'push':
                        $this->sendPushNotification($user);
                        break;
                    case 'sms':
                        $this->sendSMS($user);
                        break;
                }
            }
        } catch (\Exception $e) {
            \Log::error('Notification sending failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send email notification
     */
    private function sendEmail($user)
    {
        $subject = $this->getEmailSubject();
        $template = $this->getEmailTemplate();
        
        Mail::send($template, $this->data, function ($message) use ($user, $subject) {
            $message->to($user->email, $user->first_name . ' ' . $user->last_name)
                   ->subject($subject);
        });
    }

    /**
     * Send push notification
     */
    private function sendPushNotification($user)
    {
        // Validate FCM token
        if (empty($user->fcm_token) || !is_string($user->fcm_token)) {
            \Log::info('User has no valid FCM token', ['user_id' => $user->id]);
            return;
        }

        $title = $this->getPushTitle();
        $body = $this->getPushBody();
        
        try {
            // Use FCM v1 API (recommended) or legacy API with proper error handling
            $projectId = config('services.fcm.project_id');
            
            if ($projectId) {
                // FCM v1 API implementation
                $this->sendFcmV1Notification($user, $title, $body);
            } else {
                // Fallback to legacy API with improved error handling
                $this->sendFcmLegacyNotification($user, $title, $body);
            }
            
        } catch (\Exception $e) {
            \Log::error('Push notification failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'notification_type' => $this->type
            ]);
            
            // Don't re-throw to avoid failing the entire job
        }
    }

    /**
     * Send FCM v1 API notification
     */
    private function sendFcmV1Notification($user, $title, $body)
    {
        $projectId = config('services.fcm.project_id');
        $serviceAccountPath = config('services.fcm.service_account_path');
        
        if (!$serviceAccountPath || !file_exists($serviceAccountPath)) {
            \Log::warning('FCM service account file not found', [
                'path' => $serviceAccountPath
            ]);
            // Fall back to legacy API
            $this->sendFcmLegacyNotification($user, $title, $body);
            return;
        }

        try {
            // Get OAuth2 access token
            $accessToken = $this->getOAuth2AccessToken($serviceAccountPath);
            
            if (!$accessToken) {
                \Log::warning('Failed to get FCM OAuth2 access token');
                // Fall back to legacy API
                $this->sendFcmLegacyNotification($user, $title, $body);
                return;
            }

            // Send FCM v1 API request
            $response = Http::timeout(30)
                ->retry(2, 1000)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json'
                ])
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token' => $user->fcm_token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body
                        ],
                        'data' => array_map('strval', $this->data), // FCM v1 requires string values
                        'android' => [
                            'notification' => [
                                'icon' => 'ic_notification',
                                'sound' => 'default',
                                'channel_id' => 'default'
                            ]
                        ],
                        'apns' => [
                            'payload' => [
                                'aps' => [
                                    'sound' => 'default',
                                    'badge' => 1
                                ]
                            ]
                        ]
                    ]
                ]);

            if ($response->successful()) {
                $responseData = $response->json();
                \Log::info('FCM v1 notification sent successfully', [
                    'user_id' => $user->id,
                    'message_name' => $responseData['name'] ?? null
                ]);
            } else {
                $errorData = $response->json();
                \Log::error('FCM v1 API request failed', [
                    'user_id' => $user->id,
                    'status_code' => $response->status(),
                    'error' => $errorData['error'] ?? $response->body()
                ]);

                // Handle specific FCM v1 errors
                if (isset($errorData['error']['details'])) {
                    foreach ($errorData['error']['details'] as $detail) {
                        if ($detail['@type'] === 'type.googleapis.com/google.firebase.fcm.v1.FcmError') {
                            if ($detail['errorCode'] === 'UNREGISTERED') {
                                \Log::info('FCM token invalid, clearing from database (v1)', ['user_id' => $user->id]);
                                // Mark token as invalid in database
                                User::where('id', $user->id)->update(['fcm_token' => null]);
                            }
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            \Log::error('FCM v1 notification exception', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Fall back to legacy API on error
            $this->sendFcmLegacyNotification($user, $title, $body);
        }
    }

    /**
     * Get OAuth2 access token for FCM v1 API
     */
    private function getOAuth2AccessToken($serviceAccountPath)
    {
        try {
            $serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);
            
            if (!$serviceAccount || !isset($serviceAccount['private_key'], $serviceAccount['client_email'])) {
                \Log::error('Invalid service account file format');
                return null;
            }

            // Create JWT for OAuth2 using firebase/php-jwt
            $now = time();
            $payload = [
                'iss' => $serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600, // 1 hour
            ];

            $keyId = $serviceAccount['private_key_id'] ?? null;
            $additionalHeaders = $keyId ? ['kid' => $keyId] : [];

            try {
                $jwt = JWT::encode($payload, $serviceAccount['private_key'], 'RS256', $keyId, $additionalHeaders);
            } catch (\Throwable $jwtException) {
                \Log::error('Failed to encode service account JWT', [
                    'error' => $jwtException->getMessage(),
                ]);
                return null;
            }

            // Exchange JWT for access token
            $response = Http::timeout(30)
                ->asForm()
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt
                ]);

            if ($response->successful()) {
                $tokenData = $response->json();
                return $tokenData['access_token'] ?? null;
            } else {
                \Log::error('OAuth2 token request failed', [
                    'status_code' => $response->status(),
                    'response' => $response->body()
                ]);
                return null;
            }

        } catch (\Exception $e) {
            \Log::error('OAuth2 token generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Send FCM legacy API notification with proper error handling
     */
    private function sendFcmLegacyNotification($user, $title, $body)
    {
        $serverKey = config('services.fcm.server_key');
        
        if (!$serverKey) {
            \Log::warning('FCM server key not configured');
            return;
        }

        try {
            $response = Http::timeout(30)
                ->retry(2, 1000)
                ->withHeaders([
                    'Authorization' => 'key=' . $serverKey,
                    'Content-Type' => 'application/json'
                ])
                ->post('https://fcm.googleapis.com/fcm/send', [
                    'to' => $user->fcm_token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                        'icon' => 'ic_notification',
                        'sound' => 'default'
                    ],
                    'data' => $this->data
                ]);

            if ($response->successful()) {
                $responseData = $response->json();
                
                // Check for FCM-specific errors
                if (isset($responseData['failure']) && $responseData['failure'] > 0) {
                    $results = $responseData['results'] ?? [];
                    foreach ($results as $result) {
                        if (isset($result['error'])) {
                            \Log::warning('FCM notification error', [
                                'user_id' => $user->id,
                                'error' => $result['error'],
                                'fcm_token' => substr($user->fcm_token, 0, 20) . '...'
                            ]);
                            
                            // Handle invalid tokens
                            if (in_array($result['error'], ['InvalidRegistration', 'NotRegistered'])) {
                                \Log::info('FCM token invalid, clearing from database', ['user_id' => $user->id]);
                                // Mark token as invalid in database
                                User::where('id', $user->id)->update(['fcm_token' => null]);
                            }
                        }
                    }
                } else {
                    \Log::info('FCM notification sent successfully', [
                        'user_id' => $user->id,
                        'message_id' => $responseData['results'][0]['message_id'] ?? null
                    ]);
                }
            } else {
                \Log::error('FCM API request failed', [
                    'user_id' => $user->id,
                    'status_code' => $response->status(),
                    'response_body' => $response->body()
                ]);
            }
            
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            \Log::error('FCM connection failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            // Re-throw to potentially retry the job
            throw $e;
            
        } catch (\Exception $e) {
            \Log::error('FCM notification exception', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Send SMS notification
     */
    private function sendSMS($user)
    {
        // Implementation for SMS (Twilio, AWS SNS, etc.)
        $message = $this->getSMSMessage();
        
        if ($user->phone_number) {
            // Example Twilio implementation
            // Twilio::message($user->phone_number, $message);
        }
    }

    /**
     * Get email subject based on notification type
     */
    private function getEmailSubject()
    {
        return match($this->type) {
            'nutrition_reminder' => 'Time to log your meal!',
            'workout_reminder' => 'Your workout is scheduled now',
            'goal_achieved' => 'Congratulations! Goal achieved',
            'weekly_report' => 'Your weekly progress report',
            default => 'BodyF1rst Notification'
        };
    }

    /**
     * Get email template based on notification type
     */
    private function getEmailTemplate()
    {
        return match($this->type) {
            'nutrition_reminder' => 'emails.nutrition_reminder',
            'workout_reminder' => 'emails.workout_reminder',
            'goal_achieved' => 'emails.goal_achieved',
            'weekly_report' => 'emails.weekly_report',
            default => 'emails.generic_notification'
        };
    }

    /**
     * Get push notification title
     */
    private function getPushTitle()
    {
        return match($this->type) {
            'nutrition_reminder' => 'Meal Time!',
            'workout_reminder' => 'Workout Time!',
            'goal_achieved' => 'Goal Achieved! 🎉',
            'weekly_report' => 'Weekly Report Ready',
            default => 'BodyF1rst'
        };
    }

    /**
     * Get push notification body
     */
    private function getPushBody()
    {
        return match($this->type) {
            'nutrition_reminder' => 'Don\'t forget to log your ' . ($this->data['meal_type'] ?? 'meal'),
            'workout_reminder' => 'Your ' . ($this->data['workout_name'] ?? 'workout') . ' is scheduled now',
            'goal_achieved' => 'You\'ve reached your ' . ($this->data['goal_type'] ?? 'goal') . '!',
            'weekly_report' => 'Your progress report is ready to view',
            default => $this->data['message'] ?? 'You have a new notification'
        };
    }

    /**
     * Get SMS message
     */
    private function getSMSMessage()
    {
        return match($this->type) {
            'nutrition_reminder' => 'BodyF1rst: Time to log your ' . ($this->data['meal_type'] ?? 'meal') . '!',
            'workout_reminder' => 'BodyF1rst: Your workout is scheduled now!',
            'goal_achieved' => 'BodyF1rst: Congratulations! You\'ve achieved your goal!',
            default => 'BodyF1rst: ' . ($this->data['message'] ?? 'You have a new notification')
        };
    }
}
