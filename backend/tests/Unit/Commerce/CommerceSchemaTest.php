<?php

namespace Tests\Unit\Commerce;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommerceSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_schema_contains_commerce_columns_and_query_indexes(): void
    {
        $this->assertTrue(Schema::hasColumns('customers', [
            'id',
            'name',
            'email',
            'created_at',
            'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('users', ['is_admin']));
        $this->assertTrue(Schema::hasColumns('orders', [
            'id',
            'customer_id',
            'reference',
            'payment_reference',
            'status',
            'ordered_at',
            'delivered_at',
            'created_at',
            'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('order_items', [
            'id',
            'order_id',
            'sku',
            'name',
            'quantity',
            'unit_price_cents',
            'final_sale',
            'created_at',
            'updated_at',
        ]));

        $orderIndexes = array_column(Schema::getIndexes('orders'), 'name');
        $orderItemIndexes = array_column(Schema::getIndexes('order_items'), 'name');

        $this->assertContains('orders_reference_unique', $orderIndexes);
        $this->assertContains('orders_payment_reference_unique', $orderIndexes);
        $this->assertContains('orders_customer_id_ordered_at_index', $orderIndexes);
        $this->assertContains('orders_customer_id_delivered_at_index', $orderIndexes);
        $this->assertContains('order_items_order_id_index', $orderItemIndexes);
    }

    public function test_duplicate_order_reference_is_rejected(): void
    {
        $order = Order::factory()->create();

        $this->expectException(QueryException::class);

        Order::factory()->create(['reference' => $order->reference]);
    }

    public function test_duplicate_payment_reference_is_rejected(): void
    {
        $order = Order::factory()->create();

        $this->expectException(QueryException::class);

        Order::factory()->create(['payment_reference' => $order->payment_reference]);
    }

    public function test_order_rejects_a_customer_that_does_not_exist(): void
    {
        $this->expectException(QueryException::class);

        Order::factory()->create(['customer_id' => PHP_INT_MAX]);
    }

    public function test_order_item_rejects_an_order_that_does_not_exist(): void
    {
        $this->expectException(QueryException::class);

        OrderItem::factory()->create(['order_id' => PHP_INT_MAX]);
    }
}
