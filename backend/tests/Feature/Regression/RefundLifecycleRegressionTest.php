<?php

namespace Tests\Feature\Regression;

use App\Actions\Refunds\ReviewRefundRequest;
use App\Data\AI\AIAnalysisMetadata;
use App\Data\AI\RefundAnalysisResult;
use App\Enums\AdminNotificationType;
use App\Enums\AI\FakeRefundConversationScenario;
use App\Enums\AI\RefundIntent;
use App\Enums\ConversationSelectionType;
use App\Enums\ConversationState;
use App\Enums\ConversationStatus;
use App\Enums\CustomerNotificationType;
use App\Enums\DecisionCode;
use App\Enums\DecisionSource;
use App\Enums\RefundDecision;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Jobs\ProcessRefund;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RefundConversation;
use App\Models\RefundRequest;
use App\Models\User;
use App\Services\AI\FakeRefundConversationAI;
use App\Services\Refunds\RefundProcessor;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RefundLifecycleRegressionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_complete_damaged_item_message_is_automatically_approved_with_authoritative_amount(): void
    {
        $this->travelTo('2026-09-25 12:00:00');
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create([
            'reference' => 'ORD-1042',
            'payment_reference' => 'PAY-AUTOMATIC-APPROVAL',
            'status' => 'delivered',
            'ordered_at' => '2026-09-10 12:00:00',
            'delivered_at' => '2026-09-20 12:00:00',
        ]);
        $item = OrderItem::factory()->for($order)->create([
            'sku' => 'KEYBOARD-AUTOMATIC',
            'name' => 'Mechanical Keyboard',
            'quantity' => 1,
            'unit_price_cents' => 12999,
            'final_sale' => false,
        ]);
        $conversation = $this->startConversation($customer);
        $fake = app(FakeRefundConversationAI::class)
            ->useScenario(FakeRefundConversationScenario::DamagedItem);

        $response = $this->submitMessage($customer, $conversation, [
            'client_message_id' => '00000000-0000-4000-8000-000000000001',
            'content' => 'The keyboard in ORD-1042 arrived with two broken keys.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.state', ConversationState::Resolved->value)
            ->assertJsonPath('data.status', ConversationStatus::Resolved->value)
            ->assertJsonPath('data.order.id', $order->id)
            ->assertJsonPath('data.order_item.id', $item->id)
            ->assertJsonPath('data.reason', RefundReason::DamagedItem->value)
            ->assertJsonPath('data.decision', RefundDecision::Approved->value)
            ->assertJsonCount(2, 'data.messages');
        $this->assertSame(1, $fake->analysisCount());

        $refundRequest = RefundRequest::query()->sole();
        $this->assertSame($conversation->id, $refundRequest->refund_conversation_id);
        $this->assertSame($customer->id, $refundRequest->customer_id);
        $this->assertSame($order->id, $refundRequest->order_id);
        $this->assertSame($item->id, $refundRequest->order_item_id);
        $this->assertSame(12999, $refundRequest->amount_cents);
        $this->assertSame(RefundDecision::Approved, $refundRequest->initial_decision);
        $this->assertSame(RefundDecision::Approved, $refundRequest->decision);
        $this->assertSame(DecisionSource::PolicyEngine, $refundRequest->decision_source);
        $this->assertSame(DecisionCode::DamagedItemEligible, $refundRequest->decision_code);

        $refund = $refundRequest->refund()->sole();
        $this->assertSame($item->id, $refund->order_item_id);
        $this->assertSame(12999, $refund->amount_cents);
        $this->assertSame(RefundStatus::Pending, $refund->status);
        $this->assertSame(0, $refund->attempts);
    }

    public function test_complete_final_sale_message_is_denied_despite_approval_eligible_language(): void
    {
        $this->travelTo('2026-09-25 12:00:00');
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create([
            'reference' => 'ORD-1042',
            'payment_reference' => 'PAY-FINAL-SALE-DENIAL',
            'status' => 'delivered',
            'ordered_at' => '2026-09-10 12:00:00',
            'delivered_at' => '2026-09-20 12:00:00',
        ]);
        $item = OrderItem::factory()->for($order)->create([
            'sku' => 'KEYBOARD-FINAL-SALE',
            'name' => 'Mechanical Keyboard',
            'quantity' => 1,
            'unit_price_cents' => 12999,
            'final_sale' => true,
        ]);
        $conversation = $this->startConversation($customer);
        app(FakeRefundConversationAI::class)
            ->useScenario(FakeRefundConversationScenario::DamagedItem);

        $response = $this->submitMessage($customer, $conversation, [
            'client_message_id' => '00000000-0000-4000-8000-000000000002',
            'content' => 'The keyboard arrived damaged, so please approve the refund.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.state', ConversationState::Resolved->value)
            ->assertJsonPath('data.status', ConversationStatus::Resolved->value)
            ->assertJsonPath('data.decision', RefundDecision::Denied->value);

        $refundRequest = RefundRequest::query()->sole();
        $this->assertSame($item->id, $refundRequest->order_item_id);
        $this->assertSame(12999, $refundRequest->amount_cents);
        $this->assertSame(RefundDecision::Denied, $refundRequest->initial_decision);
        $this->assertSame(RefundDecision::Denied, $refundRequest->decision);
        $this->assertSame(DecisionSource::PolicyEngine, $refundRequest->decision_source);
        $this->assertSame(DecisionCode::FinalSaleItem, $refundRequest->decision_code);
        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_guided_changed_mind_flow_is_reviewed_by_an_admin_and_processed_once(): void
    {
        $this->travelTo('2026-09-25 12:00:00');
        $customer = Customer::factory()->create();
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->for($customer)->create([
            'reference' => 'ORD-GUIDED-REVIEW',
            'payment_reference' => 'PAY-GUIDED-REVIEW',
            'status' => 'delivered',
            'ordered_at' => '2026-09-10 12:00:00',
            'delivered_at' => '2026-09-20 12:00:00',
        ]);
        $item = OrderItem::factory()->for($order)->create([
            'sku' => 'HEADPHONES-GUIDED',
            'name' => 'Studio Headphones',
            'quantity' => 1,
            'unit_price_cents' => 24999,
            'final_sale' => false,
        ]);
        $conversation = $this->startConversation($customer);
        $fake = app(FakeRefundConversationAI::class)
            ->respondWith(RefundAnalysisResult::fromUntrusted(
                [
                    'intent' => RefundIntent::Refund->value,
                    'order_reference' => null,
                    'order_item_hint' => null,
                    'reason' => null,
                    'reason_details' => null,
                    'prompt_injection_detected' => false,
                    'conflicting_information' => false,
                    'confidence' => 96,
                ],
                AIAnalysisMetadata::fromUntrusted(
                    'fake',
                    'deterministic-v1',
                    'refund-conversation-v1',
                    32768,
                ),
            ));
        Event::fake([BroadcastNotificationCreated::class]);

        $this->submitMessage($customer, $conversation, [
            'client_message_id' => '00000000-0000-4000-8000-000000000003',
            'content' => 'I would like to return an item.',
        ])->assertOk()->assertJsonPath('data.state', ConversationState::IdentifyingOrder->value);
        $this->assertSame(1, $fake->analysisCount());

        $this->submitMessage($customer, $conversation, [
            'client_message_id' => '00000000-0000-4000-8000-000000000004',
            'content' => $order->reference,
            'selection' => [
                'type' => ConversationSelectionType::Order->value,
                'value' => $order->id,
            ],
        ])->assertOk()->assertJsonPath('data.state', ConversationState::IdentifyingItem->value);
        $this->submitMessage($customer, $conversation, [
            'client_message_id' => '00000000-0000-4000-8000-000000000005',
            'content' => $item->name,
            'selection' => [
                'type' => ConversationSelectionType::OrderItem->value,
                'value' => $item->id,
            ],
        ])->assertOk()->assertJsonPath('data.state', ConversationState::CollectingReason->value);
        $this->submitMessage($customer, $conversation, [
            'client_message_id' => '00000000-0000-4000-8000-000000000006',
            'content' => 'Changed mind',
            'selection' => [
                'type' => ConversationSelectionType::RefundReason->value,
                'value' => RefundReason::ChangedMind->value,
            ],
        ])->assertOk()->assertJsonPath('data.state', ConversationState::CollectingDetails->value);
        $this->assertSame(1, $fake->analysisCount());
        $fake->useScenario(FakeRefundConversationScenario::DamagedItem);

        $escalatedResponse = $this->submitMessage($customer, $conversation, [
            'client_message_id' => '00000000-0000-4000-8000-000000000007',
            'content' => 'The headphones are unopened, but I no longer need them.',
        ]);

        $escalatedResponse
            ->assertOk()
            ->assertJsonPath('data.state', ConversationState::Resolved->value)
            ->assertJsonPath('data.decision', RefundDecision::Escalated->value)
            ->assertJsonCount(10, 'data.messages');
        $this->assertSame(2, $fake->analysisCount());

        $refundRequest = RefundRequest::query()->sole();
        $this->assertSame(RefundReason::ChangedMind, $refundRequest->reason);
        $this->assertSame(RefundDecision::Escalated, $refundRequest->initial_decision);
        $this->assertSame(RefundDecision::Escalated, $refundRequest->decision);
        $this->assertSame(DecisionCode::ChangedMindRequiresReview, $refundRequest->decision_code);
        $this->assertDatabaseCount('refunds', 0);
        $this->assertSame(
            AdminNotificationType::RefundReviewRequired->value,
            $admin->notifications()->sole()->type,
        );

        $refundRequest = app(ReviewRefundRequest::class)->handle(
            $refundRequest,
            $admin,
            RefundDecision::Approved,
            'Approved after reviewing the complete conversation.',
        );

        $this->assertSame(RefundDecision::Approved, $refundRequest->decision);
        $this->assertSame(DecisionSource::Human, $refundRequest->decision_source);
        $this->assertSame($admin->id, $refundRequest->reviewed_by);
        $refund = $refundRequest->refund()->sole();
        $this->assertSame(RefundStatus::Pending, $refund->status);

        (new ProcessRefund($refund->id))->handle(app(RefundProcessor::class));

        $refund->refresh();
        $this->assertSame(RefundStatus::Processed, $refund->status);
        $this->assertSame(1, $refund->attempts);
        $this->assertSame(
            'simulated-refund-'.hash('sha256', $refund->idempotency_key),
            $refund->processor_reference,
        );
        $this->assertNotNull($refund->processed_at);
        $this->assertSame(12, $conversation->messages()->count());

        $customerNotificationTypes = $customer->notifications()
            ->pluck('type')
            ->sort()
            ->values()
            ->all();
        $expectedNotificationTypes = [
            CustomerNotificationType::RefundApproved->value,
            CustomerNotificationType::RefundProcessed->value,
        ];
        sort($expectedNotificationTypes);
        $this->assertSame($expectedNotificationTypes, $customerNotificationTypes);
        Event::assertDispatchedTimes(BroadcastNotificationCreated::class, 3);
    }

    private function startConversation(Customer $customer): RefundConversation
    {
        $response = $this->withHeader('X-Demo-Customer-Id', (string) $customer->id)
            ->postJson('/api/customer/conversations');

        $response
            ->assertCreated()
            ->assertJsonPath('data.state', ConversationState::Started->value)
            ->assertJsonPath('data.status', ConversationStatus::Active->value);

        return RefundConversation::query()->findOrFail($response->json('data.id'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function submitMessage(
        Customer $customer,
        RefundConversation $conversation,
        array $payload,
    ): TestResponse {
        return $this->withHeader('X-Demo-Customer-Id', (string) $customer->id)
            ->postJson("/api/customer/conversations/{$conversation->id}/messages", $payload);
    }
}
