<?php

namespace Tests\Feature\Http;

use App\Enums\AuditActorType;
use App\Enums\AuditEvent;
use App\Enums\DecisionCode;
use App\Enums\DecisionSource;
use App\Enums\MessageSender;
use App\Enums\RefundDecision;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Models\AiAnalysis;
use App\Models\AuditLog;
use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\RefundConversation;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminRefundRequestDetailControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_401_for_anonymous_requests(): void
    {
        $refundRequest = $this->createRefundRequest();

        $response = $this->getJson("/api/admin/refund-requests/{$refundRequest->id}");

        $response
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_returns_403_for_authenticated_non_admins(): void
    {
        $user = User::factory()->create();
        $refundRequest = $this->createRefundRequest();

        $response = $this->actingAs($user, 'web')
            ->getJson("/api/admin/refund-requests/{$refundRequest->id}");

        $response
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_returns_404_error_contract_for_a_missing_case(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'web')
            ->getJson('/api/admin/refund-requests/999999');

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

    public function test_returns_complete_case_detail_with_latest_analysis_and_processor_diagnostics(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Talia Mercer',
            'email' => 'talia.mercer@example.test',
        ]);
        $refundRequest = $this->createRefundRequest(
            requestAttributes: [
                'reason' => RefundReason::ChangedMind,
                'reason_details' => 'The item no longer fits my workspace.',
                'initial_decision' => RefundDecision::Escalated,
                'decision' => RefundDecision::Approved,
                'decision_source' => DecisionSource::Human,
                'decision_code' => DecisionCode::ChangedMindRequiresReview,
                'reviewed_by' => $admin->id,
                'review_note' => 'Approved as a one-time exception.',
                'decided_at' => '2026-09-23 13:00:00',
            ],
        );
        $conversation = $refundRequest->refundConversation;
        $firstMessage = ConversationMessage::factory()->for($conversation)->create([
            'sender' => MessageSender::Customer,
            'content' => 'I changed my mind about this item.',
            'created_at' => '2026-09-23 10:00:00',
        ]);
        $secondMessage = ConversationMessage::factory()->for($conversation)->assistant()->create([
            'content' => 'Your request has been sent for review.',
            'metadata' => ['decision' => RefundDecision::Escalated->value],
            'created_at' => '2026-09-23 10:01:00',
        ]);
        AiAnalysis::factory()->for($conversation)->for($firstMessage, 'conversationMessage')->create([
            'provider' => 'internal-provider-name',
            'model' => 'internal-model-name',
            'prompt_version' => 'internal-prompt-version',
            'confidence' => 91,
            'extracted_data' => ['reason' => RefundReason::Other->value],
            'raw_response' => 'sensitive-raw-provider-response',
            'created_at' => '2026-09-23 10:00:30',
        ]);
        $latestAnalysis = AiAnalysis::factory()
            ->for($conversation)
            ->for($firstMessage, 'conversationMessage')
            ->create([
                'provider' => 'internal-provider-name',
                'model' => 'internal-model-name',
                'prompt_version' => 'internal-prompt-version',
                'confidence' => 82,
                'prompt_injection_detected' => true,
                'conflicting_information' => false,
                'extracted_data' => [
                    'intent' => 'refund',
                    'reason' => RefundReason::ChangedMind->value,
                ],
                'raw_response' => 'latest-sensitive-raw-provider-response',
                'created_at' => '2026-09-23 10:00:45',
            ]);
        $refund = Refund::factory()->for($refundRequest)->create([
            'order_item_id' => $refundRequest->order_item_id,
            'amount_cents' => $refundRequest->amount_cents,
            'status' => RefundStatus::Processing,
            'processor' => 'simulated',
            'idempotency_key' => 'sensitive-idempotency-key',
            'attempts' => 2,
            'last_error' => 'The simulated processor could not complete the refund.',
        ]);
        AuditLog::factory()->for($conversation, 'subject')->create([
            'event' => AuditEvent::ConversationStarted,
            'created_at' => '2026-09-23 09:59:00',
            'metadata' => ['provider' => 'internal-provider-name'],
        ]);
        AuditLog::factory()->for($refundRequest, 'subject')->create([
            'actor_type' => AuditActorType::User,
            'actor_id' => $admin->id,
            'event' => AuditEvent::RefundRequestReviewed,
            'created_at' => '2026-09-23 13:00:00',
            'metadata' => ['review_note' => 'Approved as a one-time exception.'],
        ]);
        AuditLog::factory()->for($refund, 'subject')->create([
            'event' => AuditEvent::RefundFailed,
            'created_at' => '2026-09-23 13:05:00',
            'metadata' => ['idempotency_key' => 'sensitive-idempotency-key'],
        ]);

        $response = $this->actingAs($admin, 'web')
            ->getJson("/api/admin/refund-requests/{$refundRequest->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $refundRequest->id)
            ->assertJsonPath('data.customer.name', 'Amelia Carter')
            ->assertJsonPath('data.customer.email', 'amelia.carter@example.test')
            ->assertJsonPath('data.order.reference', 'ORD-2042')
            ->assertJsonPath('data.order.status', 'delivered')
            ->assertJsonPath('data.order_item.sku', 'SKU-DETAIL-1')
            ->assertJsonPath('data.order_item.final_sale', false)
            ->assertJsonPath('data.reason', RefundReason::ChangedMind->value)
            ->assertJsonPath('data.reason_details', 'The item no longer fits my workspace.')
            ->assertJsonPath('data.amount_cents', 12999)
            ->assertJsonPath('data.initial_decision', RefundDecision::Escalated->value)
            ->assertJsonPath('data.decision', RefundDecision::Approved->value)
            ->assertJsonPath('data.decision_source', DecisionSource::Human->value)
            ->assertJsonPath('data.reviewer.id', $admin->id)
            ->assertJsonPath('data.review_note', 'Approved as a one-time exception.')
            ->assertJsonCount(2, 'data.conversation.messages')
            ->assertJsonPath('data.conversation.messages.0.id', $firstMessage->id)
            ->assertJsonPath('data.conversation.messages.1.id', $secondMessage->id)
            ->assertJsonPath('data.latest_ai_analysis.id', $latestAnalysis->id)
            ->assertJsonPath('data.latest_ai_analysis.confidence', 82)
            ->assertJsonPath('data.latest_ai_analysis.prompt_injection_detected', true)
            ->assertJsonPath(
                'data.latest_ai_analysis.extracted_data.reason',
                RefundReason::ChangedMind->value,
            )
            ->assertJsonPath('data.refund.status', RefundStatus::Processing->value)
            ->assertJsonPath('data.refund.attempts', 2)
            ->assertJsonPath(
                'data.refund.last_error',
                'The simulated processor could not complete the refund.',
            )
            ->assertJsonCount(3, 'data.audit_timeline')
            ->assertJsonPath('data.audit_timeline.0.subject_type', 'conversation')
            ->assertJsonPath('data.audit_timeline.1.subject_type', 'refund_request')
            ->assertJsonPath('data.audit_timeline.1.actor_type', AuditActorType::User->value)
            ->assertJsonPath('data.audit_timeline.2.subject_type', 'refund')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'customer' => ['id', 'name', 'email'],
                    'order' => ['id', 'reference', 'status', 'ordered_at', 'delivered_at'],
                    'order_item' => ['id', 'sku', 'name', 'quantity', 'unit_price_cents', 'final_sale'],
                    'reason',
                    'reason_details',
                    'amount_cents',
                    'policy_checks',
                    'initial_decision',
                    'decision',
                    'decision_source',
                    'decision_code',
                    'reviewer' => ['id', 'name', 'email'],
                    'review_note',
                    'conversation' => [
                        'id',
                        'state',
                        'status',
                        'messages' => [['id', 'sender', 'content', 'metadata', 'created_at']],
                        'resolved_at',
                        'created_at',
                        'updated_at',
                    ],
                    'latest_ai_analysis' => [
                        'id',
                        'conversation_message_id',
                        'confidence',
                        'prompt_injection_detected',
                        'conflicting_information',
                        'extracted_data',
                        'created_at',
                    ],
                    'refund' => [
                        'id',
                        'amount_cents',
                        'status',
                        'processor',
                        'processor_reference',
                        'attempts',
                        'last_error',
                        'next_retry_at',
                        'processed_at',
                        'created_at',
                        'updated_at',
                    ],
                    'audit_timeline' => [[
                        'id',
                        'actor_type',
                        'actor_id',
                        'subject_type',
                        'subject_id',
                        'event',
                        'created_at',
                    ]],
                    'decided_at',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonMissingPath('data.order.payment_reference')
            ->assertJsonMissingPath('data.conversation.messages.0.client_message_id')
            ->assertJsonMissingPath('data.latest_ai_analysis.provider')
            ->assertJsonMissingPath('data.latest_ai_analysis.model')
            ->assertJsonMissingPath('data.latest_ai_analysis.prompt_version')
            ->assertJsonMissingPath('data.latest_ai_analysis.raw_response')
            ->assertJsonMissingPath('data.refund.idempotency_key')
            ->assertJsonMissingPath('data.audit_timeline.0.metadata');
        $this->assertStringNotContainsString('sensitive-raw-provider-response', $response->getContent());
        $this->assertStringNotContainsString('sensitive-idempotency-key', $response->getContent());
        $this->assertStringNotContainsString('internal-provider-name', $response->getContent());
        $this->assertStringNotContainsString('internal-model-name', $response->getContent());
        $this->assertStringNotContainsString('internal-prompt-version', $response->getContent());
    }

    public function test_returns_null_optional_sections_for_an_escalated_case_without_ai_or_refund(): void
    {
        $admin = User::factory()->admin()->create();
        $refundRequest = $this->createRefundRequest(requestAttributes: [
            'initial_decision' => RefundDecision::Escalated,
            'decision' => RefundDecision::Escalated,
            'decision_code' => DecisionCode::HighValueReviewRequired,
            'reviewed_by' => null,
            'review_note' => null,
        ]);

        $response = $this->actingAs($admin, 'web')
            ->getJson("/api/admin/refund-requests/{$refundRequest->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.decision', RefundDecision::Escalated->value)
            ->assertJsonPath('data.reviewer', null)
            ->assertJsonPath('data.review_note', null)
            ->assertJsonPath('data.latest_ai_analysis', null)
            ->assertJsonPath('data.refund', null)
            ->assertJsonCount(0, 'data.conversation.messages')
            ->assertJsonCount(0, 'data.audit_timeline');
    }

    public function test_loads_a_large_case_graph_with_a_fixed_query_count(): void
    {
        $admin = User::factory()->admin()->create();
        $refundRequest = $this->createRefundRequest(requestAttributes: [
            'reviewed_by' => $admin->id,
        ]);
        $conversation = $refundRequest->refundConversation;
        $messages = ConversationMessage::factory()->count(10)->for($conversation)->create();
        foreach ($messages->take(5) as $message) {
            AiAnalysis::factory()
                ->for($conversation)
                ->for($message, 'conversationMessage')
                ->create();
        }
        $refund = Refund::factory()->for($refundRequest)->create([
            'order_item_id' => $refundRequest->order_item_id,
            'amount_cents' => $refundRequest->amount_cents,
        ]);
        AuditLog::factory()->count(5)->for($conversation, 'subject')->create();
        AuditLog::factory()->count(5)->for($refundRequest, 'subject')->create();
        AuditLog::factory()->count(5)->for($refund, 'subject')->create();
        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $response = $this->actingAs($admin, 'web')
            ->getJson("/api/admin/refund-requests/{$refundRequest->id}");

        $response
            ->assertOk()
            ->assertJsonCount(10, 'data.conversation.messages')
            ->assertJsonCount(15, 'data.audit_timeline');
        $this->assertLessThanOrEqual(12, $queryCount);
    }

    /**
     * @param  array<string, mixed>  $requestAttributes
     */
    private function createRefundRequest(array $requestAttributes = []): RefundRequest
    {
        $customer = Customer::factory()->create([
            'name' => 'Amelia Carter',
            'email' => 'amelia.carter@example.test',
        ]);
        $order = Order::factory()->for($customer)->create([
            'reference' => 'ORD-2042',
            'payment_reference' => 'sensitive-payment-reference',
            'status' => 'delivered',
            'ordered_at' => '2026-09-15 09:00:00',
            'delivered_at' => '2026-09-20 09:00:00',
        ]);
        $orderItem = OrderItem::factory()->for($order)->create([
            'sku' => 'SKU-DETAIL-1',
            'name' => 'Mechanical Keyboard',
            'quantity' => 1,
            'unit_price_cents' => 12999,
            'final_sale' => false,
        ]);
        $conversation = RefundConversation::factory()
            ->forOrderItem($orderItem)
            ->damagedItem()
            ->resolved()
            ->create();

        return RefundRequest::factory()->create(array_merge([
            'refund_conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'amount_cents' => $orderItem->unit_price_cents,
        ], $requestAttributes));
    }
}
