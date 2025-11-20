<?php

namespace App\Services\AI;

use App\Models\Message;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Messaging AI Service
 * AI-powered message drafting, reply suggestions, and communication assistance
 */
class MessagingAiService
{
    protected $apiKey;
    protected $apiEndpoint;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->apiEndpoint = 'https://api.openai.com/v1/chat/completions';
    }

    /**
     * Process messaging-related AI queries
     */
    public function process(string $message, array $intent, array $context): array
    {
        try {
            $action = $this->detectMessagingAction($message);

            return match($action) {
                'draft_message' => $this->draftMessage($message, $context),
                'suggest_reply' => $this->suggestReply($message, $context),
                'improve_message' => $this->improveMessage($message, $context),
                'analyze_tone' => $this->analyzeTone($message, $context),
                default => $this->handleGeneralMessagingQuery($message, $context),
            };

        } catch (\Exception $e) {
            Log::error('MessagingAiService Error', [
                'message' => $e->getMessage(),
                'context' => $context,
            ]);

            return [
                'message' => 'Failed to process messaging request',
                'data' => null,
            ];
        }
    }

    /**
     * Draft a message with AI
     */
    public function draftMessage(string $prompt, array $context): array
    {
        try {
            $coachId = $context['user_id'];
            $clientId = $context['client_id'];
            $messageType = $context['message_type'] ?? 'general';

            $coach = User::find($coachId);
            $client = Client::find($clientId);

            if (!$client) {
                return [
                    'message' => 'Client not found',
                    'data' => null,
                ];
            }

            // Get conversation context
            $conversationHistory = $this->getConversationHistory($coachId, $clientId);
            $clientContext = $this->getClientContext($client);

            // Build AI prompt
            $systemPrompt = $this->buildMessageDraftSystemPrompt($coach, $client, $messageType, $clientContext);
            $userPrompt = "Draft a {$messageType} message: {$prompt}";

            if (!empty($conversationHistory)) {
                $userPrompt .= "\n\nRecent conversation:\n" . $conversationHistory;
            }

            // Call OpenAI GPT-4
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(45)->post($this->apiEndpoint, [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.7,
                'max_tokens' => 1000,
            ]);

            if (!$response->successful()) {
                throw new \Exception('OpenAI API request failed');
            }

            $aiResponse = $response->json();
            $draft = $this->parseMessageDraft($aiResponse['choices'][0]['message']['content']);

            return [
                'message' => 'Message draft created',
                'data' => [
                    'draft' => $draft,
                    'client' => [
                        'id' => $client->id,
                        'name' => $client->name,
                    ],
                    'message_type' => $messageType,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Draft Message Error', [
                'error' => $e->getMessage(),
                'prompt' => $prompt,
            ]);

            throw $e;
        }
    }

    /**
     * Suggest reply to a message
     */
    public function suggestReply(string $message, array $context): array
    {
        try {
            $coachId = $context['user_id'];
            $clientId = $context['client_id'];
            $incomingMessage = $context['incoming_message'] ?? $message;

            $coach = User::find($coachId);
            $client = Client::find($clientId);

            $conversationHistory = $this->getConversationHistory($coachId, $clientId);
            $clientContext = $this->getClientContext($client);

            $systemPrompt = "You are a fitness coach communication assistant. Suggest professional, empathetic, and helpful replies to client messages. Consider the client's context and conversation history.";

            $userPrompt = "Client message: \"{$incomingMessage}\"\n\n";
            $userPrompt .= "Client context:\n{$clientContext}\n\n";

            if ($conversationHistory) {
                $userPrompt .= "Recent conversation:\n{$conversationHistory}\n\n";
            }

            $userPrompt .= "Suggest 2-3 appropriate reply options with different tones (supportive, motivational, informative).";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(40)->post($this->apiEndpoint, [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.8,
                'max_tokens' => 1200,
            ]);

            $aiResponse = $response->json();
            $suggestions = $this->parseReplySuggestions($aiResponse['choices'][0]['message']['content']);

            return [
                'message' => 'Reply suggestions generated',
                'data' => [
                    'suggestions' => $suggestions,
                    'incoming_message' => $incomingMessage,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Suggest Reply Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Improve existing message
     */
    public function improveMessage(string $message, array $context): array
    {
        try {
            $originalMessage = $context['original_message'] ?? $message;
            $improvementGoal = $context['goal'] ?? 'clarity and professionalism';

            $systemPrompt = "You are a professional communication editor. Improve messages for fitness coaches while maintaining authenticity and warmth.";

            $userPrompt = "Original message:\n\"{$originalMessage}\"\n\n";
            $userPrompt .= "Improvement goal: {$improvementGoal}\n\n";
            $userPrompt .= "Provide: 1) Improved version, 2) What was changed, 3) Why these changes help";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->apiEndpoint, [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.6,
                'max_tokens' => 1000,
            ]);

            $aiResponse = $response->json();
            $improvement = $this->parseImprovementSuggestion($aiResponse['choices'][0]['message']['content']);

            return [
                'message' => 'Message improvement suggestions generated',
                'data' => [
                    'original' => $originalMessage,
                    'improvement' => $improvement,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Improve Message Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Analyze message tone
     */
    public function analyzeTone(string $message, array $context): array
    {
        try {
            $messageToAnalyze = $context['message_to_analyze'] ?? $message;

            $systemPrompt = "You are a communication analyst. Analyze message tone, sentiment, and potential impact on client relationships.";

            $userPrompt = "Analyze this message:\n\"{$messageToAnalyze}\"\n\n";
            $userPrompt .= "Provide:\n";
            $userPrompt .= "1) Tone assessment (professional, casual, motivational, etc.)\n";
            $userPrompt .= "2) Sentiment (positive, neutral, negative)\n";
            $userPrompt .= "3) Potential client reaction\n";
            $userPrompt .= "4) Suggestions for improvement (if needed)";

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
                'max_tokens' => 800,
            ]);

            $aiResponse = $response->json();
            $analysis = $this->parseToneAnalysis($aiResponse['choices'][0]['message']['content']);

            return [
                'message' => 'Tone analysis completed',
                'data' => [
                    'analysis' => $analysis,
                    'original_message' => $messageToAnalyze,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Analyze Tone Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Handle general messaging queries
     */
    protected function handleGeneralMessagingQuery(string $message, array $context): array
    {
        try {
            $systemPrompt = "You are a communication expert for fitness coaches. Answer questions about effective client communication, messaging strategies, and relationship building.";

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
            Log::error('General Messaging Query Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Detect messaging action
     */
    protected function detectMessagingAction(string $message): string
    {
        $lowerMessage = strtolower($message);

        if (preg_match('/\b(draft|write|compose|create)\b.*\b(message|text|email)\b/', $lowerMessage)) {
            return 'draft_message';
        }

        if (preg_match('/\b(reply|respond|answer)\b/', $lowerMessage)) {
            return 'suggest_reply';
        }

        if (preg_match('/\b(improve|enhance|better|rewrite)\b/', $lowerMessage)) {
            return 'improve_message';
        }

        if (preg_match('/\b(tone|sentiment|analyze|sound)\b/', $lowerMessage)) {
            return 'analyze_tone';
        }

        return 'general';
    }

    /**
     * Get conversation history
     */
    protected function getConversationHistory($coachId, $clientId, $limit = 5): string
    {
        $messages = Message::where(function($query) use ($coachId, $clientId) {
                $query->where('sender_id', $coachId)->where('recipient_id', $clientId);
            })
            ->orWhere(function($query) use ($coachId, $clientId) {
                $query->where('sender_id', $clientId)->where('recipient_id', $coachId);
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse();

        if ($messages->isEmpty()) {
            return '';
        }

        $formatted = '';
        foreach ($messages as $msg) {
            $sender = $msg->sender_id == $coachId ? 'Coach' : 'Client';
            $formatted .= "{$sender}: {$msg->content}\n";
        }

        return $formatted;
    }

    /**
     * Get client context
     */
    protected function getClientContext(Client $client): string
    {
        $context = "Client: {$client->name}\n";

        if ($client->goals) {
            $context .= "Goals: {$client->goals}\n";
        }

        if ($client->fitness_level) {
            $context .= "Fitness Level: {$client->fitness_level}\n";
        }

        // Get recent activity
        $recentWorkouts = $client->workoutLogs()
            ->where('completed_at', '>=', now()->subDays(7))
            ->count();

        $context .= "Recent Activity: {$recentWorkouts} workouts in last 7 days\n";

        return $context;
    }

    /**
     * Build message draft system prompt
     */
    protected function buildMessageDraftSystemPrompt($coach, $client, $messageType, $clientContext): string
    {
        $prompt = "You are a professional fitness coach communication assistant.\n\n";
        $prompt .= "Coach: {$coach->name}\n";
        $prompt .= "Client Information:\n{$clientContext}\n\n";

        $prompt .= "Message Type: {$messageType}\n\n";

        $prompt .= match($messageType) {
            'check_in' => "Draft a check-in message to see how the client is doing with their program. Be supportive and specific.",
            'motivation' => "Draft a motivational message to encourage the client. Reference their goals and recent progress.",
            'feedback' => "Draft a feedback message about the client's performance. Be constructive and encouraging.",
            'reminder' => "Draft a friendly reminder message. Be professional but not pushy.",
            default => "Draft a professional, friendly message appropriate for a fitness coach-client relationship.",
        };

        $prompt .= "\n\nGuidelines:\n";
        $prompt .= "- Keep tone warm, professional, and encouraging\n";
        $prompt .= "- Be specific and personal when possible\n";
        $prompt .= "- Keep length appropriate (2-4 sentences for most messages)\n";
        $prompt .= "- End with a clear call-to-action or question when appropriate\n";
        $prompt .= "- Use client's name naturally\n\n";

        $prompt .= "Return the draft message as plain text, ready to send.";

        return $prompt;
    }

    /**
     * Parse message draft
     */
    protected function parseMessageDraft(string $response): string
    {
        // Clean up any JSON wrapping if present
        if (preg_match('/"(?:draft|message|text)":\s*"(.+?)"/s', $response, $matches)) {
            return trim($matches[1]);
        }

        // Return cleaned response
        return trim($response);
    }

    /**
     * Parse reply suggestions
     */
    protected function parseReplySuggestions(string $response): array
    {
        // Try to extract structured suggestions
        $suggestions = [];

        // Look for numbered options
        if (preg_match_all('/(\d+)\.\s*\*\*(.+?)\*\*:\s*"?(.+?)"?(?=\n\n|\n\d+\.|\z)/s', $response, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $suggestions[] = [
                    'tone' => trim($match[2]),
                    'message' => trim($match[3]),
                ];
            }
        }

        // Fallback: return raw response
        if (empty($suggestions)) {
            $suggestions[] = [
                'tone' => 'general',
                'message' => $response,
            ];
        }

        return $suggestions;
    }

    /**
     * Parse improvement suggestion
     */
    protected function parseImprovementSuggestion(string $response): array
    {
        $improved = [
            'improved_message' => '',
            'changes' => '',
            'reasoning' => '',
        ];

        if (preg_match('/Improved version:(.+?)(?=What was changed:|$)/s', $response, $matches)) {
            $improved['improved_message'] = trim($matches[1]);
        }

        if (preg_match('/What was changed:(.+?)(?=Why these changes:|$)/s', $response, $matches)) {
            $improved['changes'] = trim($matches[1]);
        }

        if (preg_match('/Why these changes:(.+?)$/s', $response, $matches)) {
            $improved['reasoning'] = trim($matches[1]);
        }

        // Fallback
        if (empty($improved['improved_message'])) {
            $improved['full_response'] = $response;
        }

        return $improved;
    }

    /**
     * Parse tone analysis
     */
    protected function parseToneAnalysis(string $response): array
    {
        $analysis = [
            'tone' => '',
            'sentiment' => '',
            'client_reaction' => '',
            'suggestions' => '',
        ];

        if (preg_match('/Tone assessment:(.+?)(?=Sentiment:|$)/s', $response, $matches)) {
            $analysis['tone'] = trim($matches[1]);
        }

        if (preg_match('/Sentiment:(.+?)(?=Potential client reaction:|$)/s', $response, $matches)) {
            $analysis['sentiment'] = trim($matches[1]);
        }

        if (preg_match('/Potential client reaction:(.+?)(?=Suggestions:|$)/s', $response, $matches)) {
            $analysis['client_reaction'] = trim($matches[1]);
        }

        if (preg_match('/Suggestions:(.+?)$/s', $response, $matches)) {
            $analysis['suggestions'] = trim($matches[1]);
        }

        // Fallback
        if (empty($analysis['tone'])) {
            $analysis['full_analysis'] = $response;
        }

        return $analysis;
    }
}
