<?php

namespace App\Services\Conversations;

use App\Enums\ConversationState;
use App\Enums\ConversationStatus;
use App\Exceptions\Conversations\ConversationWorkflowException;
use App\Models\RefundConversation;

final class ConversationStateMachine
{
    /**
     * @var array<string, list<ConversationState>>
     */
    private const array TRANSITIONS = [
        ConversationState::Started->value => [ConversationState::IdentifyingOrder],
        ConversationState::IdentifyingOrder->value => [ConversationState::IdentifyingItem],
        ConversationState::IdentifyingItem->value => [ConversationState::CollectingReason],
        ConversationState::CollectingReason->value => [
            ConversationState::CollectingDetails,
            ConversationState::Evaluating,
        ],
        ConversationState::CollectingDetails->value => [ConversationState::Evaluating],
        ConversationState::Evaluating->value => [ConversationState::Resolved],
        ConversationState::Resolved->value => [],
    ];

    public function canTransition(ConversationState $from, ConversationState $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from->value], true);
    }

    public function transition(RefundConversation $conversation, ConversationState $to): void
    {
        $from = $conversation->state;

        if ($this->status($conversation) === ConversationStatus::Resolved) {
            throw ConversationWorkflowException::alreadyResolved();
        }

        if (! $this->canTransition($from, $to)) {
            throw ConversationWorkflowException::invalidTransition($from, $to);
        }

        $conversation->setAttribute('state', $to);

        if ($to === ConversationState::Resolved) {
            $conversation->setAttribute('status', ConversationStatus::Resolved);
            $conversation->setAttribute('resolved_at', now());
        }
    }

    public function advanceTo(RefundConversation $conversation, ConversationState $to): void
    {
        $from = $conversation->state;

        if ($from === $to) {
            return;
        }

        if ($to === ConversationState::Resolved) {
            throw ConversationWorkflowException::invalidTransition($from, $to);
        }

        $path = $this->path($from, $to);

        if ($path === null) {
            throw ConversationWorkflowException::invalidTransition($from, $to);
        }

        foreach ($path as $next) {
            $this->transition($conversation, $next);
        }
    }

    public function resolveAsDuplicate(RefundConversation $conversation): void
    {
        if ($this->status($conversation) === ConversationStatus::Resolved) {
            throw ConversationWorkflowException::alreadyResolved();
        }

        $from = $conversation->state;

        if ($from !== ConversationState::IdentifyingItem) {
            throw ConversationWorkflowException::invalidTransition($from, ConversationState::Resolved);
        }

        $conversation->setAttribute('state', ConversationState::Resolved);
        $conversation->setAttribute('status', ConversationStatus::Resolved);
        $conversation->setAttribute('resolved_at', now());
    }

    private function status(RefundConversation $conversation): ConversationStatus
    {
        return $conversation->status;
    }

    /**
     * @param  array<string, true>  $visited
     * @return list<ConversationState>|null
     */
    private function path(
        ConversationState $from,
        ConversationState $to,
        array $visited = [],
    ): ?array {
        $visited[$from->value] = true;
        $transitions = self::TRANSITIONS[$from->value];

        if (in_array($to, $transitions, true)) {
            return [$to];
        }

        foreach ($transitions as $next) {
            if (isset($visited[$next->value])) {
                continue;
            }

            $remainingPath = $this->path($next, $to, $visited);

            if ($remainingPath !== null) {
                return [$next, ...$remainingPath];
            }
        }

        return null;
    }
}
