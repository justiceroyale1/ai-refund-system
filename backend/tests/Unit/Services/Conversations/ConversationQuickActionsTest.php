<?php

namespace Tests\Unit\Services\Conversations;

use App\Enums\ConversationSelectionType;
use App\Enums\ConversationState;
use App\Enums\RefundReason;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RefundConversation;
use App\Services\Conversations\ConversationQuickActions;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ConversationQuickActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_order_choices_include_only_owned_delivered_orders(): void
    {
        $customer = Customer::factory()->create();
        $eligibleOrder = Order::factory()->for($customer)->create([
            'reference' => 'ORD-ELIGIBLE',
            'status' => 'delivered',
            'delivered_at' => '2026-09-20 10:00:00',
        ]);
        Order::factory()->for($customer)->create([
            'reference' => 'ORD-UNDELIVERED',
            'status' => 'processing',
            'delivered_at' => null,
        ]);
        Order::factory()->for(Customer::factory())->create([
            'reference' => 'ORD-OTHER-CUSTOMER',
            'status' => 'delivered',
            'delivered_at' => '2026-09-21 10:00:00',
        ]);
        $conversation = RefundConversation::factory()->for($customer)->create([
            'state' => ConversationState::IdentifyingOrder,
        ]);

        $actions = (new ConversationQuickActions)->for($conversation);

        $this->assertSame([[
            'type' => ConversationSelectionType::Order->value,
            'value' => $eligibleOrder->id,
            'label' => 'ORD-ELIGIBLE',
        ]], $actions);
    }

    public function test_item_choices_are_scoped_to_the_selected_order_and_can_exclude_a_duplicate(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create();
        $excludedItem = OrderItem::factory()->for($order)->create(['name' => 'Keyboard']);
        $availableItem = OrderItem::factory()->for($order)->create(['name' => 'Mouse']);
        OrderItem::factory()->create(['name' => 'Another order item']);
        $conversation = RefundConversation::factory()->for($customer)->create([
            'order_id' => $order->id,
            'state' => ConversationState::IdentifyingItem,
        ]);

        $actions = (new ConversationQuickActions)->for($conversation, $excludedItem->id);

        $this->assertSame([[
            'type' => ConversationSelectionType::OrderItem->value,
            'value' => $availableItem->id,
            'label' => 'Mouse',
        ]], $actions);
    }

    public function test_reason_choices_exclude_the_unknown_internal_value(): void
    {
        $conversation = RefundConversation::factory()->make([
            'state' => ConversationState::CollectingReason,
        ]);

        $actions = (new ConversationQuickActions)->for($conversation);

        $this->assertSame(
            array_column([
                RefundReason::DamagedItem,
                RefundReason::IncorrectItem,
                RefundReason::MissingItem,
                RefundReason::ChangedMind,
                RefundReason::Other,
            ], 'value'),
            array_column($actions, 'value'),
        );
    }
}
