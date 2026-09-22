<?php

namespace App\Actions\Conversations;

use App\Data\Conversations\ConversationSelection;
use App\Enums\ConversationStatus;
use App\Enums\MessageSender;
use App\Models\ConversationMessage;
use App\Models\RefundConversation;
use App\Services\Conversations\ConversationMessageService;
use App\Services\Conversations\ConversationWorkflowException;
use Illuminate\Support\Facades\DB;

final class SubmitConversationMessage
{
    public function __construct(
        private readonly ApplyConversationSelection $applyConversationSelection,
        private readonly ConversationMessageService $messages,
    ) {}

    public function handle(
        RefundConversation $conversation,
        string $clientMessageId,
        string $content,
        ?ConversationSelection $selection,
    ): RefundConversation {
        return DB::transaction(function () use (
            $conversation,
            $clientMessageId,
            $content,
            $selection,
        ): RefundConversation {
            $lockedConversation = RefundConversation::query()
                ->whereKey($conversation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $existingMessage = ConversationMessage::query()
                ->where('refund_conversation_id', $lockedConversation->getKey())
                ->where('sender', MessageSender::Customer->value)
                ->where('client_message_id', $clientMessageId)
                ->first();

            if ($existingMessage !== null) {
                return $lockedConversation->refresh();
            }

            if ($lockedConversation->status === ConversationStatus::Resolved) {
                throw ConversationWorkflowException::alreadyResolved();
            }

            $this->messages->customer(
                $lockedConversation,
                $clientMessageId,
                $content,
                $selection,
            );

            if ($selection === null) {
                return $lockedConversation->refresh();
            }

            $selectionResult = $this->applyConversationSelection->handle(
                $lockedConversation,
                $selection,
            );
            $this->messages->followUp($selectionResult);

            return $selectionResult->conversation->refresh();
        });
    }
}
