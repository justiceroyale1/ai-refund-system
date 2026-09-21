<?php

namespace Database\Factories;

use App\Models\AiAnalysis;
use App\Models\ConversationMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiAnalysis>
 */
class AiAnalysisFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_message_id' => ConversationMessage::factory(),
            'refund_conversation_id' => function (array $attributes): int {
                return ConversationMessage::query()
                    ->whereKey($attributes['conversation_message_id'])
                    ->firstOrFail()
                    ->refund_conversation_id;
            },
            'provider' => 'fake',
            'model' => 'deterministic-test-model',
            'prompt_version' => 'v1',
            'confidence' => 96,
            'prompt_injection_detected' => false,
            'conflicting_information' => false,
            'extracted_data' => [
                'intent' => 'refund',
                'reason' => 'damaged_item',
            ],
            'raw_response' => null,
        ];
    }
}
