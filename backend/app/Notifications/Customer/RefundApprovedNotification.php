<?php

namespace App\Notifications\Customer;

use App\Enums\CustomerNotificationType;

final class RefundApprovedNotification extends CustomerNotification
{
    protected function type(): CustomerNotificationType
    {
        return CustomerNotificationType::RefundApproved;
    }

    protected function title(): string
    {
        return 'Refund approved';
    }

    protected function message(): string
    {
        return 'Your refund request was approved after review.';
    }
}
