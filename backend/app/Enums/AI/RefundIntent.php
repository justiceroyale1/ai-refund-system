<?php

namespace App\Enums\AI;

enum RefundIntent: string
{
    case Refund = 'refund';
    case Unknown = 'unknown';
}
