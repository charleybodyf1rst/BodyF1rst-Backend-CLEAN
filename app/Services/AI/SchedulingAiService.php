<?php

namespace App\Services\AI;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Scheduling AI Service
 * AI-powered appointment scheduling and calendar management
 */
class SchedulingAiService
{
    protected $apiKey;
    protected $apiEndpoint;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->apiEndpoint = 'https://api.openai.com/v1/chat/completions';
    }

    /**
     * Process scheduling-related AI queries
     */
    public function process(string $message, array $intent, array $context): array
    {
        try {
            $action = $this->detectSchedulingAction($message);

            return match($action) {
                'schedule_appointment' => $this->scheduleAppointment($message, $context),
                'find_availability' => $this->findAvailability($message, $context),
                'reschedule' => $this->rescheduleAppointment($message, $context),
                'suggest_times' => $this->suggestBestTimes($message, $context),
                default => $this->handleGeneralSchedulingQuery($message, $context),
            };

        } catch (\Exception $e) {
            Log::error('SchedulingAiService Error', [
                'message' => $e->getMessage(),
                'context' => $context,
            ]);

            return [
                'message' => 'Failed to process scheduling request',
                'data' => null,
            ];
        }
    }

    /**
     * Schedule an appointment with AI
     */
    public function scheduleAppointment(string $prompt, array $context): array
    {
        try {
            $coachId = $context['user_id'];
            $clientId = $context['client_id'] ?? null;
            $preferredDate = $context['preferred_date'] ?? null;
            $duration = $context['duration_minutes'] ?? 60;

            $coach = User::find($coachId);
            $client = $clientId ? Client::find($clientId) : null;

            // Get coach's availability
            $availability = $this->getCoachAvailability($coach, $preferredDate);

            // Build AI prompt
            $systemPrompt = $this->buildSchedulingSystemPrompt($coach, $client, $availability);
            $userPrompt = "Scheduling request: {$prompt}\n\nSuggest the best appointment time and type.";

            // Call OpenAI GPT-4
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->apiEndpoint, [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.3,
                'max_tokens' => 800,
            ]);

            if (!$response->successful()) {
                throw new \Exception('OpenAI API request failed');
            }

            $aiResponse = $response->json();
            $suggestion = $this->parseSchedulingSuggestion($aiResponse['choices'][0]['message']['content']);

            // Create appointment if AI found a good time
            if (isset($suggestion['suggested_time'])) {
                $appointment = $this->createAppointment([
                    'coach_id' => $coachId,
                    'client_id' => $clientId,
                    'scheduled_at' => $suggestion['suggested_time'],
                    'duration_minutes' => $duration,
                    'type' => $suggestion['appointment_type'] ?? 'training',
                    'notes' => $suggestion['notes'] ?? '',
                    'ai_scheduled' => true,
                ]);

                return [
                    'message' => 'Appointment scheduled successfully',
                    'data' => [
                        'appointment' => $appointment,
                        'ai_reasoning' => $suggestion['reasoning'] ?? null,
                    ],
                ];
            } else {
                return [
                    'message' => 'No suitable time found',
                    'data' => [
                        'suggestion' => $suggestion,
                        'availability' => $availability,
                    ],
                ];
            }

        } catch (\Exception $e) {
            Log::error('Schedule Appointment Error', [
                'error' => $e->getMessage(),
                'prompt' => $prompt,
            ]);

            throw $e;
        }
    }

    /**
     * Find availability for scheduling
     */
    public function findAvailability(string $message, array $context): array
    {
        try {
            $coachId = $context['user_id'];
            $startDate = $context['start_date'] ?? now();
            $endDate = $context['end_date'] ?? now()->addDays(7);

            $coach = User::find($coachId);
            $availability = $this->getCoachAvailability($coach, $startDate, $endDate);

            $systemPrompt = "You are a scheduling assistant. Based on the coach's availability, suggest the best available times for appointments.";

            $availabilityData = json_encode($availability, JSON_PRETTY_PRINT);
            $userPrompt = "Available times:\n{$availabilityData}\n\nQuery: {$message}\n\nSuggest best times.";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->apiEndpoint, [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.3,
                'max_tokens' => 1000,
            ]);

            $aiResponse = $response->json();
            $suggestions = $this->parseAvailabilitySuggestions($aiResponse['choices'][0]['message']['content']);

            return [
                'message' => 'Availability suggestions generated',
                'data' => [
                    'suggestions' => $suggestions,
                    'raw_availability' => $availability,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Find Availability Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Reschedule existing appointment
     */
    public function rescheduleAppointment(string $message, array $context): array
    {
        try {
            $appointmentId = $context['appointment_id'] ?? null;

            if (!$appointmentId) {
                return [
                    'message' => 'Please specify which appointment to reschedule',
                    'data' => null,
                ];
            }

            $appointment = Appointment::find($appointmentId);

            if (!$appointment) {
                return [
                    'message' => 'Appointment not found',
                    'data' => null,
                ];
            }

            $coach = User::find($appointment->coach_id);
            $availability = $this->getCoachAvailability($coach);

            $systemPrompt = "You are a rescheduling assistant. Help find a new time for an appointment based on availability and the reason for rescheduling.";

            $currentTime = $appointment->scheduled_at->format('Y-m-d H:i');
            $availabilityData = json_encode($availability, JSON_PRETTY_PRINT);
            $userPrompt = "Current appointment: {$currentTime}\nReason: {$message}\nAvailability:\n{$availabilityData}\n\nSuggest new time.";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->apiEndpoint, [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.3,
                'max_tokens' => 800,
            ]);

            $aiResponse = $response->json();
            $suggestion = $this->parseRescheduleSuggestion($aiResponse['choices'][0]['message']['content']);

            return [
                'message' => 'Reschedule suggestions generated',
                'data' => [
                    'current_appointment' => $appointment,
                    'suggestion' => $suggestion,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Reschedule Appointment Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Suggest best times for scheduling
     */
    public function suggestBestTimes(string $message, array $context): array
    {
        try {
            $coachId = $context['user_id'];
            $clientId = $context['client_id'] ?? null;

            $coach = User::find($coachId);
            $client = $clientId ? Client::find($clientId) : null;

            // Get availability and client preferences
            $availability = $this->getCoachAvailability($coach);
            $clientPreferences = $client ? $this->getClientSchedulingPreferences($client) : [];

            $systemPrompt = "You are a smart scheduling assistant. Analyze patterns and preferences to suggest optimal appointment times.";

            $contextData = json_encode([
                'availability' => $availability,
                'client_preferences' => $clientPreferences,
            ], JSON_PRETTY_PRINT);

            $userPrompt = "Context:\n{$contextData}\n\nQuery: {$message}\n\nSuggest 3-5 best times with reasoning.";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->apiEndpoint, [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.4,
                'max_tokens' => 1000,
            ]);

            $aiResponse = $response->json();
            $suggestions = $this->parseTimeSuggestions($aiResponse['choices'][0]['message']['content']);

            return [
                'message' => 'Time suggestions generated',
                'data' => [
                    'suggestions' => $suggestions,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Suggest Best Times Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Handle general scheduling queries
     */
    protected function handleGeneralSchedulingQuery(string $message, array $context): array
    {
        try {
            $systemPrompt = "You are a scheduling expert. Answer questions about appointment management, calendar optimization, and time management for fitness coaches.";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->apiEndpoint, [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $message],
                ],
                'temperature' => 0.7,
                'max_tokens' => 800,
            ]);

            $aiResponse = $response->json();
            $answer = $aiResponse['choices'][0]['message']['content'];

            return [
                'message' => $answer,
                'data' => null,
            ];

        } catch (\Exception $e) {
            Log::error('General Scheduling Query Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Detect scheduling action
     */
    protected function detectSchedulingAction(string $message): string
    {
        $lowerMessage = strtolower($message);

        if (preg_match('/\b(schedule|book|create)\b.*\b(appointment|session|meeting)\b/', $lowerMessage)) {
            return 'schedule_appointment';
        }

        if (preg_match('/\b(availability|available|free|open)\b/', $lowerMessage)) {
            return 'find_availability';
        }

        if (preg_match('/\b(reschedule|move|change)\b/', $lowerMessage)) {
            return 'reschedule';
        }

        if (preg_match('/\b(suggest|recommend|best time)\b/', $lowerMessage)) {
            return 'suggest_times';
        }

        return 'general';
    }

    /**
     * Get coach availability
     */
    protected function getCoachAvailability(User $coach, $startDate = null, $endDate = null): array
    {
        $startDate = $startDate ? Carbon::parse($startDate) : now();
        $endDate = $endDate ? Carbon::parse($endDate) : now()->addDays(7);

        // Get existing appointments
        $existingAppointments = Appointment::where('coach_id', $coach->id)
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->orderBy('scheduled_at')
            ->get();

        // Build availability slots (assuming 9 AM - 6 PM working hours)
        $availableSlots = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            if ($currentDate->isWeekday()) { // Only weekdays
                for ($hour = 9; $hour < 18; $hour++) {
                    $slotTime = $currentDate->copy()->setTime($hour, 0);

                    // Check if slot is free
                    $isBooked = $existingAppointments->contains(function ($apt) use ($slotTime) {
                        return $slotTime->between(
                            $apt->scheduled_at,
                            $apt->scheduled_at->copy()->addMinutes($apt->duration_minutes)
                        );
                    });

                    if (!$isBooked && $slotTime > now()) {
                        $availableSlots[] = [
                            'datetime' => $slotTime->toISOString(),
                            'formatted' => $slotTime->format('l, F j, Y \a\t g:i A'),
                        ];
                    }
                }
            }

            $currentDate->addDay();
        }

        return [
            'slots' => array_slice($availableSlots, 0, 20), // Return first 20 slots
            'total_available' => count($availableSlots),
        ];
    }

    /**
     * Get client scheduling preferences
     */
    protected function getClientSchedulingPreferences(Client $client): array
    {
        // Get historical appointment data
        $pastAppointments = Appointment::where('client_id', $client->id)
            ->where('scheduled_at', '<', now())
            ->orderBy('scheduled_at', 'desc')
            ->limit(10)
            ->get();

        if ($pastAppointments->isEmpty()) {
            return ['message' => 'No historical preference data'];
        }

        // Analyze patterns
        $preferredDays = $pastAppointments->groupBy(fn($apt) => $apt->scheduled_at->dayOfWeek);
        $preferredTimes = $pastAppointments->groupBy(fn($apt) => $apt->scheduled_at->hour);

        return [
            'preferred_days' => $preferredDays->map->count()->toArray(),
            'preferred_times' => $preferredTimes->map->count()->toArray(),
            'typical_duration' => $pastAppointments->avg('duration_minutes'),
        ];
    }

    /**
     * Build scheduling system prompt
     */
    protected function buildSchedulingSystemPrompt($coach, $client, $availability): string
    {
        $prompt = "You are an intelligent scheduling assistant for fitness coaching.\n\n";
        $prompt .= "Coach: {$coach->name}\n";

        if ($client) {
            $prompt .= "Client: {$client->name}\n";
        }

        $prompt .= "\nAvailable Time Slots:\n";
        $prompt .= json_encode($availability, JSON_PRETTY_PRINT);

        $prompt .= "\n\nYour task: Suggest the best appointment time considering:\n";
        $prompt .= "- Coach availability\n";
        $prompt .= "- Client preferences (if known)\n";
        $prompt .= "- Optimal spacing between sessions\n";
        $prompt .= "- Time of day effectiveness\n\n";

        $prompt .= "Return suggestion in JSON format:\n";
        $prompt .= "{\n";
        $prompt .= "  \"suggested_time\": \"ISO 8601 datetime\",\n";
        $prompt .= "  \"appointment_type\": \"training|consultation|assessment\",\n";
        $prompt .= "  \"reasoning\": \"Why this time is optimal\",\n";
        $prompt .= "  \"notes\": \"Any additional notes\"\n";
        $prompt .= "}";

        return $prompt;
    }

    /**
     * Parse scheduling suggestion
     */
    protected function parseSchedulingSuggestion(string $response): array
    {
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            try {
                return json_decode($matches[0], true);
            } catch (\Exception $e) {
                Log::warning('Failed to parse scheduling suggestion JSON');
            }
        }

        return ['raw_response' => $response];
    }

    /**
     * Parse availability suggestions
     */
    protected function parseAvailabilitySuggestions(string $response): array
    {
        return ['suggestions' => $response];
    }

    /**
     * Parse reschedule suggestion
     */
    protected function parseRescheduleSuggestion(string $response): array
    {
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            try {
                return json_decode($matches[0], true);
            } catch (\Exception $e) {
                Log::warning('Failed to parse reschedule JSON');
            }
        }

        return ['suggestion' => $response];
    }

    /**
     * Parse time suggestions
     */
    protected function parseTimeSuggestions(string $response): array
    {
        return ['times' => $response];
    }

    /**
     * Create appointment in database
     */
    protected function createAppointment(array $data): Appointment
    {
        return Appointment::create($data);
    }
}
