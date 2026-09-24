<?php

namespace App\Services\Refunds;

use App\Contracts\Payments\PaymentProcessor;
use App\Data\Payments\PaymentRefundResult;
use App\Enums\AuditActorType;
use App\Enums\AuditEvent;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\Refund;
use App\Models\RefundRequest;
use Illuminate\Support\Facades\DB;

final class RefundProcessor
{
    public function __construct(
        private readonly PaymentProcessor $paymentProcessor,
    ) {}

    public function process(int $refundId): ?PaymentRefundResult
    {
        $execution = $this->claim($refundId);

        if ($execution === null) {
            return null;
        }

        return $this->paymentProcessor->refund(
            $execution['payment_reference'],
            $execution['amount_cents'],
            $execution['idempotency_key'],
        );
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
}
