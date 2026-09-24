<?php

namespace App\Services\Refunds;

use App\Contracts\Payments\PaymentProcessor;
use App\Data\Payments\PaymentRefundResult;
use App\Enums\AuditActorType;
use App\Enums\AuditEvent;
use App\Enums\ConversationMessageTemplate;
use App\Enums\RefundStatus;
use App\Events\RefundProcessed;
use App\Events\RefundProcessingFailed;
use App\Models\Order;
use App\Models\Refund;
use App\Models\RefundConversation;
use App\Models\RefundRequest;
use App\Services\Conversations\ConversationMessageService;
use Illuminate\Support\Facades\DB;

final class RefundProcessor
{
    public function __construct(
        private readonly PaymentProcessor $paymentProcessor,
        private readonly ConversationMessageService $messages,
    ) {}

    public function process(int $refundId): ?PaymentRefundResult
    {
        $execution = $this->claim($refundId);

        if ($execution === null) {
            return null;
        }

        $result = $this->paymentProcessor->refund(
            $execution['payment_reference'],
            $execution['amount_cents'],
            $execution['idempotency_key'],
        );

        $this->persistOutcome($refundId, $result);

        return $result;
    }

    /**
     * @return array{payment_reference: string, amount_cents: int, idempotency_key: string}|null
     */
    private function claim(int $refundId): ?array
    {
        return DB::transaction(function () use ($refundId): ?array {
            $refund = Refund::query()
                ->whereKey($refundId)
                ->eligibleForDispatch()
                ->lockForUpdate()
                ->first();

            if ($refund === null) {
                return null;
            }

            $refundRequest = RefundRequest::query()
                ->whereKey($refund->refund_request_id)
                ->lockForUpdate()
                ->firstOrFail();
            $order = Order::query()
                ->whereKey($refundRequest->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $refund->status = RefundStatus::Processing;
            $refund->save();
            $refund->auditLogs()->create([
                'actor_type' => AuditActorType::System,
                'actor_id' => null,
                'event' => AuditEvent::RefundProcessing,
                'metadata' => [
                    'refund_request_id' => $refundRequest->id,
                    'status' => RefundStatus::Processing->value,
                    'processor' => $refund->processor,
                ],
            ]);

            return [
                'payment_reference' => $order->payment_reference,
                'amount_cents' => $refund->amount_cents,
                'idempotency_key' => $refund->idempotency_key,
            ];
        });
    }

    private function persistOutcome(int $refundId, PaymentRefundResult $result): void
    {
        DB::transaction(function () use ($refundId, $result): void {
            $refund = Refund::query()
                ->whereKey($refundId)
                ->where('status', RefundStatus::Processing->value)
                ->lockForUpdate()
                ->firstOrFail();
            $refundRequest = RefundRequest::query()
                ->whereKey($refund->refund_request_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($result->success) {
                $this->persistSuccessfulOutcome($refund, $refundRequest, $result);

                RefundProcessed::dispatch($refund->id);

                return;
            }

            $this->persistFailedOutcome($refund, $refundRequest, $result);

            RefundProcessingFailed::dispatch($refund->id);
        });
    }

    private function persistSuccessfulOutcome(
        Refund $refund,
        RefundRequest $refundRequest,
        PaymentRefundResult $result,
    ): void {
        $processedAt = now();
        $refund->status = RefundStatus::Processed;
        $refund->processor_reference = $result->processorReference;
        $refund->attempts = $refund->attempts + 1;
        $refund->last_error = null;
        $refund->next_retry_at = null;
        $refund->processed_at = $processedAt;
        $refund->save();
        $refund->auditLogs()->create([
            'actor_type' => AuditActorType::System,
            'actor_id' => null,
            'event' => AuditEvent::RefundProcessed,
            'metadata' => [
                'refund_request_id' => $refundRequest->id,
                'status' => RefundStatus::Processed->value,
                'processor' => $refund->processor,
                'processor_reference' => $result->processorReference,
                'attempts' => $refund->attempts,
                'processed_at' => $processedAt->toISOString(),
            ],
        ]);

        $conversation = RefundConversation::query()
            ->whereKey($refundRequest->refund_conversation_id)
            ->lockForUpdate()
            ->firstOrFail();
        $this->messages->system(
            $conversation,
            ConversationMessageTemplate::RefundProcessed,
            ['refund_status' => RefundStatus::Processed->value],
        );
    }

    private function persistFailedOutcome(
        Refund $refund,
        RefundRequest $refundRequest,
        PaymentRefundResult $result,
    ): void {
        $refund->attempts = $refund->attempts + 1;
        $refund->last_error = $result->errorMessage;
        $refund->next_retry_at = null;
        $refund->save();
        $refund->auditLogs()->create([
            'actor_type' => AuditActorType::System,
            'actor_id' => null,
            'event' => AuditEvent::RefundFailed,
            'metadata' => [
                'refund_request_id' => $refundRequest->id,
                'status' => RefundStatus::Processing->value,
                'processor' => $refund->processor,
                'attempts' => $refund->attempts,
                'error' => $result->errorMessage,
            ],
        ]);
    }
}
