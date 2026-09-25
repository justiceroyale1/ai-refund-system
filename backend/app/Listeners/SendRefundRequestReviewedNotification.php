<?php

namespace App\Listeners;

use App\Enums\RefundDecision;
use App\Events\RefundRequestReviewed;
use App\Models\RefundRequest;
use App\Notifications\Customer\RefundApprovedNotification;
use App\Notifications\Customer\RefundDeniedNotification;
use LogicException;

final class SendRefundRequestReviewedNotification
{
    public function handle(RefundRequestReviewed $event): void
    {
        $refundRequest = RefundRequest::query()
            ->with('customer:id')
            ->findOrFail($event->refundRequestId);

        $notification = match ($refundRequest->decision) {
            RefundDecision::Approved => new RefundApprovedNotification($refundRequest->refund_conversation_id),
            RefundDecision::Denied => new RefundDeniedNotification($refundRequest->refund_conversation_id),
            default => throw new LogicException('Reviewed refund requests must have a final human decision.'),
        };

        $refundRequest->customer->notify($notification);
    }
}
