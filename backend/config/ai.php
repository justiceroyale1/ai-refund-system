<?php

use App\Services\AI\FakeRefundConversationAI;
use App\Services\AI\RefundConversationPrompt;

return [
    'default' => env('AI_PROVIDER', 'fake'),

    'prompt_version' => RefundConversationPrompt::VERSION,

    'max_response_bytes' => (int) env('AI_MAX_RESPONSE_BYTES', 32768),

    'providers' => [
        'fake' => [
            'driver' => FakeRefundConversationAI::class,
            'model' => 'deterministic-v1',
        ],
    ],
];
