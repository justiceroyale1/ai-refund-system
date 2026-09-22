<?php

namespace App\Data\AI;

final class RefundAnalysisConstraints
{
    public const int ORDER_REFERENCE_MAX_LENGTH = 32;

    public const int ORDER_ITEM_TEXT_MAX_LENGTH = 255;

    public const int REASON_DETAILS_MAX_LENGTH = 4000;

    private function __construct() {}
}
