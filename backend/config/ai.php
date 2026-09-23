<?php

use App\Services\AI\FakeRefundConversationAI;
use App\Services\AI\GeminiRefundConversationAI;
use App\Services\AI\RefundConversationPrompt;

return [
    'default' => env('AI_PROVIDER', 'gemini'),

    'prompt_version' => RefundConversationPrompt::VERSION,

    'max_response_bytes' => (int) env('AI_MAX_RESPONSE_BYTES', 32768),

    'providers' => [
        'fake' => [
            'driver' => FakeRefundConversationAI::class,
            'model' => 'deterministic-v1',
        ],
        'gemini' => [
            'driver' => GeminiRefundConversationAI::class,
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-3.8-flash'),
            'connection_timeout_seconds' => 3,
            'timeout_seconds' => 15,
        ],
    ],
];
