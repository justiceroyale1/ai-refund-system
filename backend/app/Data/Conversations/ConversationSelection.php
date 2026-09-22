<?php

namespace App\Data\Conversations;

use App\Enums\ConversationSelectionType;
use App\Enums\RefundReason;
use App\Services\Conversations\ConversationWorkflowException;

final readonly class ConversationSelection
{
    private function __construct(
        public ConversationSelectionType $type,
        public int|string $value,
    ) {}

    public static function fromUntrusted(mixed $type, mixed $value): self
    {
        if (! is_string($type)) {
            throw ConversationWorkflowException::invalidSelection(
                'The selection type must be a supported string value.',
                ['field' => 'selection.type'],
            );
        }

        $selectionType = ConversationSelectionType::tryFrom($type);

        if ($selectionType === null) {
            throw ConversationWorkflowException::invalidSelection(
                'The selection type is not supported.',
                ['field' => 'selection.type', 'value' => $type],
            );
        }

        return match ($selectionType) {
            ConversationSelectionType::RefundReason => self::refundReason($value),
            default => new self($selectionType, self::positiveInteger($value)),
        };
    }

    private static function refundReason(mixed $value): self
    {
        $reason = is_string($value) ? RefundReason::tryFrom($value) : null;

        if ($reason === null || $reason === RefundReason::Unknown) {
            throw ConversationWorkflowException::invalidSelection(
                'The selected refund reason is not supported.',
                ['field' => 'selection.value'],
            );
        }

        return new self(ConversationSelectionType::RefundReason, $reason->value);
    }

    private static function positiveInteger(mixed $value): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (! is_string($value) || ! preg_match('/^[1-9][0-9]*$/', $value)) {
            throw ConversationWorkflowException::invalidSelection(
                'The selection value must be a positive integer identifier.',
                ['field' => 'selection.value'],
            );
        }

        $identifier = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if ($identifier === false) {
            throw ConversationWorkflowException::invalidSelection(
                'The selection value must be a positive integer identifier.',
                ['field' => 'selection.value'],
            );
        }

        return $identifier;
    }
}
