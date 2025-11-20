<?php
return [
    'provider' => env('AI_PROVIDER', 'openai'), // 'openai' | 'anthropic'
    'model'    => env('AI_MODEL', 'gpt-4'),
    'system'   => "You are BodyF1rst's coach. Be safe, helpful, concise. Refuse medical/diagnostic requests. If user expresses self-harm/crisis, return {route:'crisis'} immediately.",
    'anthropic_api_key' => env('ANTHROPIC_API_KEY'),
];
