<?php

namespace App\Enums;

enum CustomerNotificationType: string
{
    case RefundApproved = 'refund_approved';
    case RefundDenied = 'refund_denied';
    case RefundProcessed = 'refund_processed';
}
