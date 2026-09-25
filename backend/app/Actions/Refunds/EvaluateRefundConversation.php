<?php

namespace App\Actions\Refunds;

use App\Contracts\Refunds\RefundPolicy;
use App\Data\Refunds\RefundPolicyContext;
use App\Enums\AuditActorType;
use App\Enums\AuditEvent;
use App\Enums\ConversationMessageTemplate;
use App\Enums\ConversationState;
use App\Enums\ConversationStatus;
use App\Enums\DecisionSource;
use App\Enums\RefundDecision;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Events\RefundRequestEscalated;
use App\Exceptions\Conversations\ConversationWorkflowException;
use App\Models\AiAnalysis;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\RefundConversation;
use App\Models\RefundRequest;
use App\Services\Conversations\ConversationMessageService;
use App\Services\Conversations\ConversationStateMachine;
use Illuminate\Support\Facades\DB;
use LogicException;

final class EvaluateRefundConversation
{
    public function __construct(
        private readonly RefundPolicy $policy,
        private readonly ConversationStateMachine $stateMachine,
        private readonly ConversationMessageService $messages,
    ) {}

    public function handle(RefundConversation $conversation): RefundRequest
    {
        return DB::transaction(function () use ($conversation): RefundRequest {
            $lockedConversation = RefundConversation::query()
                ->whereKey($conversation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existingRequest = RefundRequest::query()
                ->where('refund_conversation_id', $lockedConversation->id)
                ->lockForUpdate()
                ->first();

            if ($existingRequest !== null) {
                return $existingRequest;
            }

            $this->assertReadyForEvaluation($lockedConversation);

            $order = Order::query()
                ->whereKey($lockedConversation->order_id)
                ->where('customer_id', $lockedConversation->customer_id)
                ->where('status', 'delivered')
                ->whereNotNull('delivered_at')
                ->lockForUpdate()
                ->firstOrFail();
            $orderItem = OrderItem::query()
                ->whereKey($lockedConversation->order_item_id)
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->firstOrFail();
            $reason = $lockedConversation->reason;
            $reasonDetails = $lockedConversation->reason_details;
            $deliveredAt = $order->delivered_at;

            if (
                $reason === null
                || $reason === RefundReason::Unknown
                || ! is_string($reasonDetails)
                || trim($reasonDetails) === ''
                || $deliveredAt === null
            ) {
                throw new LogicException('Only complete refund conversations may be evaluated.');
            }

            $evaluatedAt = now();
            $signals = $this->riskSignals($lockedConversation);
            $policyResult = $this->policy->evaluate(new RefundPolicyContext(
                unitPriceCents: $orderItem->unit_price_cents,
                quantity: $orderItem->quantity,
                finalSale: $orderItem->final_sale,
                deliveredAt: $deliveredAt,
                alreadyRefunded: Refund::query()
                    ->where('order_item_id', $orderItem->id)
                    ->exists(),
                reason: $reason,
                reasonDetails: $reasonDetails,
                conflictingInformation: $signals['conflicting_information'],
                promptInjectionDetected: $signals['prompt_injection_detected'],
                confidence: $signals['confidence'],
                evaluatedAt: $evaluatedAt,
            ));

            $refundRequest = $lockedConversation->refundRequest()->create([
                'customer_id' => $lockedConversation->customer_id,
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'reason' => $reason,
                'reason_details' => $reasonDetails,
                'amount_cents' => $policyResult->amountCents,
                'initial_decision' => $policyResult->decision,
                'decision' => $policyResult->decision,
                'decision_source' => DecisionSource::PolicyEngine,
                'decision_code' => $policyResult->decisionCode,
                'policy_checks' => $policyResult->policyChecks(),
                'reviewed_by' => null,
                'review_note' => null,
                'decided_at' => $evaluatedAt,
            ]);

            $lockedConversation->auditLogs()->create([
                'actor_type' => AuditActorType::System,
                'actor_id' => null,
                'event' => AuditEvent::PolicyEvaluated->value,
                'metadata' => [
                    'refund_request_id' => $refundRequest->id,
                    'decision' => $policyResult->decision->value,
                    'decision_code' => $policyResult->decisionCode->value,
                    'amount_cents' => $policyResult->amountCents,
                    'policy_checks' => $policyResult->policyChecks(),
                    'ai_analysis_count' => $signals['analysis_count'],
                    'confidence' => $signals['confidence'],
                    'prompt_injection_detected' => $signals['prompt_injection_detected'],
                    'conflicting_information' => $signals['conflicting_information'],
                ],
            ]);

            $refundRequest->auditLogs()->create([
                'actor_type' => AuditActorType::System,
                'actor_id' => null,
                'event' => $this->requestAuditEvent($policyResult->decision)->value,
                'metadata' => [
                    'initial_decision' => $policyResult->decision->value,
                    'decision_source' => DecisionSource::PolicyEngine->value,
                    'decision_code' => $policyResult->decisionCode->value,
                    'amount_cents' => $policyResult->amountCents,
                ],
            ]);

            if ($policyResult->decision === RefundDecision::Approved) {
                $refund = $refundRequest->refund()->create([
                    'order_item_id' => $orderItem->id,
                    'amount_cents' => $policyResult->amountCents,
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
                    'actor_type' => AuditActorType::System,
                    'actor_id' => null,
                    'event' => AuditEvent::RefundCreated->value,
                    'metadata' => [
                        'refund_request_id' => $refundRequest->id,
                        'order_item_id' => $orderItem->id,
                        'amount_cents' => $policyResult->amountCents,
                        'status' => RefundStatus::Pending->value,
                    ],
                ]);
            }

            $this->stateMachine->transition($lockedConversation, ConversationState::Resolved);
            $lockedConversation->save();
            $this->messages->assistant(
                $lockedConversation,
                $this->outcomeMessage($policyResult->decision),
            );

            if ($policyResult->decision === RefundDecision::Escalated) {
                RefundRequestEscalated::dispatch($refundRequest->id);
            }

            return $refundRequest->refresh();
        });
    }

    private function assertReadyForEvaluation(RefundConversation $conversation): void
    {
        if ($conversation->status === ConversationStatus::Resolved) {
            throw ConversationWorkflowException::alreadyResolved();
        }

        if ($conversation->state !== ConversationState::Evaluating) {
            throw ConversationWorkflowException::invalidTransition(
                $conversation->state,
                ConversationState::Evaluating,
            );
        }
    }

    /**
     * @return array{analysis_count: int, confidence: int, prompt_injection_detected: bool, conflicting_information: bool}
     */
    private function riskSignals(RefundConversation $conversation): array
    {
        $analyses = AiAnalysis::query()
            ->where('refund_conversation_id', $conversation->id)
            ->orderBy('id')
            ->get(['id', 'confidence', 'prompt_injection_detected', 'conflicting_information']);
        $confidence = 100;
        $promptInjectionDetected = false;
        $conflictingInformation = false;

        foreach ($analyses as $analysis) {
            $confidence = min($confidence, $analysis->confidence);
            $promptInjectionDetected = $promptInjectionDetected || $analysis->prompt_injection_detected;
            $conflictingInformation = $conflictingInformation || $analysis->conflicting_information;
        }

        return [
            'analysis_count' => $analyses->count(),
            'confidence' => $analyses->isEmpty() ? 0 : $confidence,
            'prompt_injection_detected' => $promptInjectionDetected,
            'conflicting_information' => $conflictingInformation,
        ];
    }

    private function requestAuditEvent(RefundDecision $decision): AuditEvent
    {
        return match ($decision) {
            RefundDecision::Approved => AuditEvent::RefundRequestApproved,
            RefundDecision::Denied => AuditEvent::RefundRequestDenied,
            RefundDecision::Escalated => AuditEvent::RefundRequestEscalated,
        };
    }

    private function outcomeMessage(RefundDecision $decision): ConversationMessageTemplate
    {
        return match ($decision) {
            RefundDecision::Approved => ConversationMessageTemplate::RefundApproved,
            RefundDecision::Denied => ConversationMessageTemplate::RefundDenied,
            RefundDecision::Escalated => ConversationMessageTemplate::RefundEscalated,
        };
    }
}
