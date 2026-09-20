<?php

namespace Tests\Unit\Commerce;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommerceModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_models_expose_the_complete_commerce_relationship_graph(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create();
        $item = OrderItem::factory()->for($order)->create();

        $this->assertTrue($customer->orders()->whereKey($order)->exists());
        $this->assertTrue($order->customer->is($customer));
        $this->assertTrue($order->items()->whereKey($item)->exists());
        $this->assertTrue($item->order->is($order));
    }

    public function test_money_boolean_quantity_and_order_dates_use_domain_safe_types(): void
    {
        $item = OrderItem::factory()
            ->finalSale()
            ->create([
                'quantity' => 1,
                'unit_price_cents' => 12999,
            ]);
        $order = $item->order;

        $this->assertSame(12999, $item->unit_price_cents);
        $this->assertSame(1, $item->quantity);
        $this->assertTrue($item->final_sale);
        $this->assertInstanceOf(CarbonInterface::class, $order->ordered_at);
        $this->assertInstanceOf(CarbonInterface::class, $order->delivered_at);
        $this->assertDatabaseHas('order_items', [
            'id' => $item->id,
            'unit_price_cents' => 12999,
        ]);
    }

    public function test_factories_create_a_complete_graph_with_non_admin_defaults(): void
    {
        $item = OrderItem::factory()->create();
        $user = User::factory()->create();

        $this->assertModelExists($item->order);
        $this->assertModelExists($item->order->customer);
        $this->assertSame(1, $item->quantity);
        $this->assertFalse($item->final_sale);
        $this->assertFalse($user->is_admin);
    }

    public function test_admin_factory_state_and_user_cast_expose_a_boolean_flag(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->is_admin);
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'is_admin' => true,
        ]);
    }
}
