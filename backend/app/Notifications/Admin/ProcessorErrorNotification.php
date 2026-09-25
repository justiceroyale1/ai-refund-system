<?php

namespace App\Notifications\Admin;

use App\Enums\AdminNotificationType;
use Illuminate\Notifications\Notification;

final class ProcessorErrorNotification extends Notification
{
    public const string ERROR_SUMMARY = 'The simulated payment processor could not complete the refund.';

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
     * @return array{type: string, title: string, message: string, refund_request_id: int, error_summary: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => AdminNotificationType::ProcessorError->value,
            'title' => 'Refund processing error',
            'message' => 'A refund could not be processed. Review the case for details.',
            'refund_request_id' => $this->refundRequestId,
            'error_summary' => self::ERROR_SUMMARY,
        ];
    }

    public function databaseType(object $notifiable): string
    {
        return AdminNotificationType::ProcessorError->value;
    }

    public function broadcastType(): string
    {
        return AdminNotificationType::ProcessorError->value;
    }
}
