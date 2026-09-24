<?php

namespace App\Actions\Refunds;

use App\Enums\AuditActorType;
use App\Enums\AuditEvent;
use App\Enums\ConversationMessageTemplate;
use App\Enums\DecisionSource;
use App\Enums\RefundDecision;
use App\Enums\RefundStatus;
use App\Events\RefundRequestReviewed;
use App\Exceptions\Refunds\RefundReviewException;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\RefundConversation;
use App\Models\RefundRequest;
use App\Models\User;
use App\Services\Conversations\ConversationMessageService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ReviewRefundRequest
{
    public function __construct(private readonly ConversationMessageService $messages) {}

    public function handle(
        RefundRequest $refundRequest,
        User $reviewer,
        RefundDecision $decision,
        ?string $reviewNote,
    ): RefundRequest {
        if (! in_array($decision, [RefundDecision::Approved, RefundDecision::Denied], true)) {
            throw new InvalidArgumentException('Human review requires an approved or denied decision.');
        }

        return DB::transaction(function () use ($refundRequest, $reviewer, $decision, $reviewNote): RefundRequest {
            $lockedRequest = RefundRequest::query()
                ->whereKey($refundRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertAwaitingReview($lockedRequest);

            OrderItem::query()
                ->whereKey($lockedRequest->order_item_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $decision === RefundDecision::Approved
                && Refund::query()->where('order_item_id', $lockedRequest->order_item_id)->exists()
            ) {
                throw RefundReviewException::notAwaitingReview();
            }

            $decidedAt = now();
            $lockedRequest->decision = $decision;
            $lockedRequest->decision_source = DecisionSource::Human;
            $lockedRequest->reviewed_by = $reviewer->id;
            $lockedRequest->review_note = $reviewNote;
            $lockedRequest->decided_at = $decidedAt;
            $lockedRequest->save();

            $lockedRequest->auditLogs()->create([
                'actor_type' => AuditActorType::User,
                'actor_id' => $reviewer->id,
                'event' => AuditEvent::RefundRequestReviewed,
                'metadata' => [
                    'initial_decision' => RefundDecision::Escalated->value,
                    'decision' => $decision->value,
                    'decision_source' => DecisionSource::Human->value,
                    'decision_code' => $lockedRequest->decision_code->value,
                    'review_note' => $reviewNote,
                    'decided_at' => $decidedAt->toISOString(),
                ],
            ]);

            if ($decision === RefundDecision::Approved) {
                $this->createRefund($lockedRequest, $reviewer);
            }

            $conversation = RefundConversation::query()
                ->whereKey($lockedRequest->refund_conversation_id)
                ->lockForUpdate()
                ->firstOrFail();
            $this->messages->system(
                $conversation,
                $decision === RefundDecision::Approved
                    ? ConversationMessageTemplate::RefundApprovedAfterReview
                    : ConversationMessageTemplate::RefundDeniedAfterReview,
                [
                    'decision' => $decision->value,
                    'decision_source' => DecisionSource::Human->value,
                ],
            );

            RefundRequestReviewed::dispatch($lockedRequest->id);

            return $lockedRequest->refresh();
        });
    }

    private function assertAwaitingReview(RefundRequest $refundRequest): void
    {
        if (
            $refundRequest->initial_decision !== RefundDecision::Escalated
            || $refundRequest->decision !== RefundDecision::Escalated
            || $refundRequest->decision_source !== DecisionSource::PolicyEngine
            || $refundRequest->reviewed_by !== null
        ) {
            throw RefundReviewException::notAwaitingReview();
        }
    }

    private function createRefund(RefundRequest $refundRequest, User $reviewer): Refund
    {
        $refund = $refundRequest->refund()->create([
            'order_item_id' => $refundRequest->order_item_id,
            'amount_cents' => $refundRequest->amount_cents,
            'status' => RefundStatus::Pending,
            'processor' => 'simulated',
            'idempotency_key' => sprintf('refund-request-%d', $refundRequest->id),
            'processor_reference' => null,
            'attempts' => 0,
            'last_error' => null,
            'next_retry_at' => null,
            'processed_at' => null,
        ]);

        $refund->auditLogs()->create([
            'actor_type' => AuditActorType::User,
            'actor_id' => $reviewer->id,
            'event' => AuditEvent::RefundCreated,
            'metadata' => [
                'refund_request_id' => $refundRequest->id,
                'order_item_id' => $refundRequest->order_item_id,
                'amount_cents' => $refundRequest->amount_cents,
                'status' => RefundStatus::Pending->value,
            ],
        ]);

        return $refund;
    }
}
