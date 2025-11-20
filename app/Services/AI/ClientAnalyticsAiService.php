<?php

namespace App\Services\AI;

use App\Models\Client;
use App\Models\WorkoutLog;
use App\Models\ProgressPhoto;
use App\Models\BodyMeasurement;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Client Analytics AI Service
 * Analyzes client progress, identifies trends, and provides insights
 */
class ClientAnalyticsAiService
{
    protected $apiKey;
    protected $apiEndpoint;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->apiEndpoint = 'https://api.openai.com/v1/chat/completions';
    }

    /**
     * Process analytics-related AI queries
     */
    public function process(string $message, array $intent, array $context): array
    {
        try {
            $action = $this->detectAnalyticsAction($message);

            return match($action) {
                'analyze_client' => $this->analyzeClient($context['client_id'] ?? null, $context['user_id']),
                'identify_trends' => $this->identifyTrends($message, $context),
                'compare_progress' => $this->compareProgress($message, $context),
                'predict_outcomes' => $this->predictOutcomes($message, $context),
                default => $this->handleGeneralAnalyticsQuery($message, $context),
            };

        } catch (\Exception $e) {
            Log::error('ClientAnalyticsAiService Error', [
                'message' => $e->getMessage(),
                'context' => $context,
            ]);

            return [
                'message' => 'Failed to process analytics request',
                'data' => null,
            ];
        }
    }

    /**
     * Analyze complete client progress
     */
    public function analyzeClient($clientId, $coachId): array
    {
        try {
            if (!$clientId) {
                return [
                    'message' => 'Please specify which client to analyze',
                    'data' => null,
                ];
            }

            $client = Client::where('id', $clientId)
                ->where('coach_id', $coachId)
                ->first();

            if (!$client) {
                return [
                    'message' => 'Client not found or access denied',
                    'data' => null,
                ];
            }

            // Gather all client data
            $analyticsData = $this->gatherClientData($client);

            // Build AI prompt
            $systemPrompt = $this->buildAnalyticsSystemPrompt();
            $userPrompt = $this->buildAnalyticsUserPrompt($analyticsData);

            // Call OpenAI GPT-4
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($this->apiEndpoint, [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.5,
                'max_tokens' => 2000,
            ]);

            if (!$response->successful()) {
                throw new \Exception('OpenAI API request failed');
            }

            $aiResponse = $response->json();
            $insights = $this->parseInsightsResponse($aiResponse['choices'][0]['message']['content']);

            return [
                'message' => 'Client analysis completed',
                'data' => [
                    'client' => [
                        'id' => $client->id,
                        'name' => $client->name,
                        'goals' => $client->goals,
                    ],
                    'analytics' => $analyticsData,
                    'ai_insights' => $insights,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Analyze Client Error', [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Identify trends in client data
     */
    public function identifyTrends(string $message, array $context): array
    {
        try {
            $clientId = $context['client_id'] ?? null;

            if (!$clientId) {
                return [
                    'message' => 'Please specify which client to analyze',
                    'data' => null,
                ];
            }

            $client = Client::find($clientId);

            if (!$client) {
                return [
                    'message' => 'Client not found',
                    'data' => null,
                ];
            }

            // Get time-series data
            $trendsData = $this->gatherTrendsData($client, $context);

            $systemPrompt = "You are a data analyst specializing in fitness trends. Identify patterns, correlations, and trends in client data. Provide actionable insights.";

            $userPrompt = "Analyze these trends:\n\n{$trendsData}\n\nIdentify: 1) Key trends, 2) Patterns, 3) Correlations, 4) Recommendations for optimization";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(45)->post($this->apiEndpoint, [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.5,
                'max_tokens' => 1500,
            ]);

            $aiResponse = $response->json();
            $trends = $this->parseTrendsResponse($aiResponse['choices'][0]['message']['content']);

            return [
                'message' => 'Trend analysis completed',
                'data' => [
                    'trends' => $trends,
                    'client' => $client,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Identify Trends Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Compare progress between time periods
     */
    public function compareProgress(string $message, array $context): array
    {
        try {
            $clientId = $context['client_id'] ?? null;
            $startDate = $context['start_date'] ?? now()->subDays(30);
            $endDate = $context['end_date'] ?? now();

            if (!$clientId) {
                return [
                    'message' => 'Please specify which client to compare',
                    'data' => null,
                ];
            }

            $client = Client::find($clientId);

            if (!$client) {
                return [
                    'message' => 'Client not found',
                    'data' => null,
                ];
            }

            $comparisonData = $this->gatherComparisonData($client, $startDate, $endDate);

            $systemPrompt = "You are a fitness progress analyst. Compare client data between time periods and evaluate effectiveness of their program.";

            $userPrompt = "Compare this client's progress:\n\n{$comparisonData}\n\nProvide: 1) Progress summary, 2) Improvements, 3) Areas needing attention, 4) Program adjustments";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(45)->post($this->apiEndpoint, [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.5,
                'max_tokens' => 1500,
            ]);

            $aiResponse = $response->json();
            $comparison = $this->parseComparisonResponse($aiResponse['choices'][0]['message']['content']);

            return [
                'message' => 'Progress comparison completed',
                'data' => [
                    'comparison' => $comparison,
                    'client' => $client,
                    'period' => [
                        'start' => $startDate,
                        'end' => $endDate,
                    ],
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Compare Progress Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Predict future outcomes
     */
    public function predictOutcomes(string $message, array $context): array
    {
        try {
            $clientId = $context['client_id'] ?? null;

            if (!$clientId) {
                return [
                    'message' => 'Please specify which client to analyze',
                    'data' => null,
                ];
            }

            $client = Client::find($clientId);

            if (!$client) {
                return [
                    'message' => 'Client not found',
                    'data' => null,
                ];
            }

            $historicalData = $this->gatherHistoricalData($client);

            $systemPrompt = "You are a predictive analytics expert for fitness. Based on historical data and current trajectory, predict likely outcomes and provide recommendations to optimize results.";

            $userPrompt = "Historical data:\n\n{$historicalData}\n\nPredict: 1) Likely outcomes in 30/60/90 days, 2) Potential obstacles, 3) Optimization strategies, 4) Goal achievement probability";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(45)->post($this->apiEndpoint, [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.6,
                'max_tokens' => 1500,
            ]);

            $aiResponse = $response->json();
            $predictions = $this->parsePredictionResponse($aiResponse['choices'][0]['message']['content']);

            return [
                'message' => 'Outcome predictions generated',
                'data' => [
                    'predictions' => $predictions,
                    'client' => $client,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Predict Outcomes Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Handle general analytics queries
     */
    protected function handleGeneralAnalyticsQuery(string $message, array $context): array
    {
        try {
            $systemPrompt = "You are a fitness analytics expert. Answer questions about client progress tracking, metrics, and analysis methods.";

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
            Log::error('General Analytics Query Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Detect analytics action
     */
    protected function detectAnalyticsAction(string $message): string
    {
        $lowerMessage = strtolower($message);

        if (preg_match('/\b(analyze|analysis|assess|evaluate)\b.*\b(client|progress)\b/', $lowerMessage)) {
            return 'analyze_client';
        }

        if (preg_match('/\b(trend|pattern|over time)\b/', $lowerMessage)) {
            return 'identify_trends';
        }

        if (preg_match('/\b(compare|comparison|vs|versus)\b/', $lowerMessage)) {
            return 'compare_progress';
        }

        if (preg_match('/\b(predict|forecast|project|expect)\b/', $lowerMessage)) {
            return 'predict_outcomes';
        }

        return 'general';
    }

    /**
     * Gather complete client data
     */
    protected function gatherClientData(Client $client): array
    {
        return [
            'profile' => [
                'name' => $client->name,
                'age' => $client->age,
                'goals' => $client->goals,
                'start_date' => $client->created_at,
            ],
            'body_metrics' => $this->getBodyMetrics($client),
            'workout_stats' => $this->getWorkoutStats($client),
            'adherence' => $this->getAdherenceStats($client),
            'progress_photos' => $this->getProgressPhotos($client),
        ];
    }

    /**
     * Get body metrics
     */
    protected function getBodyMetrics(Client $client): array
    {
        $measurements = BodyMeasurement::where('client_id', $client->id)
            ->orderBy('measured_at', 'desc')
            ->limit(10)
            ->get();

        if ($measurements->isEmpty()) {
            return ['message' => 'No body measurements recorded'];
        }

        $latest = $measurements->first();
        $oldest = $measurements->last();

        return [
            'latest' => [
                'weight' => $latest->weight ?? null,
                'body_fat' => $latest->body_fat_percentage ?? null,
                'measurements' => [
                    'chest' => $latest->chest ?? null,
                    'waist' => $latest->waist ?? null,
                    'hips' => $latest->hips ?? null,
                ],
                'date' => $latest->measured_at,
            ],
            'change_from_start' => [
                'weight' => ($latest->weight ?? 0) - ($oldest->weight ?? 0),
                'body_fat' => ($latest->body_fat_percentage ?? 0) - ($oldest->body_fat_percentage ?? 0),
            ],
            'history' => $measurements->take(5)->map(fn($m) => [
                'weight' => $m->weight,
                'date' => $m->measured_at,
            ]),
        ];
    }

    /**
     * Get workout statistics
     */
    protected function getWorkoutStats(Client $client): array
    {
        $workouts = WorkoutLog::where('client_id', $client->id)
            ->where('completed_at', '>=', now()->subDays(30))
            ->get();

        return [
            'total_workouts' => $workouts->count(),
            'average_per_week' => round($workouts->count() / 4, 1),
            'total_volume' => $workouts->sum('total_volume'),
            'avg_duration' => round($workouts->avg('duration_minutes'), 0),
        ];
    }

    /**
     * Get adherence statistics
     */
    protected function getAdherenceStats(Client $client): array
    {
        $scheduledWorkouts = 12; // Assume 3x per week for 4 weeks
        $completedWorkouts = WorkoutLog::where('client_id', $client->id)
            ->where('completed_at', '>=', now()->subDays(30))
            ->count();

        return [
            'adherence_rate' => round(($completedWorkouts / $scheduledWorkouts) * 100, 1),
            'scheduled' => $scheduledWorkouts,
            'completed' => $completedWorkouts,
            'missed' => $scheduledWorkouts - $completedWorkouts,
        ];
    }

    /**
     * Get progress photos
     */
    protected function getProgressPhotos(Client $client): array
    {
        $photos = ProgressPhoto::where('client_id', $client->id)
            ->orderBy('taken_at', 'desc')
            ->limit(3)
            ->get();

        return [
            'total_count' => $photos->count(),
            'latest_date' => $photos->first()->taken_at ?? null,
        ];
    }

    /**
     * Gather trends data
     */
    protected function gatherTrendsData(Client $client, array $context): string
    {
        $measurements = BodyMeasurement::where('client_id', $client->id)
            ->orderBy('measured_at', 'asc')
            ->get();

        $formatted = "Client: {$client->name}\n\n";
        $formatted .= "Weight Trend:\n";

        foreach ($measurements as $m) {
            $formatted .= "  {$m->measured_at->format('Y-m-d')}: {$m->weight} kg\n";
        }

        return $formatted;
    }

    /**
     * Gather comparison data
     */
    protected function gatherComparisonData(Client $client, $startDate, $endDate): string
    {
        $startMeasurement = BodyMeasurement::where('client_id', $client->id)
            ->where('measured_at', '>=', $startDate)
            ->orderBy('measured_at', 'asc')
            ->first();

        $endMeasurement = BodyMeasurement::where('client_id', $client->id)
            ->where('measured_at', '<=', $endDate)
            ->orderBy('measured_at', 'desc')
            ->first();

        $formatted = "Period: {$startDate} to {$endDate}\n\n";
        $formatted .= "Start:\n";
        $formatted .= "  Weight: " . ($startMeasurement->weight ?? 'N/A') . " kg\n";
        $formatted .= "End:\n";
        $formatted .= "  Weight: " . ($endMeasurement->weight ?? 'N/A') . " kg\n";

        return $formatted;
    }

    /**
     * Gather historical data
     */
    protected function gatherHistoricalData(Client $client): string
    {
        $data = $this->gatherClientData($client);
        return json_encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * Build analytics system prompt
     */
    protected function buildAnalyticsSystemPrompt(): string
    {
        return "You are an expert fitness analytics AI. Analyze client progress data to identify strengths, weaknesses, and opportunities for improvement. Provide actionable insights based on:\n" .
               "- Body composition changes\n" .
               "- Workout adherence and consistency\n" .
               "- Training volume and intensity trends\n" .
               "- Goal alignment\n\n" .
               "Return insights in JSON format with: overall_assessment, key_strengths, areas_for_improvement, specific_recommendations, goal_progress_evaluation.";
    }

    /**
     * Build analytics user prompt
     */
    protected function buildAnalyticsUserPrompt(array $data): string
    {
        return "Analyze this client data:\n\n" . json_encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * Parse insights response
     */
    protected function parseInsightsResponse(string $response): array
    {
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            try {
                return json_decode($matches[0], true);
            } catch (\Exception $e) {
                Log::warning('Failed to parse insights JSON');
            }
        }

        return ['full_analysis' => $response];
    }

    /**
     * Parse trends response
     */
    protected function parseTrendsResponse(string $response): array
    {
        return ['analysis' => $response];
    }

    /**
     * Parse comparison response
     */
    protected function parseComparisonResponse(string $response): array
    {
        return ['comparison' => $response];
    }

    /**
     * Parse prediction response
     */
    protected function parsePredictionResponse(string $response): array
    {
        return ['predictions' => $response];
    }
}
