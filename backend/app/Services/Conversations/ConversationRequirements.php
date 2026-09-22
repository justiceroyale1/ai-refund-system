<?php

namespace App\Services\Conversations;

use App\Enums\ConversationState;
use App\Enums\ConversationStatus;
use App\Enums\RefundReason;
use App\Models\RefundConversation;

final class ConversationRequirements
{
    public function nextState(RefundConversation $conversation): ConversationState
    {
        $status = $conversation->status;

        if ($status === ConversationStatus::Resolved) {
            return ConversationState::Resolved;
        }

        if ($conversation->order_id === null) {
            return ConversationState::IdentifyingOrder;
        }

        if ($conversation->order_item_id === null) {
            return ConversationState::IdentifyingItem;
        }

        $reason = $conversation->reason;

        if ($reason === null || $reason === RefundReason::Unknown) {
            return ConversationState::CollectingReason;
        }

        if ($reason->requiresDetails() && ! $this->hasMeaningfulDetails($conversation->reason_details)) {
            return ConversationState::CollectingDetails;
        }

        return ConversationState::Evaluating;
    }

    public function hasMeaningfulDetails(mixed $details): bool
    {
        return is_string($details) && trim($details) !== '';
    }
}
