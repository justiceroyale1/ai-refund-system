<?php

namespace App\Actions\Conversations;

use App\Data\Conversations\ConversationSelection;
use App\Enums\ConversationStatus;
use App\Enums\MessageSender;
use App\Exceptions\AI\AIProviderException;
use App\Exceptions\Conversations\ConversationWorkflowException;
use App\Models\ConversationMessage;
use App\Models\RefundConversation;
use App\Services\Conversations\ConversationMessageService;
use Illuminate\Support\Facades\DB;

final class SubmitConversationMessage
{
    public function __construct(
        private readonly ApplyConversationSelection $applyConversationSelection,
        private readonly ProcessConversationAnalysis $processConversationAnalysis,
        private readonly ConversationMessageService $messages,
    ) {}

    public function handle(
        RefundConversation $conversation,
        string $clientMessageId,
        string $content,
        ?ConversationSelection $selection,
    ): RefundConversation {
        try {
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

                $customerMessage = $this->messages->customer(
                    $lockedConversation,
                    $clientMessageId,
                    $content,
                    $selection,
                );

                $result = $selection === null
                    ? $this->processConversationAnalysis->handle($lockedConversation, $customerMessage)
                    : $this->applyConversationSelection->handle($lockedConversation, $selection);
                $this->messages->followUp($result);

                return $result->conversation->refresh();
            });
        } catch (AIProviderException $exception) {
            if ($selection === null) {
                $this->processConversationAnalysis->recordFailure(
                    $conversation,
                    $clientMessageId,
                    $exception,
                );
            }

            throw $exception;
        }
    }
}
