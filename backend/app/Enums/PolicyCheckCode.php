<?php

namespace App\Enums;

enum PolicyCheckCode: string
{
    case AlreadyRefunded = 'ALREADY_REFUNDED';
    case FinalSale = 'FINAL_SALE';
    case RefundWindow = 'REFUND_WINDOW';
    case ConflictingInformation = 'CONFLICTING_INFORMATION';
    case PromptInjection = 'PROMPT_INJECTION';
    case AIConfidence = 'AI_CONFIDENCE';
    case RefundAmount = 'REFUND_AMOUNT';
    case ReasonDetails = 'REASON_DETAILS';
    case RefundReason = 'REFUND_REASON';
}
