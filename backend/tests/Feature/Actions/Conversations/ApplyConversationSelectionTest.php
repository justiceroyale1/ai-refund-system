<?php

namespace Tests\Feature\Actions\Conversations;

use App\Actions\Conversations\ApplyConversationSelection;
use App\Data\Conversations\ConversationSelection;
use App\Data\Conversations\ConversationSelectionResult;
use App\Enums\ConversationMessageTemplate;
use App\Enums\ConversationSelectionOutcome;
use App\Enums\ConversationSelectionType;
use App\Enums\ConversationState;
use App\Enums\ConversationStatus;
use App\Enums\Http\ApiErrorCode;
use App\Enums\MessageSender;
use App\Enums\RefundReason;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RefundConversation;
use App\Services\Conversations\ConversationWorkflowException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ApplyConversationSelectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_applies_owned_order_item_and_reason_selections_in_sequence(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create([
            'reference' => 'ORD-2042',
            'status' => 'delivered',
            'delivered_at' => '2026-09-20 10:00:00',
        ]);
        $item = OrderItem::factory()->for($order)->create([
            'sku' => 'SKU-KEYBOARD',
            'name' => 'Mechanical Keyboard',
        ]);
        $conversation = RefundConversation::factory()->for($customer)->create([
            'state' => ConversationState::IdentifyingOrder,
        ]);

        $orderResult = $this->apply($conversation, 'order', $order->id);
        $itemResult = $this->apply($orderResult->conversation, 'order_item', $item->id);
        $reasonResult = $this->apply($itemResult->conversation, 'refund_reason', 'damaged_item');

        $this->assertSame(ConversationSelectionOutcome::Applied, $reasonResult->outcome);
        $this->assertSame(ConversationState::CollectingDetails, $reasonResult->conversation->state);
        $this->assertSame(RefundReason::DamagedItem, $reasonResult->conversation->reason);
        $this->assertSame([], $reasonResult->actions);
        $this->assertDatabaseHas('refund_conversations', [
            'id' => $conversation->id,
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'state' => ConversationState::CollectingDetails->value,
            'reason' => RefundReason::DamagedItem->value,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'subject_type' => RefundConversation::class,
            'subject_id' => $conversation->id,
            'event' => 'conversation.order_identified',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'subject_type' => RefundConversation::class,
            'subject_id' => $conversation->id,
            'event' => 'conversation.item_identified',
        ]);
    }

    public function test_rejects_an_unowned_order_without_modifying_the_conversation(): void
    {
        $customer = Customer::factory()->create();
        $unownedOrder = Order::factory()->for(Customer::factory())->create();
        $conversation = RefundConversation::factory()->for($customer)->create([
            'state' => ConversationState::IdentifyingOrder,
        ]);

        $exception = $this->captureWorkflowException(
            fn () => $this->apply($conversation, 'order', $unownedOrder->id),
        );

        $this->assertSame(ApiErrorCode::InvalidConversationSelection, $exception->errorCode());
        $this->assertSame(422, $exception->status());
        $this->assertDatabaseHas('refund_conversations', [
            'id' => $conversation->id,
            'order_id' => null,
            'state' => ConversationState::IdentifyingOrder->value,
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_rejects_an_undelivered_order_without_modifying_the_conversation(): void
    {
        $customer = Customer::factory()->create();
        $undeliveredOrder = Order::factory()->for($customer)->create([
            'status' => 'processing',
            'delivered_at' => null,
        ]);
        $conversation = RefundConversation::factory()->for($customer)->create([
            'state' => ConversationState::IdentifyingOrder,
        ]);

        $exception = $this->captureWorkflowException(
            fn () => $this->apply($conversation, 'order', $undeliveredOrder->id),
        );

        $this->assertSame(ApiErrorCode::InvalidConversationSelection, $exception->errorCode());
        $this->assertDatabaseHas('refund_conversations', [
            'id' => $conversation->id,
            'order_id' => null,
            'state' => ConversationState::IdentifyingOrder->value,
        ]);
    }

    public function test_rejects_an_item_from_another_order_without_modifying_the_conversation(): void
    {
        $customer = Customer::factory()->create();
        $selectedOrder = Order::factory()->for($customer)->create();
        $otherOrder = Order::factory()->for($customer)->create();
        $otherItem = OrderItem::factory()->for($otherOrder)->create();
        $conversation = RefundConversation::factory()->for($customer)->create([
            'order_id' => $selectedOrder->id,
            'state' => ConversationState::IdentifyingItem,
        ]);

        $exception = $this->captureWorkflowException(
            fn () => $this->apply($conversation, 'order_item', $otherItem->id),
        );

        $this->assertSame(ApiErrorCode::InvalidConversationSelection, $exception->errorCode());
        $this->assertDatabaseHas('refund_conversations', [
            'id' => $conversation->id,
            'order_item_id' => null,
            'state' => ConversationState::IdentifyingItem->value,
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_rejects_a_selection_that_is_invalid_for_the_current_state(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create();
        $conversation = RefundConversation::factory()->for($customer)->create([
            'state' => ConversationState::Started,
        ]);

        $exception = $this->captureWorkflowException(
            fn () => $this->apply($conversation, 'order', $order->id),
        );

        $this->assertSame(ApiErrorCode::InvalidConversationTransition, $exception->errorCode());
        $this->assertSame(409, $exception->status());
        $this->assertDatabaseHas('refund_conversations', [
            'id' => $conversation->id,
            'order_id' => null,
            'state' => ConversationState::Started->value,
        ]);
    }

    public function test_duplicate_item_selection_offers_the_two_approved_actions(): void
    {
        [$newConversation, $existingConversation, $duplicateItem] = $this->duplicateScenario();

        $result = $this->apply($newConversation, 'order_item', $duplicateItem->id);

        $this->assertSame(ConversationSelectionOutcome::DuplicateDetected, $result->outcome);
        $this->assertSame($existingConversation->id, $result->existingConversationId);
        $this->assertSame([
            ConversationSelectionType::OpenExistingConversation->value,
            ConversationSelectionType::ChooseAnotherItem->value,
        ], array_column($result->actions, 'type'));
        $this->assertDatabaseHas('refund_conversations', [
            'id' => $newConversation->id,
            'order_item_id' => null,
            'state' => ConversationState::IdentifyingItem->value,
            'status' => ConversationStatus::Active->value,
        ]);
        $this->assertDatabaseHas('conversation_messages', [
            'refund_conversation_id' => $newConversation->id,
            'sender' => MessageSender::Assistant->value,
            'content' => ConversationMessageTemplate::DuplicateItemDetected->value,
        ]);
    }

    public function test_opening_the_existing_duplicate_resolves_only_the_newer_conversation(): void
    {
        [$newConversation, $existingConversation, $duplicateItem] = $this->duplicateScenario();
        $this->apply($newConversation, 'order_item', $duplicateItem->id);
        $newConversation->messages()->create([
            'sender' => MessageSender::Customer,
            'client_message_id' => '6f92fcbb-b660-4fba-b07f-8329381da397',
            'content' => 'Open existing conversation',
            'metadata' => [
                'selection' => [
                    'type' => ConversationSelectionType::OpenExistingConversation->value,
                    'value' => $existingConversation->id,
                ],
            ],
        ]);

        $result = $this->apply(
            $newConversation,
            'open_existing_conversation',
            $existingConversation->id,
        );

        $this->assertSame(ConversationSelectionOutcome::ExistingConversationOpened, $result->outcome);
        $this->assertSame($existingConversation->id, $result->existingConversationId);
        $this->assertSame(ConversationState::Resolved, $result->conversation->state);
        $this->assertSame(ConversationStatus::Resolved, $result->conversation->status);
        $this->assertNotNull($result->conversation->resolved_at);
        $this->assertDatabaseHas('refund_conversations', [
            'id' => $existingConversation->id,
            'status' => ConversationStatus::Active->value,
            'order_item_id' => $duplicateItem->id,
        ]);
        $this->assertDatabaseHas('conversation_messages', [
            'refund_conversation_id' => $newConversation->id,
            'sender' => MessageSender::System->value,
            'content' => ConversationMessageTemplate::DuplicateConversationResolved->value,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'subject_type' => RefundConversation::class,
            'subject_id' => $newConversation->id,
            'event' => 'conversation.duplicate_resolved',
        ]);
        $this->assertDatabaseCount('refund_requests', 0);
    }

    public function test_choosing_another_item_keeps_the_conversation_active_and_excludes_the_duplicate(): void
    {
        [$newConversation, $existingConversation, $duplicateItem, $alternateItem] = $this->duplicateScenario();
        $this->apply($newConversation, 'order_item', $duplicateItem->id);

        $result = $this->apply($newConversation, 'choose_another_item', $duplicateItem->id);

        $this->assertSame(ConversationSelectionOutcome::AlternateItemRequested, $result->outcome);
        $this->assertSame(ConversationState::IdentifyingItem, $result->conversation->state);
        $this->assertSame(ConversationStatus::Active, $result->conversation->status);
        $this->assertNull($result->conversation->order_item_id);
        $this->assertSame([$alternateItem->id], array_column($result->actions, 'value'));
        $this->assertSame(
            ConversationSelectionType::OrderItem->value,
            $result->actions[0]['type'],
        );
        $this->assertDatabaseHas('conversation_messages', [
            'refund_conversation_id' => $newConversation->id,
            'sender' => MessageSender::Assistant->value,
            'content' => ConversationMessageTemplate::AlternateItemRequested->value,
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'subject_type' => RefundConversation::class,
            'subject_id' => $newConversation->id,
            'event' => 'conversation.duplicate_resolved',
        ]);
        $this->assertSame(ConversationStatus::Active, $existingConversation->fresh()?->status);
    }

    public function test_rejects_a_tampered_duplicate_action_without_resolving_the_conversation(): void
    {
        [$newConversation, $existingConversation, $duplicateItem, $alternateItem] = $this->duplicateScenario();
        $otherConversation = RefundConversation::factory()->forOrderItem($alternateItem)->create();
        $this->apply($newConversation, 'order_item', $duplicateItem->id);

        $exception = $this->captureWorkflowException(
            fn () => $this->apply(
                $newConversation,
                'open_existing_conversation',
                $otherConversation->id,
            ),
        );

        $this->assertSame(ApiErrorCode::InvalidConversationSelection, $exception->errorCode());
        $this->assertDatabaseHas('refund_conversations', [
            'id' => $newConversation->id,
            'status' => ConversationStatus::Active->value,
            'state' => ConversationState::IdentifyingItem->value,
        ]);
        $this->assertSame(ConversationStatus::Active, $existingConversation->fresh()?->status);
    }

    public function test_rejects_selections_for_a_resolved_conversation(): void
    {
        $conversation = RefundConversation::factory()->resolved()->create();

        $exception = $this->captureWorkflowException(
            fn () => $this->apply($conversation, 'refund_reason', 'other'),
        );

        $this->assertSame(ApiErrorCode::ConversationAlreadyResolved, $exception->errorCode());
        $this->assertSame(409, $exception->status());
        $this->assertDatabaseHas('refund_conversations', [
            'id' => $conversation->id,
            'status' => ConversationStatus::Resolved->value,
            'state' => ConversationState::Resolved->value,
        ]);
    }

    private function apply(
        RefundConversation $conversation,
        string $type,
        mixed $value,
    ): ConversationSelectionResult {
        return app(ApplyConversationSelection::class)->handle(
            $conversation,
            ConversationSelection::fromUntrusted($type, $value),
        );
    }

    private function captureWorkflowException(callable $callback): ConversationWorkflowException
    {
        try {
            $callback();
            $this->fail('The workflow operation unexpectedly succeeded.');
        } catch (ConversationWorkflowException $exception) {
            return $exception;
        }
    }

    /**
     * @return array{RefundConversation, RefundConversation, OrderItem, OrderItem}
     */
    private function duplicateScenario(): array
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create();
        $duplicateItem = OrderItem::factory()->for($order)->create(['name' => 'Keyboard']);
        $alternateItem = OrderItem::factory()->for($order)->create(['name' => 'Mouse']);
        $existingConversation = RefundConversation::factory()->forOrderItem($duplicateItem)->create();
        $newConversation = RefundConversation::factory()->for($customer)->create([
            'order_id' => $order->id,
            'state' => ConversationState::IdentifyingItem,
        ]);

        return [$newConversation, $existingConversation, $duplicateItem, $alternateItem];
    }
}
