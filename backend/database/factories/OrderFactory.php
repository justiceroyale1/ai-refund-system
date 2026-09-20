<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $orderedAt = fake()->dateTimeBetween('-120 days', '-10 days');

        return [
            'customer_id' => Customer::factory(),
            'reference' => fake()->unique()->bothify('ORD-####'),
            'payment_reference' => fake()->unique()->bothify('PAY-################'),
            'status' => 'delivered',
            'ordered_at' => $orderedAt,
            'delivered_at' => fake()->dateTimeBetween($orderedAt, 'now'),
        ];
    }
}
