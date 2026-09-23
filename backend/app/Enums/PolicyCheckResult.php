<?php

namespace App\Enums;

enum PolicyCheckResult: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case ReviewRequired = 'review_required';
}
