<?php

namespace Database\Factories;

use App\Enums\RefundStatus;
use App\Models\Refund;
use App\Models\RefundRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'refund_request_id' => RefundRequest::factory(),
            'order_item_id' => function (array $attributes): int {
                return RefundRequest::query()
                    ->whereKey($attributes['refund_request_id'])
                    ->firstOrFail()
                    ->order_item_id;
            },
            'amount_cents' => function (array $attributes): int {
                return RefundRequest::query()
                    ->whereKey($attributes['refund_request_id'])
                    ->firstOrFail()
                    ->amount_cents;
            },
            'status' => RefundStatus::Pending,
            'processor' => 'simulated',
            'idempotency_key' => fake()->unique()->uuid(),
            'processor_reference' => null,
            'attempts' => 0,
            'last_error' => null,
            'next_retry_at' => null,
            'processed_at' => null,
        ];
    }
}
