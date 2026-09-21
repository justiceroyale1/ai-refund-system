<?php

namespace App\Enums;

enum RefundDecision: string
{
    case Approved = 'approved';
    case Denied = 'denied';
    case Escalated = 'escalated';
}
