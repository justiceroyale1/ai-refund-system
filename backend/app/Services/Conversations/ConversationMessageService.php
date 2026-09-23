<?php

namespace App\Services\Conversations;

use App\Data\Conversations\ConversationSelection;
use App\Data\Conversations\ConversationSelectionResult;
use App\Enums\ConversationMessageTemplate;
use App\Enums\ConversationSelectionOutcome;
use App\Enums\ConversationState;
use App\Enums\MessageSender;
use App\Enums\RefundReason;
use App\Models\ConversationMessage;
use App\Models\RefundConversation;

final class ConversationMessageService
{
    public function customer(
        RefundConversation $conversation,
        string $clientMessageId,
        string $content,
        ?ConversationSelection $selection,
    ): ConversationMessage {
        return $conversation->messages()->create([
            'client_message_id' => $clientMessageId,
            'sender' => MessageSender::Customer,
            'content' => $content,
            'metadata' => $selection === null ? null : [
                'selection' => [
                    'type' => $selection->type->value,
                    'value' => $selection->value,
                ],
            ],
        ]);
    }

    /**
     * @param  list<array{type: string, value: int|string, label: string}>  $actions
     */
    public function assistant(
        RefundConversation $conversation,
        string|ConversationMessageTemplate $content,
        array $actions = [],
    ): ConversationMessage {
        return $conversation->messages()->create([
            'sender' => MessageSender::Assistant,
            'content' => $this->content($content),
            'metadata' => $actions === [] ? null : ['actions' => $actions],
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function system(
        RefundConversation $conversation,
        string|ConversationMessageTemplate $content,
        ?array $metadata = null,
    ): ConversationMessage {
        return $conversation->messages()->create([
            'sender' => MessageSender::System,
            'content' => $this->content($content),
            'metadata' => $metadata,
        ]);
    }

    public function followUp(ConversationSelectionResult $result): ?ConversationMessage
    {
        if ($result->outcome !== ConversationSelectionOutcome::Applied) {
            return null;
        }

        $template = match ($result->conversation->state) {
            ConversationState::IdentifyingOrder => ConversationMessageTemplate::OrderRequested,
            ConversationState::IdentifyingItem => ConversationMessageTemplate::OrderItemRequested,
            ConversationState::CollectingReason => ConversationMessageTemplate::RefundReasonRequested,
            ConversationState::CollectingDetails => $this->detailsTemplate($result->conversation->reason),
            default => null,
        };

        if ($template === null) {
            return null;
        }

        return $this->assistant($result->conversation, $template, $result->actions);
    }

    private function detailsTemplate(?RefundReason $reason): ConversationMessageTemplate
    {
        return match ($reason) {
            RefundReason::DamagedItem => ConversationMessageTemplate::DamagedItemDetailsRequested,
            RefundReason::IncorrectItem => ConversationMessageTemplate::IncorrectItemDetailsRequested,
            RefundReason::MissingItem => ConversationMessageTemplate::MissingItemDetailsRequested,
            RefundReason::ChangedMind => ConversationMessageTemplate::ChangedMindDetailsRequested,
            RefundReason::Other, RefundReason::Unknown, null => ConversationMessageTemplate::GenericDetailsRequested,
        };
    }

    private function content(string|ConversationMessageTemplate $content): string
    {
        return $content instanceof ConversationMessageTemplate ? $content->value : $content;
    }
}
