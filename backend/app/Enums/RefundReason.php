<?php

namespace App\Enums;

enum RefundReason: string
{
    case DamagedItem = 'damaged_item';
    case IncorrectItem = 'incorrect_item';
    case MissingItem = 'missing_item';
    case ChangedMind = 'changed_mind';
    case Other = 'other';
    case Unknown = 'unknown';
}
