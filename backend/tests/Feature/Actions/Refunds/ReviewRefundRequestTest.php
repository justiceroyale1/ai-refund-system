<?php

namespace Tests\Feature\Actions\Refunds;

use App\Actions\Refunds\ReviewRefundRequest;
use App\Enums\AuditEvent;
use App\Enums\DecisionSource;
use App\Enums\RefundDecision;
use App\Events\RefundRequestReviewed;
use App\Exceptions\Refunds\RefundReviewException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\RefundConversation;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class ReviewRefundRequestTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_rejects_a_request_that_was_not_escalated(): void
    {
        $admin = User::factory()->admin()->create();
        $refundRequest = RefundRequest::factory()->create();

        try {
            app(ReviewRefundRequest::class)->handle(
                $refundRequest,
                $admin,
                RefundDecision::Denied,
                null,
            );
            $this->fail('A non-escalated request was reviewed.');
        } catch (RefundReviewException $exception) {
            $this->assertSame('This refund request is no longer awaiting review.', $exception->getMessage());
        }

        $this->assertSame(RefundDecision::Approved, $refundRequest->refresh()->decision);
        $this->assertNull($refundRequest->reviewed_by);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('conversation_messages', 0);
    }

    public function test_second_review_returns_conflict_without_duplicate_side_effects(): void
    {
        $firstAdmin = User::factory()->admin()->create();
        $secondAdmin = User::factory()->admin()->create();
        $refundRequest = $this->escalatedRequest();
        Event::fake([RefundRequestReviewed::class]);
        $review = app(ReviewRefundRequest::class);
        $review->handle($refundRequest, $firstAdmin, RefundDecision::Denied, 'First decision.');

        try {
            $review->handle($refundRequest, $secondAdmin, RefundDecision::Approved, 'Second decision.');
            $this->fail('A second review was accepted.');
        } catch (RefundReviewException $exception) {
            $this->assertSame(409, $exception->status());
        }

        $refundRequest->refresh();
        $this->assertSame(RefundDecision::Denied, $refundRequest->decision);
        $this->assertSame(DecisionSource::Human, $refundRequest->decision_source);
        $this->assertSame($firstAdmin->id, $refundRequest->reviewed_by);
        $this->assertSame('First decision.', $refundRequest->review_note);
        $this->assertDatabaseCount('refunds', 0);
        $this->assertSame(1, $refundRequest->auditLogs()
            ->where('event', AuditEvent::RefundRequestReviewed->value)
            ->count());
        $this->assertSame(1, $refundRequest->refundConversation->messages()->count());
        Event::assertDispatchedTimes(RefundRequestReviewed::class, 1);
    }

    public function test_approval_returns_conflict_when_the_item_already_has_a_refund(): void
    {
        $admin = User::factory()->admin()->create();
        $refundRequest = $this->escalatedRequest();
        $existingRequest = RefundRequest::factory()->create([
            'order_item_id' => $refundRequest->order_item_id,
            'customer_id' => $refundRequest->customer_id,
            'order_id' => $refundRequest->order_id,
        ]);
        $existingRefund = Refund::factory()->for($existingRequest)->create([
            'order_item_id' => $refundRequest->order_item_id,
        ]);

        try {
            app(ReviewRefundRequest::class)->handle(
                $refundRequest,
                $admin,
                RefundDecision::Approved,
                null,
            );
            $this->fail('A duplicate item refund was created.');
        } catch (RefundReviewException $exception) {
            $this->assertSame(409, $exception->status());
        }

        $this->assertSame(RefundDecision::Escalated, $refundRequest->refresh()->decision);
        $this->assertSame(1, Refund::query()->where('order_item_id', $refundRequest->order_item_id)->count());
        $this->assertModelExists($existingRefund);
        $this->assertSame(0, $refundRequest->auditLogs()->count());
        $this->assertSame(0, $refundRequest->refundConversation->messages()->count());
    }

    public function test_rolls_back_review_refund_audits_and_event_when_the_message_fails(): void
    {
        $admin = User::factory()->admin()->create();
        $refundRequest = $this->escalatedRequest();
        Event::fake([RefundRequestReviewed::class]);
        Event::listen(QueryExecuted::class, static function (QueryExecuted $query): void {
            if (str_contains($query->sql, 'insert into "conversation_messages"')) {
                throw new RuntimeException('Simulated review message failure.');
            }
        });

        try {
            app(ReviewRefundRequest::class)->handle(
                $refundRequest,
                $admin,
                RefundDecision::Approved,
                'Approve after review.',
            );
            $this->fail('The message persistence failure was not raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated review message failure.', $exception->getMessage());
        }

        $refundRequest->refresh();
        $this->assertSame(RefundDecision::Escalated, $refundRequest->decision);
        $this->assertSame(DecisionSource::PolicyEngine, $refundRequest->decision_source);
        $this->assertNull($refundRequest->reviewed_by);
        $this->assertNull($refundRequest->review_note);
        $this->assertDatabaseCount('refunds', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('conversation_messages', 0);
        Event::assertNotDispatched(RefundRequestReviewed::class);
    }

    private function escalatedRequest(): RefundRequest
    {
        $order = Order::factory()->create();
        $orderItem = OrderItem::factory()->for($order)->create();
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
            'amount_cents' => $orderItem->unit_price_cents,
        ]);
    }
}
