<?php

namespace App\Listeners;

use App\Events\RefundProcessingFailed;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\Admin\ProcessorErrorNotification;
use Illuminate\Support\Facades\Notification;

final class SendRefundProcessingFailedNotification
{
    public function handle(RefundProcessingFailed $event): void
    {
        $refund = Refund::query()->findOrFail($event->refundId);
        $admins = User::query()
            ->where('is_admin', true)
            ->get();

        Notification::send(
            $admins,
            new ProcessorErrorNotification($refund->refund_request_id),
        );
    }
}
