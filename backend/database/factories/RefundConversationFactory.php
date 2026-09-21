<?php

namespace Database\Factories;

use App\Enums\ConversationState;
use App\Enums\ConversationStatus;
use App\Enums\RefundReason;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\RefundConversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RefundConversation>
 */
class RefundConversationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'order_id' => null,
            'order_item_id' => null,
            'state' => ConversationState::Started,
            'reason' => null,
            'reason_details' => null,
            'status' => ConversationStatus::Active,
            'resolved_at' => null,
        ];
    }

    /**
     * Target the conversation at an order item and its authoritative owners.
     */
    public function forOrderItem(OrderItem $orderItem): static
    {
        return $this->state(fn (array $attributes): array => [
            'customer_id' => $orderItem->order->customer_id,
            'order_id' => $orderItem->order_id,
            'order_item_id' => $orderItem->id,
            'state' => ConversationState::CollectingReason,
        ]);
    }

    /**
     * Mark the conversation as resolved.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'state' => ConversationState::Resolved,
            'status' => ConversationStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }

    /**
     * Add a complete damaged-item reason.
     */
    public function damagedItem(): static
    {
        return $this->state(fn (array $attributes): array => [
            'reason' => RefundReason::DamagedItem,
            'reason_details' => 'The item arrived with visible damage.',
        ]);
    }
}
