<?php

namespace App\Enums;

enum AdminNotificationType: string
{
    case ProcessorError = 'processor_error';
    case RefundReviewRequired = 'refund_review_required';
}
