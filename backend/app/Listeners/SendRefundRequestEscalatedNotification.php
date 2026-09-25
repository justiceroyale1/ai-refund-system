<?php

namespace App\Listeners;

use App\Events\RefundRequestEscalated;
use App\Models\RefundRequest;
use App\Models\User;
use App\Notifications\Admin\RefundReviewRequiredNotification;
use Illuminate\Support\Facades\Notification;

final class SendRefundRequestEscalatedNotification
{
    public function handle(RefundRequestEscalated $event): void
    {
        $refundRequest = RefundRequest::query()->findOrFail($event->refundRequestId);
        $admins = User::query()
            ->where('is_admin', true)
            ->get();

        Notification::send(
            $admins,
            new RefundReviewRequiredNotification($refundRequest->id),
        );
    }
}
