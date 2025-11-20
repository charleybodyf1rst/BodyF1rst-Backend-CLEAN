<?php

namespace App\Services;

use InvalidArgumentException;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use Anthropic\Client as Anthropic;

class AiGateway
{
    /**
     * @param array<int, array<string, mixed>> $messages
     * @param array<int, array<string, mixed>> $tools
     */
    public function respond(array $messages, array $tools = [], bool $stream = false)
    {
        [$provider, $model, $system, $maxTokens] = $this->validateConfig(
            config('ai.provider'),
            config('ai.model'),
            config('ai.system'),
            config('ai.max_tokens')
        );

        $normalizedMessages = $this->normalizeMessages($messages);

        if ($provider === 'openai') {
            $payload = [
                'model' => $model,
                'messages' => array_merge([
                    ['role' => 'system', 'content' => $system],
                ], $normalizedMessages),
                'tools' => $this->normalizeTools($tools, 'openai'),
                'tool_choice' => 'auto',
                'max_tokens' => $maxTokens,
            ];

            try {
                return $stream
                    ? OpenAI::chat()->createStreamed($payload)
                    : OpenAI::chat()->create($payload);
            } catch (\Throwable $exception) {
                Log::error('OpenAI chat request failed', [
                    'error' => $exception->getMessage(),
                    'model' => $model
                ]);

                throw new \RuntimeException('OpenAI chat request failed', 0, $exception);
            }
        }

        $anthropicKey = config('ai.anthropic_api_key');
        if (!is_string($anthropicKey) || trim($anthropicKey) === '') {
            throw new InvalidArgumentException('Anthropic API key is not configured.');
        }

        try {
            $client = new Anthropic(apiKey: $anthropicKey);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Unable to initialize Anthropic client', 0, $exception);
        }

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'system' => $system,
            'messages' => $normalizedMessages,
            'tools' => $this->normalizeTools($tools, 'anthropic'),
        ];

        try {
            return $stream
                ? $client->messages()->createStream($payload)
                : $client->messages()->create($payload);
        } catch (\Throwable $exception) {
            Log::error('Anthropic chat request failed', [
                'error' => $exception->getMessage(),
                'model' => $model
            ]);

            throw new \RuntimeException('Anthropic chat request failed', 0, $exception);
        }
    }

    /**
     * @param mixed $provider
     * @param mixed $model
     * @param mixed $system
     */
    private function validateConfig($provider, $model, $system, $maxTokens): array
    {
        $allowedProviders = ['openai', 'anthropic'];

        if (!is_string($provider) || !in_array($provider, $allowedProviders, true)) {
            throw new InvalidArgumentException('Invalid AI provider configured.');
        }

        if (!is_string($model) || trim($model) === '') {
            throw new InvalidArgumentException('AI model configuration must be a non-empty string.');
        }

        if (!is_string($system) || trim($system) === '') {
            throw new InvalidArgumentException('AI system prompt must be a non-empty string.');
        }

        if ($maxTokens === null || $maxTokens === '') {
            $maxTokens = 1024;
        }

        if (!is_numeric($maxTokens) || (int) $maxTokens <= 0) {
            throw new InvalidArgumentException('ai.max_tokens must be a positive integer.');
        }

        return [$provider, $model, $system, (int) $maxTokens];
    }

    /**
     * @param array<int, mixed> $messages
     * @return array<int, array{role: string, content: string}>
     */
    private function normalizeMessages(array $messages): array
    {
        $normalized = [];

        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }

            $content = $message['content'] ?? null;
            if (!is_string($content) || trim($content) === '') {
                continue;
            }

            $role = $message['role'] ?? 'user';
            if (!is_string($role) || trim($role) === '') {
                $role = 'user';
            }

            $normalized[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        if (empty($normalized)) {
            throw new InvalidArgumentException('At least one valid message with content is required.');
        }

        return $normalized;
    }

    /**
     * @param array<int, mixed> $tools
     * @return array<int, array<string, mixed>>
     */
    private function normalizeTools(array $tools, string $provider): array
    {
        $normalized = [];

        foreach ($tools as $tool) {
            if (!is_array($tool)) {
                continue;
            }

            $function = $tool['function'] ?? null;
            if (!is_array($function)) {
                continue;
            }

            $name = $function['name'] ?? null;
            $parameters = $function['parameters'] ?? null;
            $description = $function['description'] ?? '';

            if (!is_string($name) || trim($name) === '' || !is_array($parameters)) {
                continue;
            }

            if ($provider === 'openai') {
                $normalized[] = [
                    'type' => 'function',
                    'function' => [
                        'name' => $name,
                        'description' => is_string($description) ? $description : '',
                        'parameters' => $parameters,
                    ],
                ];
            } else {
                $normalized[] = [
                    'name' => $name,
                    'input_schema' => $parameters,
                ];
            }
        }

        return $normalized;
    }
}
