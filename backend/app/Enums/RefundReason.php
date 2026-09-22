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

    public function requiresDetails(): bool
    {
        return $this !== self::Unknown;
    }

    public function label(): string
    {
        return match ($this) {
            self::DamagedItem => 'Damaged item',
            self::IncorrectItem => 'Incorrect item',
            self::MissingItem => 'Missing item',
            self::ChangedMind => 'Changed mind',
            self::Other => 'Other',
            self::Unknown => 'Unknown',
        };
    }
}
