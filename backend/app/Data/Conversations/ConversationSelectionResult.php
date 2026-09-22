<?php

namespace App\Data\Conversations;

use App\Enums\ConversationSelectionOutcome;
use App\Models\RefundConversation;

final readonly class ConversationSelectionResult
{
    /**
     * @param  list<array{type: string, value: int|string, label: string}>  $actions
     */
    private function __construct(
        public RefundConversation $conversation,
        public ConversationSelectionOutcome $outcome,
        public array $actions,
        public ?int $existingConversationId,
    ) {}

    /**
     * @param  list<array{type: string, value: int|string, label: string}>  $actions
     */
    public static function applied(RefundConversation $conversation, array $actions): self
    {
        return new self(
            $conversation,
            ConversationSelectionOutcome::Applied,
            $actions,
            null,
        );
    }

    /**
     * @param  list<array{type: string, value: int|string, label: string}>  $actions
     */
    public static function duplicateDetected(
        RefundConversation $conversation,
        int $existingConversationId,
        array $actions,
    ): self {
        return new self(
            $conversation,
            ConversationSelectionOutcome::DuplicateDetected,
            $actions,
            $existingConversationId,
        );
    }

    public static function existingConversationOpened(
        RefundConversation $conversation,
        int $existingConversationId,
    ): self {
        return new self(
            $conversation,
            ConversationSelectionOutcome::ExistingConversationOpened,
            [],
            $existingConversationId,
        );
    }

    /**
     * @param  list<array{type: string, value: int|string, label: string}>  $actions
     */
    public static function alternateItemRequested(RefundConversation $conversation, array $actions): self
    {
        return new self(
            $conversation,
            ConversationSelectionOutcome::AlternateItemRequested,
            $actions,
            null,
        );
    }
}
