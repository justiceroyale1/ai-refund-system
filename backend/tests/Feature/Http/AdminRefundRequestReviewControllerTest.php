<?php

namespace Tests\Feature\Http;

use App\Enums\AuditActorType;
use App\Enums\AuditEvent;
use App\Enums\ConversationMessageTemplate;
use App\Enums\DecisionSource;
use App\Enums\MessageSender;
use App\Enums\RefundDecision;
use App\Enums\RefundStatus;
use App\Events\RefundRequestReviewed;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RefundConversation;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminRefundRequestReviewControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_401_for_anonymous_review_requests(): void
    {
        $refundRequest = $this->escalatedRequest();

        $response = $this->postJson("/api/admin/refund-requests/{$refundRequest->id}/review", [
            'decision' => RefundDecision::Approved->value,
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
        $this->assertSame(RefundDecision::Escalated, $refundRequest->refresh()->decision);
    }

    public function test_returns_403_for_authenticated_non_admin_reviewers(): void
    {
        $user = User::factory()->create();
        $refundRequest = $this->escalatedRequest();

        $response = $this->actingAs($user, 'web')
            ->postJson("/api/admin/refund-requests/{$refundRequest->id}/review", [
                'decision' => RefundDecision::Approved->value,
            ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
        $this->assertSame(RefundDecision::Escalated, $refundRequest->refresh()->decision);
    }

    public function test_approval_uses_the_authenticated_reviewer_and_creates_one_pending_refund(): void
    {
        $this->travelTo('2026-09-24 12:00:00');
        $admin = User::factory()->admin()->create();
        $spoofedReviewer = User::factory()->admin()->create();
        $refundRequest = $this->escalatedRequest();
        Event::fake([RefundRequestReviewed::class]);

        $response = $this->actingAs($admin, 'web')
            ->postJson("/api/admin/refund-requests/{$refundRequest->id}/review", [
                'decision' => RefundDecision::Approved->value,
                'review_note' => '  Approved after checking the complete case.  ',
                'reviewed_by' => $spoofedReviewer->id,
                'amount_cents' => 1,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.decision', RefundDecision::Approved->value)
            ->assertJsonPath('data.decision_source', DecisionSource::Human->value)
            ->assertJsonPath('data.reviewer.id', $admin->id)
            ->assertJsonPath('data.review_note', 'Approved after checking the complete case.')
            ->assertJsonPath('data.refund.amount_cents', 65000)
            ->assertJsonPath('data.refund.status', RefundStatus::Pending->value)
            ->assertJsonCount(1, 'data.conversation.messages')
            ->assertJsonCount(2, 'data.audit_timeline');
        $this->assertDatabaseHas('refund_requests', [
            'id' => $refundRequest->id,
            'decision' => RefundDecision::Approved->value,
            'decision_source' => DecisionSource::Human->value,
            'reviewed_by' => $admin->id,
            'review_note' => 'Approved after checking the complete case.',
            'decided_at' => '2026-09-24 12:00:00',
        ]);

        $refund = $refundRequest->refresh()->refund()->sole();
        $this->assertSame($refundRequest->order_item_id, $refund->order_item_id);
        $this->assertSame(65000, $refund->amount_cents);
        $this->assertSame(RefundStatus::Pending, $refund->status);
        $this->assertSame('simulated', $refund->processor);
        $this->assertSame("refund-request-{$refundRequest->id}", $refund->idempotency_key);

        $reviewAudit = $refundRequest->auditLogs()
            ->where('event', AuditEvent::RefundRequestReviewed->value)
            ->sole();
        $this->assertSame(AuditActorType::User, $reviewAudit->actor_type);
        $this->assertSame($admin->id, $reviewAudit->actor_id);
        $this->assertSame(RefundDecision::Approved->value, $reviewAudit->metadata['decision'] ?? null);
        $this->assertSame(
            'Approved after checking the complete case.',
            $reviewAudit->metadata['review_note'] ?? null,
        );

        $refundAudit = $refund->auditLogs()->sole();
        $this->assertSame(AuditEvent::RefundCreated, $refundAudit->event);
        $this->assertSame(AuditActorType::User, $refundAudit->actor_type);
        $this->assertSame($admin->id, $refundAudit->actor_id);

        $message = $refundRequest->refundConversation->messages()->sole();
        $this->assertSame(MessageSender::System, $message->sender);
        $this->assertSame(ConversationMessageTemplate::RefundApprovedAfterReview->value, $message->content);
        $this->assertSame(RefundDecision::Approved->value, $message->metadata['decision'] ?? null);
        $this->assertSame(DecisionSource::Human->value, $message->metadata['decision_source'] ?? null);
        Event::assertDispatched(
            RefundRequestReviewed::class,
            fn (RefundRequestReviewed $event): bool => $event->refundRequestId === $refundRequest->id,
        );
    }

    public function test_denial_without_a_note_creates_no_refund(): void
    {
        $admin = User::factory()->admin()->create();
        $refundRequest = $this->escalatedRequest();
        Event::fake([RefundRequestReviewed::class]);

        $response = $this->actingAs($admin, 'web')
            ->postJson("/api/admin/refund-requests/{$refundRequest->id}/review", [
                'decision' => RefundDecision::Denied->value,
                'review_note' => " \n\t ",
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.decision', RefundDecision::Denied->value)
            ->assertJsonPath('data.decision_source', DecisionSource::Human->value)
            ->assertJsonPath('data.reviewer.id', $admin->id)
            ->assertJsonPath('data.review_note', null)
            ->assertJsonPath('data.refund', null);
        $this->assertDatabaseMissing('refunds', [
            'refund_request_id' => $refundRequest->id,
        ]);
        $message = $refundRequest->refundConversation->messages()->sole();
        $this->assertSame(ConversationMessageTemplate::RefundDeniedAfterReview->value, $message->content);
        $this->assertSame(1, $refundRequest->auditLogs()->count());
        Event::assertDispatchedTimes(RefundRequestReviewed::class, 1);
    }

    public function test_returns_409_for_a_second_review_without_repeating_side_effects(): void
    {
        $firstAdmin = User::factory()->admin()->create();
        $secondAdmin = User::factory()->admin()->create();
        $refundRequest = $this->escalatedRequest();
        Event::fake([RefundRequestReviewed::class]);
        $this->actingAs($firstAdmin, 'web')
            ->postJson("/api/admin/refund-requests/{$refundRequest->id}/review", [
                'decision' => RefundDecision::Denied->value,
            ])
            ->assertOk();

        $response = $this->actingAs($secondAdmin, 'web')
            ->postJson("/api/admin/refund-requests/{$refundRequest->id}/review", [
                'decision' => RefundDecision::Approved->value,
            ]);

        $response
            ->assertConflict()
            ->assertExactJson([
                'error' => [
                    'code' => 'CONFLICT',
                    'message' => 'This refund request is no longer awaiting review.',
                    'details' => [],
                ],
            ]);
        $refundRequest->refresh();
        $this->assertSame(RefundDecision::Denied, $refundRequest->decision);
        $this->assertSame($firstAdmin->id, $refundRequest->reviewed_by);
        $this->assertDatabaseCount('refunds', 0);
        $this->assertSame(1, $refundRequest->auditLogs()->count());
        $this->assertSame(1, $refundRequest->refundConversation->messages()->count());
        Event::assertDispatchedTimes(RefundRequestReviewed::class, 1);
    }

    public function test_returns_409_when_the_request_was_not_escalated(): void
    {
        $admin = User::factory()->admin()->create();
        $refundRequest = RefundRequest::factory()->create();

        $response = $this->actingAs($admin, 'web')
            ->postJson("/api/admin/refund-requests/{$refundRequest->id}/review", [
                'decision' => RefundDecision::Denied->value,
            ]);

        $response
            ->assertConflict()
            ->assertJsonPath('error.code', 'CONFLICT');
        $this->assertSame(RefundDecision::Approved, $refundRequest->refresh()->decision);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('conversation_messages', 0);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidDecisionPayloads')]
    public function test_returns_422_for_an_invalid_review_decision(array $payload, string $message): void
    {
        $admin = User::factory()->admin()->create();
        $refundRequest = $this->escalatedRequest();

        $response = $this->actingAs($admin, 'web')
            ->postJson("/api/admin/refund-requests/{$refundRequest->id}/review", $payload);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.errors.decision.0', $message);
        $this->assertSame(RefundDecision::Escalated, $refundRequest->refresh()->decision);
        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_returns_422_for_an_oversized_review_note(): void
    {
        $admin = User::factory()->admin()->create();
        $refundRequest = $this->escalatedRequest();

        $response = $this->actingAs($admin, 'web')
            ->postJson("/api/admin/refund-requests/{$refundRequest->id}/review", [
                'decision' => RefundDecision::Denied->value,
                'review_note' => str_repeat('N', 4001),
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.details.errors.review_note.0',
                'The review note field must not be greater than 4000 characters.',
            );
        $this->assertSame(RefundDecision::Escalated, $refundRequest->refresh()->decision);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidDecisionPayloads(): array
    {
        return [
            'missing decision' => [[], 'The decision field is required.'],
            'escalated is not a human outcome' => [
                ['decision' => RefundDecision::Escalated->value],
                'The selected decision is invalid.',
            ],
            'imperative approve is not the persisted enum value' => [
                ['decision' => 'approve'],
                'The selected decision is invalid.',
            ],
            'unknown value' => [
                ['decision' => 'refunded'],
                'The selected decision is invalid.',
            ],
        ];
    }

    private function escalatedRequest(): RefundRequest
    {
        $order = Order::factory()->create();
        $orderItem = OrderItem::factory()->for($order)->create([
            'quantity' => 1,
            'unit_price_cents' => 65000,
        ]);
        $conversation = RefundConversation::factory()
            ->forOrderItem($orderItem)
            ->damagedItem()
            ->resolved()
            ->create();

        return RefundRequest::factory()->escalated()->create([
            'refund_conversation_id' => $conversation->id,
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'amount_cents' => 65000,
        ]);
    }
}
