<?php

namespace App\Notifications\Customer;

use App\Enums\CustomerNotificationType;

final class RefundDeniedNotification extends CustomerNotification
{
    protected function type(): CustomerNotificationType
    {
        return CustomerNotificationType::RefundDenied;
    }

    protected function title(): string
    {
        return 'Refund denied';
    }

    protected function message(): string
    {
        return 'Your refund request was denied after review.';
    }
}
