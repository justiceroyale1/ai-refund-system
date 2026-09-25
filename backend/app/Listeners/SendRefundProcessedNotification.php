<?php

namespace App\Listeners;

use App\Events\RefundProcessed;
use App\Models\Refund;
use App\Notifications\Customer\RefundProcessedNotification;

final class SendRefundProcessedNotification
{
    public function handle(RefundProcessed $event): void
    {
        $refund = Refund::query()
            ->with('refundRequest.customer:id')
            ->findOrFail($event->refundId);
        $refundRequest = $refund->refundRequest;

        $refundRequest->customer->notify(
            new RefundProcessedNotification($refundRequest->refund_conversation_id),
        );
    }
}
