<?php

namespace App\Enums;

enum DecisionCode: string
{
    case DamagedItemEligible = 'DAMAGED_ITEM_ELIGIBLE';
    case IncorrectItemEligible = 'INCORRECT_ITEM_ELIGIBLE';
    case FinalSaleItem = 'FINAL_SALE_ITEM';
    case RefundWindowExpired = 'REFUND_WINDOW_EXPIRED';
    case AlreadyRefunded = 'ALREADY_REFUNDED';
    case HighValueReviewRequired = 'HIGH_VALUE_REVIEW_REQUIRED';
    case ChangedMindRequiresReview = 'CHANGED_MIND_REQUIRES_REVIEW';
    case MissingItemRequiresReview = 'MISSING_ITEM_REQUIRES_REVIEW';
    case ConflictingInformation = 'CONFLICTING_INFORMATION';
    case PromptInjectionDetected = 'PROMPT_INJECTION_DETECTED';
    case LowConfidenceReviewRequired = 'LOW_CONFIDENCE_REVIEW_REQUIRED';
    case OtherReasonRequiresReview = 'OTHER_REASON_REQUIRES_REVIEW';
}
