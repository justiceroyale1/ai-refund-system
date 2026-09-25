<?php

namespace App\Notifications\Admin;

use App\Enums\AdminNotificationType;
use Illuminate\Notifications\Notification;

final class RefundReviewRequiredNotification extends Notification
{
    public function __construct(public readonly int $refundRequestId) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array{type: string, title: string, message: string, refund_request_id: int, error_summary: null}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => AdminNotificationType::RefundReviewRequired->value,
            'title' => 'Refund request needs review',
            'message' => 'A refund request requires a human decision. Review the case details.',
            'refund_request_id' => $this->refundRequestId,
            'error_summary' => null,
        ];
    }

    public function databaseType(object $notifiable): string
    {
        return AdminNotificationType::RefundReviewRequired->value;
    }

    public function broadcastType(): string
    {
        return AdminNotificationType::RefundReviewRequired->value;
    }
}
