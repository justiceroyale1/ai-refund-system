<?php

namespace App\Actions\Conversations;

use App\Enums\ConversationState;
use App\Enums\ConversationStatus;
use App\Models\Customer;
use App\Models\RefundConversation;
use Illuminate\Support\Facades\DB;

class StartRefundConversation
{
    public function handle(Customer $customer): RefundConversation
    {
        return DB::transaction(function () use ($customer): RefundConversation {
            $conversation = $customer->refundConversations()->create([
                'state' => ConversationState::Started,
                'status' => ConversationStatus::Active,
            ]);

            $conversation->auditLogs()->create([
                'actor_type' => 'customer',
                'actor_id' => $customer->getKey(),
                'event' => 'conversation.started',
                'metadata' => null,
            ]);

            return $conversation;
        });
    }
}
