<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioService
{
    private string $accountSid;
    private string $authToken;
    private string $phoneNumber;
    private string $baseUrl;

    public function __construct()
    {
        $this->accountSid = config('services.twilio.account_sid');
        $this->authToken = config('services.twilio.auth_token');
        $this->phoneNumber = config('services.twilio.phone_number');
        $this->baseUrl = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}";
    }

    /**
     * Send SMS message
     *
     * @param string $to Recipient phone number (E.164 format)
     * @param string $message Message content
     * @return array
     */
    public function sendSMS(string $to, string $message): array
    {
        try {
            $response = Http::asForm()
                ->withBasicAuth($this->accountSid, $this->authToken)
                ->post($this->baseUrl . '/Messages.json', [
                    'From' => $this->phoneNumber,
                    'To' => $to,
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                $result = $response->json();

                Log::info('Twilio SMS sent', [
                    'to' => $to,
                    'sid' => $result['sid'] ?? null,
                ]);

                return [
                    'success' => true,
                    'sid' => $result['sid'] ?? null,
                    'status' => $result['status'] ?? 'sent',
                ];
            }

            Log::error('Twilio SMS failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Failed to send SMS',
            ];
        } catch (\Exception $e) {
            Log::error('Twilio SMS exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send verification code via SMS
     *
     * @param string $phoneNumber
     * @param string $code
     * @return array
     */
    public function sendVerificationCode(string $phoneNumber, string $code): array
    {
        $message = "Your BodyF1rst verification code is: {$code}. This code will expire in 10 minutes.";
        return $this->sendSMS($phoneNumber, $message);
    }

    /**
     * Send appointment reminder via SMS
     *
     * @param string $phoneNumber
     * @param string $appointmentDetails
     * @param string $dateTime
     * @return array
     */
    public function sendAppointmentReminder(string $phoneNumber, string $appointmentDetails, string $dateTime): array
    {
        $message = "Reminder: You have an appointment for {$appointmentDetails} scheduled at {$dateTime}. Reply CANCEL to cancel.";
        return $this->sendSMS($phoneNumber, $message);
    }

    /**
     * Send workout reminder via SMS
     *
     * @param string $phoneNumber
     * @param string $workoutName
     * @param string $time
     * @return array
     */
    public function sendWorkoutReminder(string $phoneNumber, string $workoutName, string $time): array
    {
        $message = "Time for your {$workoutName} workout at {$time}! Let's crush it! 💪";
        return $this->sendSMS($phoneNumber, $message);
    }

    /**
     * Send bulk SMS to multiple recipients
     *
     * @param array $recipients Array of phone numbers
     * @param string $message
     * @return array
     */
    public function sendBulkSMS(array $recipients, string $message): array
    {
        $results = [
            'sent' => 0,
            'failed' => 0,
            'details' => [],
        ];

        foreach ($recipients as $phoneNumber) {
            $result = $this->sendSMS($phoneNumber, $message);

            if ($result['success']) {
                $results['sent']++;
            } else {
                $results['failed']++;
            }

            $results['details'][$phoneNumber] = $result;
        }

        return $results;
    }

    /**
     * Get message status
     *
     * @param string $messageSid
     * @return array
     */
    public function getMessageStatus(string $messageSid): array
    {
        try {
            $response = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->get($this->baseUrl . "/Messages/{$messageSid}.json");

            if ($response->successful()) {
                $result = $response->json();

                return [
                    'success' => true,
                    'status' => $result['status'] ?? 'unknown',
                    'error_code' => $result['error_code'] ?? null,
                    'error_message' => $result['error_message'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to fetch message status',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test Twilio connectivity
     *
     * @return array
     */
    public function ping(): array
    {
        try {
            $response = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->get($this->baseUrl . '.json');

            if ($response->successful()) {
                return [
                    'status' => 'success',
                    'message' => 'Successfully connected to Twilio',
                ];
            }

            return [
                'status' => 'failed',
                'message' => 'Failed to connect to Twilio',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
}
