<?php

namespace Database\Factories;

use App\Enums\DecisionCode;
use App\Enums\DecisionSource;
use App\Enums\RefundDecision;
use App\Enums\RefundReason;
use App\Models\OrderItem;
use App\Models\RefundConversation;
use App\Models\RefundRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RefundRequest>
 */
class RefundRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'customer_id' => function (array $attributes): int {
                return OrderItem::query()
                    ->whereKey($attributes['order_item_id'])
                    ->firstOrFail()
                    ->order
                    ->customer_id;
            },
            'order_id' => function (array $attributes): int {
                return OrderItem::query()
                    ->whereKey($attributes['order_item_id'])
                    ->firstOrFail()
                    ->order_id;
            },
            'refund_conversation_id' => function (array $attributes): int {
                $orderItem = OrderItem::query()
                    ->whereKey($attributes['order_item_id'])
                    ->firstOrFail();

                return RefundConversation::factory()
                    ->forOrderItem($orderItem)
                    ->damagedItem()
                    ->resolved()
                    ->create()
                    ->id;
            },
            'reason' => RefundReason::DamagedItem,
            'reason_details' => 'The item arrived with visible damage.',
            'amount_cents' => function (array $attributes): int {
                return OrderItem::query()
                    ->whereKey($attributes['order_item_id'])
                    ->firstOrFail()
                    ->unit_price_cents;
            },
            'initial_decision' => RefundDecision::Approved,
            'decision' => RefundDecision::Approved,
            'decision_source' => DecisionSource::PolicyEngine,
            'decision_code' => DecisionCode::DamagedItemEligible,
            'policy_checks' => [
                [
                    'code' => 'FINAL_SALE',
                    'result' => 'passed',
                    'message' => 'Item is not marked as final sale.',
                ],
            ],
            'reviewed_by' => null,
            'review_note' => null,
            'decided_at' => now(),
        ];
    }

    /**
     * Mark the request as awaiting human review.
     */
    public function escalated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'initial_decision' => RefundDecision::Escalated,
            'decision' => RefundDecision::Escalated,
            'decision_source' => DecisionSource::PolicyEngine,
            'decision_code' => DecisionCode::HighValueReviewRequired,
            'reviewed_by' => null,
            'review_note' => null,
        ]);
    }
}
