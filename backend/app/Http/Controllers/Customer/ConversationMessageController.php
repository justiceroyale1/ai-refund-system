<?php

namespace App\Http\Controllers\Customer;

use App\Actions\Conversations\SubmitConversationMessage;
use App\Http\Controllers\Controller;
use App\Http\CurrentCustomer;
use App\Http\Requests\StoreConversationMessageRequest;
use App\Http\Resources\RefundConversationResource;
use App\Models\RefundConversation;

class ConversationMessageController extends Controller
{
    public function store(
        StoreConversationMessageRequest $request,
        string $conversation,
        CurrentCustomer $currentCustomer,
        SubmitConversationMessage $submitConversationMessage,
    ): RefundConversationResource {
        $ownedConversation = $currentCustomer->findOwnedOrFail(
            RefundConversation::query(),
            $conversation,
        );

        $updatedConversation = $submitConversationMessage->handle(
            $ownedConversation,
            $request->clientMessageId(),
            $request->content(),
            $request->selection(),
        );

        $updatedConversation->load([
            'order:id,reference',
            'orderItem:id,name',
            'refundRequest:id,refund_conversation_id,decision',
            'latestMessage',
            'messages',
        ]);

        return new RefundConversationResource($updatedConversation);
    }
}
