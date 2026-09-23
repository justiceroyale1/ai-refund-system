<?php

namespace Tests\Feature\Http;

use App\Enums\AI\FakeRefundConversationScenario;
use App\Enums\AuditActorType;
use App\Enums\ConversationMessageTemplate;
use App\Enums\ConversationSelectionType;
use App\Enums\ConversationState;
use App\Enums\ConversationStatus;
use App\Enums\MessageSender;
use App\Enums\RefundReason;
use App\Exceptions\AI\AIProviderUnavailableException;
use App\Exceptions\AI\InvalidAIResponseException;
use App\Models\AiAnalysis;
use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RefundConversation;
use App\Services\AI\FakeRefundConversationAI;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConversationMessageControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_analyzes_a_free_form_customer_message_and_asks_for_missing_order_information(): void
    {
        $customer = Customer::factory()->create();
        $conversation = RefundConversation::factory()->for($customer)->create();
        $clientMessageId = '6f92fcbb-b660-4fba-b07f-8329381da397';
        app(FakeRefundConversationAI::class)->useScenario(FakeRefundConversationScenario::MissingInformation);

        $response = $this->submitMessage($customer, $conversation, [
            'client_message_id' => $clientMessageId,
            'content' => 'The keyboard arrived with two broken keys.',
            'customer_id' => Customer::factory()->create()->id,
            'state' => ConversationState::Resolved->value,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $conversation->id)
            ->assertJsonPath('data.state', ConversationState::IdentifyingOrder->value)
            ->assertJsonPath('data.status', ConversationStatus::Active->value)
            ->assertJsonPath('data.available_actions', [])
            ->assertJsonCount(2, 'data.messages')
            ->assertJsonPath('data.messages.0.client_message_id', $clientMessageId)
            ->assertJsonPath('data.messages.0.sender', MessageSender::Customer->value)
            ->assertJsonPath('data.messages.0.content', 'The keyboard arrived with two broken keys.')
            ->assertJsonPath('data.messages.1.sender', MessageSender::Assistant->value)
            ->assertJsonPath('data.messages.1.content', ConversationMessageTemplate::OrderRequested->value);

        $this->assertDatabaseHas('conversation_messages', [
            'refund_conversation_id' => $conversation->id,
            'client_message_id' => $clientMessageId,
            'sender' => MessageSender::Customer->value,
            'content' => 'The keyboard arrived with two broken keys.',
        ]);
        $this->assertDatabaseHas('refund_conversations', [
            'id' => $conversation->id,
            'customer_id' => $customer->id,
            'state' => ConversationState::IdentifyingOrder->value,
            'status' => ConversationStatus::Active->value,
        ]);
        $this->assertDatabaseHas('ai_analyses', [
            'refund_conversation_id' => $conversation->id,
            'confidence' => 62,
        ]);
    }

    #[DataProvider('invalidMessagePayloads')]
    public function test_returns_422_for_invalid_message_payloads(
        array $payload,
        string $errorField,
        string $errorMessage,
    ): void {
        $customer = Customer::factory()->create();
        $conversation = RefundConversation::factory()->for($customer)->create();

        $response = $this->submitMessage($customer, $conversation, $payload);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $errors = $response->json('error.details.errors');
        $this->assertIsArray($errors);
        $this->assertSame($errorMessage, $errors[$errorField][0] ?? null);
        $this->assertDatabaseCount('conversation_messages', 0);
    }

    public function test_applies_a_valid_selection_and_persists_the_customer_and_follow_up_messages(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create();
        $item = OrderItem::factory()->for($order)->create(['name' => 'Mechanical Keyboard']);
        $conversation = RefundConversation::factory()->for($customer)->create([
            'state' => ConversationState::IdentifyingOrder,
        ]);
        $clientMessageId = '76f06743-b329-49a6-b060-a2aa77d34492';
        $fake = app(FakeRefundConversationAI::class);

        $response = $this->submitMessage($customer, $conversation, [
            'client_message_id' => $clientMessageId,
            'content' => $order->reference,
            'selection' => [
                'type' => ConversationSelectionType::Order->value,
                'value' => $order->id,
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.order.id', $order->id)
            ->assertJsonPath('data.state', ConversationState::IdentifyingItem->value)
            ->assertJsonPath('data.available_actions.0.type', ConversationSelectionType::OrderItem->value)
            ->assertJsonPath('data.available_actions.0.value', $item->id)
            ->assertJsonCount(2, 'data.messages')
            ->assertJsonPath('data.messages.0.client_message_id', $clientMessageId)
            ->assertJsonPath('data.messages.0.metadata.selection.type', ConversationSelectionType::Order->value)
            ->assertJsonPath('data.messages.0.metadata.selection.value', $order->id)
            ->assertJsonPath('data.messages.1.sender', MessageSender::Assistant->value)
            ->assertJsonPath('data.messages.1.content', ConversationMessageTemplate::OrderItemRequested->value);

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => AuditActorType::Customer->value,
            'actor_id' => $customer->id,
            'subject_type' => RefundConversation::class,
            'subject_id' => $conversation->id,
            'event' => 'conversation.order_identified',
        ]);
        $this->assertSame(0, $fake->analysisCount());
    }

    public function test_item_selection_advances_to_reason_collection_with_reason_actions(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create();
        $item = OrderItem::factory()->for($order)->create();
        $conversation = RefundConversation::factory()->for($customer)->create([
            'order_id' => $order->id,
            'state' => ConversationState::IdentifyingItem,
        ]);

        $response = $this->submitMessage($customer, $conversation, [
            'client_message_id' => '2d295d73-a9c9-443b-96fc-f98a1e09c46e',
            'content' => $item->name,
            'selection' => [
                'type' => ConversationSelectionType::OrderItem->value,
                'value' => $item->id,
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.order_item.id', $item->id)
            ->assertJsonPath('data.state', ConversationState::CollectingReason->value)
            ->assertJsonPath('data.messages.1.content', ConversationMessageTemplate::RefundReasonRequested->value)
            ->assertJsonPath('data.available_actions.0.type', ConversationSelectionType::RefundReason->value)
            ->assertJsonPath('data.available_actions.0.value', RefundReason::DamagedItem->value)
            ->assertJsonCount(2, 'data.messages');
    }

    public function test_reason_selection_advances_to_detail_collection_with_a_specific_prompt(): void
    {
        $customer = Customer::factory()->create();
        $item = OrderItem::factory()->for(Order::factory()->for($customer))->create();
        $conversation = RefundConversation::factory()->forOrderItem($item)->create();

        $response = $this->submitMessage($customer, $conversation, [
            'client_message_id' => '4bcfa6f5-43f6-4545-8e2f-ebfac00a03d1',
            'content' => 'Changed mind',
            'selection' => [
                'type' => ConversationSelectionType::RefundReason->value,
                'value' => RefundReason::ChangedMind->value,
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.reason', RefundReason::ChangedMind->value)
            ->assertJsonPath('data.state', ConversationState::CollectingDetails->value)
            ->assertJsonPath('data.available_actions', [])
            ->assertJsonPath('data.messages.1.sender', MessageSender::Assistant->value)
            ->assertJsonPath('data.messages.1.content', ConversationMessageTemplate::ChangedMindDetailsRequested->value)
            ->assertJsonCount(2, 'data.messages');
    }

    public function test_rolls_back_the_customer_message_when_the_selection_is_invalid(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $otherOrder = Order::factory()->for($otherCustomer)->create();
        $conversation = RefundConversation::factory()->for($customer)->create([
            'state' => ConversationState::IdentifyingOrder,
        ]);

        $response = $this->submitMessage($customer, $conversation, [
            'client_message_id' => 'f2248f83-f14a-4d91-8ea2-7f98fc633b1c',
            'content' => $otherOrder->reference,
            'selection' => [
                'type' => ConversationSelectionType::Order->value,
                'value' => $otherOrder->id,
            ],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_CONVERSATION_SELECTION');
        $this->assertDatabaseMissing('conversation_messages', [
            'refund_conversation_id' => $conversation->id,
        ]);
        $this->assertDatabaseHas('refund_conversations', [
            'id' => $conversation->id,
            'order_id' => null,
            'state' => ConversationState::IdentifyingOrder->value,
        ]);
    }

    public function test_replays_the_original_result_for_a_duplicate_uuid_without_repeating_side_effects(): void
    {
        $customer = Customer::factory()->create();
        $originalOrder = Order::factory()->for($customer)->create();
        OrderItem::factory()->for($originalOrder)->create();
        $differentOrder = Order::factory()->for($customer)->create();
        OrderItem::factory()->for($differentOrder)->create();
        $conversation = RefundConversation::factory()->for($customer)->create([
            'state' => ConversationState::IdentifyingOrder,
        ]);
        $clientMessageId = '3fa85f64-5717-4562-b3fc-2c963f66afa6';

        $originalResponse = $this->submitMessage($customer, $conversation, [
            'client_message_id' => $clientMessageId,
            'content' => $originalOrder->reference,
            'selection' => [
                'type' => ConversationSelectionType::Order->value,
                'value' => $originalOrder->id,
            ],
        ]);
        $retryResponse = $this->submitMessage($customer, $conversation, [
            'client_message_id' => $clientMessageId,
            'content' => $differentOrder->reference,
            'selection' => [
                'type' => ConversationSelectionType::Order->value,
                'value' => $differentOrder->id,
            ],
        ]);

        $originalResponse->assertOk();
        $retryResponse
            ->assertOk()
            ->assertExactJson($originalResponse->json());
        $this->assertDatabaseHas('refund_conversations', [
            'id' => $conversation->id,
            'order_id' => $originalOrder->id,
            'state' => ConversationState::IdentifyingItem->value,
        ]);
        $this->assertSame(1, ConversationMessage::query()
            ->where('refund_conversation_id', $conversation->id)
            ->where('sender', MessageSender::Customer->value)
            ->count());
        $this->assertSame(1, ConversationMessage::query()
            ->where('refund_conversation_id', $conversation->id)
            ->where('sender', MessageSender::Assistant->value)
            ->count());
        $this->assertSame(1, $conversation->auditLogs()
            ->where('event', 'conversation.order_identified')
            ->count());
    }

    public function test_allows_the_same_uuid_in_different_owned_conversations(): void
    {
        $customer = Customer::factory()->create();
        $firstConversation = RefundConversation::factory()->for($customer)->create();
        $secondConversation = RefundConversation::factory()->for($customer)->create();
        $clientMessageId = 'c5db89ed-60eb-49ef-8daf-34e1ab265901';

        $this->submitMessage($customer, $firstConversation, [
            'client_message_id' => $clientMessageId,
            'content' => 'First refund request.',
        ])->assertOk();
        $this->submitMessage($customer, $secondConversation, [
            'client_message_id' => $clientMessageId,
            'content' => 'Second refund request.',
        ])->assertOk();

        $this->assertSame(2, ConversationMessage::query()
            ->where('sender', MessageSender::Customer->value)
            ->count());
        $this->assertDatabaseCount('ai_analyses', 2);
        $this->assertDatabaseHas('conversation_messages', [
            'refund_conversation_id' => $firstConversation->id,
            'client_message_id' => $clientMessageId,
        ]);
        $this->assertDatabaseHas('conversation_messages', [
            'refund_conversation_id' => $secondConversation->id,
            'client_message_id' => $clientMessageId,
        ]);
    }

    public function test_returns_404_before_a_matching_uuid_can_expose_another_customers_conversation(): void
    {
        $requestingCustomer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $otherConversation = RefundConversation::factory()->for($otherCustomer)->create();
        $clientMessageId = '333e4567-e89b-42d3-a456-426614174000';
        ConversationMessage::factory()->for($otherConversation)->create([
            'client_message_id' => $clientMessageId,
        ]);

        $response = $this->submitMessage($requestingCustomer, $otherConversation, [
            'client_message_id' => $clientMessageId,
            'content' => 'Retry this message.',
        ]);

        $response
            ->assertNotFound()
            ->assertExactJson([
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'The requested resource was not found.',
                    'details' => [],
                ],
            ]);
        $this->assertDatabaseCount('conversation_messages', 1);
    }

    public function test_returns_409_for_a_new_message_on_a_resolved_conversation(): void
    {
        $customer = Customer::factory()->create();
        $conversation = RefundConversation::factory()->for($customer)->resolved()->create();

        $response = $this->submitMessage($customer, $conversation, [
            'client_message_id' => 'a987fbc9-4bed-4078-b1c2-33f9a12b774e',
            'content' => 'Please reopen this request.',
        ]);

        $response
            ->assertConflict()
            ->assertExactJson([
                'error' => [
                    'code' => 'CONVERSATION_ALREADY_RESOLVED',
                    'message' => 'This refund conversation has already been resolved.',
                    'details' => [],
                ],
            ]);
        $this->assertDatabaseCount('conversation_messages', 0);
    }

    #[DataProvider('recoverableAiFailures')]
    public function test_returns_503_and_retries_without_duplicate_messages_when_ai_analysis_fails(
        string $exceptionClass,
        string $message,
        string $failureType,
    ): void {
        $customer = Customer::factory()->create();
        $conversation = RefundConversation::factory()->for($customer)->create();
        $clientMessageId = '99999999-9999-4999-8999-999999999999';
        $fake = app(FakeRefundConversationAI::class);
        /** @var AIProviderUnavailableException|InvalidAIResponseException $exception */
        $exception = new $exceptionClass;
        $fake->failWith($exception);
        $payload = [
            'client_message_id' => $clientMessageId,
            'content' => 'I need help with a refund.',
        ];

        $failedResponse = $this->submitMessage($customer, $conversation, $payload);

        $failedResponse
            ->assertServiceUnavailable()
            ->assertExactJson([
                'error' => [
                    'code' => 'SERVICE_UNAVAILABLE',
                    'message' => $message,
                    'details' => [],
                ],
            ]);
        $this->assertDatabaseCount('conversation_messages', 0);
        $this->assertDatabaseCount('ai_analyses', 0);
        $this->assertDatabaseCount('refund_requests', 0);
        $failureAudit = $conversation->auditLogs()->where('event', 'ai.analysis.failed')->sole();
        $this->assertSame($clientMessageId, $failureAudit->metadata['client_message_id'] ?? null);
        $this->assertSame($failureType, $failureAudit->metadata['failure_type'] ?? null);

        $fake->useScenario(FakeRefundConversationScenario::MissingInformation);
        $retryResponse = $this->submitMessage($customer, $conversation, $payload);

        $retryResponse->assertOk();
        $this->assertSame(1, ConversationMessage::query()
            ->where('refund_conversation_id', $conversation->id)
            ->where('sender', MessageSender::Customer->value)
            ->count());
        $this->assertSame(1, ConversationMessage::query()
            ->where('refund_conversation_id', $conversation->id)
            ->where('sender', MessageSender::Assistant->value)
            ->count());
        $this->assertSame(1, AiAnalysis::query()
            ->where('refund_conversation_id', $conversation->id)
            ->count());
        $this->assertSame(1, $conversation->auditLogs()
            ->where('event', 'ai.analysis.completed')
            ->count());
    }

    public function test_duplicate_open_selection_resolves_with_a_system_message_and_remains_retryable(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create();
        $item = OrderItem::factory()->for($order)->create();
        $existingConversation = RefundConversation::factory()->forOrderItem($item)->create();
        $conversation = RefundConversation::factory()->for($customer)->create([
            'order_id' => $order->id,
            'state' => ConversationState::IdentifyingItem,
        ]);
        $this->submitMessage($customer, $conversation, [
            'client_message_id' => 'de193a27-8f38-4675-8dcb-cf73bb8bc823',
            'content' => $item->name,
            'selection' => [
                'type' => ConversationSelectionType::OrderItem->value,
                'value' => $item->id,
            ],
        ])->assertOk();
        $openExistingPayload = [
            'client_message_id' => 'b2d1c9ea-6ee3-4c36-954f-59981db965ee',
            'content' => 'Open existing conversation',
            'selection' => [
                'type' => ConversationSelectionType::OpenExistingConversation->value,
                'value' => $existingConversation->id,
            ],
        ];

        $originalResponse = $this->submitMessage($customer, $conversation, $openExistingPayload);
        $retryResponse = $this->submitMessage($customer, $conversation, $openExistingPayload);

        $originalResponse
            ->assertOk()
            ->assertJsonPath('data.status', ConversationStatus::Resolved->value)
            ->assertJsonPath('data.messages.3.sender', MessageSender::System->value)
            ->assertJsonPath(
                'data.messages.3.content',
                ConversationMessageTemplate::DuplicateConversationResolved->value,
            )
            ->assertJsonPath(
                'data.messages.3.metadata.existing_conversation_id',
                $existingConversation->id,
            )
            ->assertJsonCount(4, 'data.messages');
        $retryResponse
            ->assertOk()
            ->assertExactJson($originalResponse->json());
        $this->assertSame(4, $conversation->messages()->count());
        $this->assertSame(1, $conversation->messages()
            ->where('sender', MessageSender::System->value)
            ->count());
        $this->assertDatabaseCount('refund_requests', 0);
    }

    public function test_requires_demo_customer_identity_for_message_submission(): void
    {
        $conversation = RefundConversation::factory()->create();

        $response = $this->postJson(
            "/api/customer/conversations/{$conversation->id}/messages",
            [
                'client_message_id' => '7db4e20f-79d3-49a7-8da8-430109d7850e',
                'content' => 'I need a refund.',
            ],
        );

        $response
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'DEMO_CUSTOMER_ID_REQUIRED');
        $this->assertDatabaseCount('conversation_messages', 0);
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidMessagePayloads(): array
    {
        return [
            'missing UUID' => [
                ['content' => 'I need a refund.'],
                'client_message_id',
                'The client message id field is required.',
            ],
            'malformed UUID' => [
                ['client_message_id' => 'not-a-uuid', 'content' => 'I need a refund.'],
                'client_message_id',
                'The client message id field must be a valid UUID.',
            ],
            'missing content' => [
                ['client_message_id' => '63b23f2a-9cd5-4d27-8bb6-33f67007bb13'],
                'content',
                'The content field is required.',
            ],
            'content over 5000 characters' => [
                [
                    'client_message_id' => 'a9547579-7d9a-4d1d-9676-e0f3fe44f0f5',
                    'content' => str_repeat('a', 5001),
                ],
                'content',
                'The content field must not be greater than 5000 characters.',
            ],
            'unsupported selection type' => [
                [
                    'client_message_id' => '4c0c77ca-8891-4773-87a1-2d505e200e15',
                    'content' => 'Use this selection.',
                    'selection' => ['type' => 'customer', 'value' => 1],
                ],
                'selection.type',
                'The selected selection.type is invalid.',
            ],
            'missing selection value' => [
                [
                    'client_message_id' => 'd9cb2c45-5b6d-42ff-ad57-ef67ae60952d',
                    'content' => 'Use this selection.',
                    'selection' => ['type' => ConversationSelectionType::Order->value],
                ],
                'selection.value',
                'The selection.value field is required when selection is present.',
            ],
        ];
    }

    /**
     * @return array<string, array{class-string<AIProviderUnavailableException|InvalidAIResponseException>, string, string}>
     */
    public static function recoverableAiFailures(): array
    {
        return [
            'provider unavailable' => [
                AIProviderUnavailableException::class,
                'The AI analysis provider is temporarily unavailable.',
                'provider_unavailable',
            ],
            'invalid provider response' => [
                InvalidAIResponseException::class,
                'The AI analysis provider returned an invalid response.',
                'invalid_response',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function submitMessage(
        Customer $customer,
        RefundConversation $conversation,
        array $payload,
    ): TestResponse {
        return $this->withHeader('X-Demo-Customer-Id', (string) $customer->getKey())
            ->postJson("/api/customer/conversations/{$conversation->id}/messages", $payload);
    }
}
