<?php

namespace Database\Factories;

use App\Enums\MessageSender;
use App\Models\ConversationMessage;
use App\Models\RefundConversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversationMessage>
 */
class ConversationMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'refund_conversation_id' => RefundConversation::factory(),
            'client_message_id' => fake()->uuid(),
            'sender' => MessageSender::Customer,
            'content' => fake()->sentence(),
            'metadata' => null,
        ];
    }

    /**
     * Mark the message as assistant-generated.
     */
    public function assistant(): static
    {
        return $this->state(fn (array $attributes): array => [
            'client_message_id' => null,
            'sender' => MessageSender::Assistant,
        ]);
    }

    /**
     * Mark the message as a system message.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes): array => [
            'client_message_id' => null,
            'sender' => MessageSender::System,
        ]);
    }
}
