<?php

namespace App\Data\AI;

use App\Enums\ConversationState;
use App\Enums\RefundReason;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class ConversationContext
{
    /**
     * @var list<ConversationContextMessage>
     */
    public array $messages;

    /**
     * @param  array<array-key, mixed>  $messages
     */
    public function __construct(
        public ConversationState $state,
        public ?string $orderReference = null,
        public ?string $orderItemName = null,
        public ?RefundReason $reason = null,
        public ?string $reasonDetails = null,
        array $messages = [],
    ) {
        $this->assertOptionalText(
            $orderReference,
            RefundAnalysisConstraints::ORDER_REFERENCE_MAX_LENGTH,
            'order reference',
        );
        $this->assertOptionalText(
            $orderItemName,
            RefundAnalysisConstraints::ORDER_ITEM_TEXT_MAX_LENGTH,
            'order item name',
        );
        $this->assertOptionalText(
            $reasonDetails,
            RefundAnalysisConstraints::REASON_DETAILS_MAX_LENGTH,
            'reason details',
        );

        if (! array_is_list($messages)) {
            throw new InvalidArgumentException('Conversation context messages must be a list.');
        }

        foreach ($messages as $message) {
            if (! $message instanceof ConversationContextMessage) {
                throw new InvalidArgumentException('Conversation context messages must be typed message objects.');
            }
        }

        $this->messages = $messages;
    }

    private function assertOptionalText(?string $value, int $maximumLength, string $field): void
    {
        if ($value === null) {
            return;
        }

        if (trim($value) === '' || Str::length($value) > $maximumLength) {
            throw new InvalidArgumentException("The conversation context {$field} is invalid.");
        }
    }
}
