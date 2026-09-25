<?php

namespace App\Notifications\Customer;

use App\Enums\CustomerNotificationType;

final class RefundProcessedNotification extends CustomerNotification
{
    protected function type(): CustomerNotificationType
    {
        return CustomerNotificationType::RefundProcessed;
    }

    protected function title(): string
    {
        return 'Refund processed';
    }

    protected function message(): string
    {
        return 'Your refund has been processed successfully.';
    }
}
