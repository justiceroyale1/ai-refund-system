<?php

namespace Tests\Feature\Http;

use App\Enums\AuditActorType;
use App\Enums\ConversationMessageTemplate;
use App\Enums\ConversationState;
use App\Enums\ConversationStatus;
use App\Enums\MessageSender;
use App\Enums\RefundDecision;
use App\Enums\RefundReason;
use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RefundConversation;
use App\Models\RefundRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RefundConversationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lists_only_owned_conversations_in_recent_activity_order(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $olderConversation = RefundConversation::factory()->for($customer)->create([
            'created_at' => '2026-09-20 10:00:00',
            'updated_at' => '2026-09-20 10:00:00',
        ]);
        $newerConversation = RefundConversation::factory()->for($customer)->create([
            'created_at' => '2026-09-20 11:00:00',
            'updated_at' => '2026-09-20 11:00:00',
        ]);
        RefundConversation::factory()->for($otherCustomer)->create([
            'updated_at' => '2026-09-20 12:00:00',
        ]);
        ConversationMessage::factory()->for($olderConversation)->create();

        $response = $this->withDemoCustomer($customer)
            ->getJson('/api/customer/conversations');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $olderConversation->id)
            ->assertJsonPath('data.1.id', $newerConversation->id)
            ->assertJsonPath('data.0.state', ConversationState::Started->value)
            ->assertJsonPath('data.0.status', ConversationStatus::Active->value)
            ->assertJsonPath('data.0.available_actions', [])
            ->assertJsonMissingPath('data.0.messages');
    }

    public function test_paginates_conversation_history(): void
    {
        $customer = Customer::factory()->create();
        RefundConversation::factory()->count(16)->for($customer)->create();

        $response = $this->withDemoCustomer($customer)
            ->getJson('/api/customer/conversations');

        $response
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 16)
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total'],
            ]);
    }

    public function test_creates_an_initial_conversation_and_started_audit_record(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $response = $this->withDemoCustomer($customer)
            ->postJson('/api/customer/conversations', [
                'customer_id' => $otherCustomer->id,
                'state' => ConversationState::Resolved->value,
                'status' => ConversationStatus::Resolved->value,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.state', ConversationState::Started->value)
            ->assertJsonPath('data.status', ConversationStatus::Active->value)
            ->assertJsonPath('data.order', null)
            ->assertJsonPath('data.order_item', null)
            ->assertJsonPath('data.available_actions', [])
            ->assertJsonCount(0, 'data.messages');

        $conversationId = $response->json('data.id');

        $this->assertDatabaseHas('refund_conversations', [
            'id' => $conversationId,
            'customer_id' => $customer->id,
            'state' => ConversationState::Started->value,
            'status' => ConversationStatus::Active->value,
            'order_id' => null,
            'order_item_id' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => AuditActorType::Customer->value,
            'actor_id' => $customer->id,
            'subject_type' => RefundConversation::class,
            'subject_id' => $conversationId,
            'event' => 'conversation.started',
        ]);
    }

    public function test_returns_owned_conversation_summary_transcript_and_latest_actions(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create([
            'reference' => 'ORD-2042',
        ]);
        $item = OrderItem::factory()->for($order)->create([
            'name' => 'Mechanical Keyboard',
        ]);
        $conversation = RefundConversation::factory()
            ->forOrderItem($item)
            ->damagedItem()
            ->create();
        $firstMessage = ConversationMessage::factory()->for($conversation)->create([
            'sender' => MessageSender::Customer,
            'content' => 'The keyboard arrived damaged.',
            'created_at' => '2026-09-20 10:00:00',
            'updated_at' => '2026-09-20 10:00:00',
        ]);
        $latestMessage = ConversationMessage::factory()->assistant()->for($conversation)->create([
            'content' => ConversationMessageTemplate::DamagedItemDetailsRequested->value,
            'metadata' => [
                'actions' => [
                    ['type' => 'provide_details', 'label' => 'Describe damage'],
                ],
            ],
            'created_at' => '2026-09-20 11:00:00',
            'updated_at' => '2026-09-20 11:00:00',
        ]);

        $response = $this->withDemoCustomer($customer)
            ->getJson("/api/customer/conversations/{$conversation->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $conversation->id)
            ->assertJsonPath('data.order.id', $order->id)
            ->assertJsonPath('data.order.reference', 'ORD-2042')
            ->assertJsonPath('data.order_item.id', $item->id)
            ->assertJsonPath('data.order_item.name', 'Mechanical Keyboard')
            ->assertJsonPath('data.state', ConversationState::CollectingReason->value)
            ->assertJsonPath('data.status', ConversationStatus::Active->value)
            ->assertJsonPath('data.reason', RefundReason::DamagedItem->value)
            ->assertJsonPath('data.decision', null)
            ->assertJsonPath('data.available_actions.0.type', 'provide_details')
            ->assertJsonPath('data.messages.0.id', $firstMessage->id)
            ->assertJsonPath('data.messages.0.sender', MessageSender::Customer->value)
            ->assertJsonPath('data.messages.1.id', $latestMessage->id)
            ->assertJsonPath('data.messages.1.sender', MessageSender::Assistant->value)
            ->assertJsonMissingPath('data.customer_id');
    }

    public function test_returns_the_current_refund_decision_in_the_conversation_summary(): void
    {
        $customer = Customer::factory()->create();
        $item = OrderItem::factory()->for(Order::factory()->for($customer))->create();
        $conversation = RefundConversation::factory()
            ->forOrderItem($item)
            ->damagedItem()
            ->resolved()
            ->create();
        RefundRequest::factory()->create([
            'refund_conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'order_id' => $item->order_id,
            'order_item_id' => $item->id,
            'decision' => RefundDecision::Approved,
        ]);

        $response = $this->withDemoCustomer($customer)
            ->getJson("/api/customer/conversations/{$conversation->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.decision', RefundDecision::Approved->value);
    }

    public function test_returns_404_without_exposing_another_customers_conversation(): void
    {
        $requestingCustomer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $conversation = RefundConversation::factory()->for($otherCustomer)->create();

        $response = $this->withDemoCustomer($requestingCustomer)
            ->getJson("/api/customer/conversations/{$conversation->id}");

        $response
            ->assertNotFound()
            ->assertExactJson([
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'The requested resource was not found.',
                    'details' => [],
                ],
            ]);
    }

    #[DataProvider('malformedConversationIds')]
    public function test_returns_404_when_the_conversation_id_is_malformed(string $conversationId): void
    {
        $customer = Customer::factory()->create();

        $response = $this->withDemoCustomer($customer)
            ->getJson("/api/customer/conversations/{$conversationId}");

        $response
            ->assertNotFound()
            ->assertExactJson([
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'The requested resource was not found.',
                    'details' => [],
                ],
            ]);
    }

    public function test_requires_demo_customer_identity_for_conversation_routes(): void
    {
        $response = $this->getJson('/api/customer/conversations');

        $response
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'DEMO_CUSTOMER_ID_REQUIRED');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedConversationIds(): array
    {
        return [
            'zero' => ['0'],
            'negative integer' => ['-1'],
            'decimal number' => ['1.5'],
            'non-numeric value' => ['not-a-conversation'],
            'non-canonical leading zero' => ['01'],
        ];
    }

    private function withDemoCustomer(Customer $customer): static
    {
        return $this->withHeader('X-Demo-Customer-Id', (string) $customer->id);
    }
}
