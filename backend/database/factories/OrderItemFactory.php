<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'sku' => fake()->unique()->bothify('SKU-########'),
            'name' => fake()->words(3, true),
            'quantity' => 1,
            'unit_price_cents' => fake()->numberBetween(1000, 75000),
            'final_sale' => false,
        ];
    }

    /**
     * Indicate that the item is final sale.
     */
    public function finalSale(): static
    {
        return $this->state(fn (array $attributes): array => [
            'final_sale' => true,
        ]);
    }
}
