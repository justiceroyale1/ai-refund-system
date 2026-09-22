<?php

namespace App\Enums;

enum ConversationSelectionType: string
{
    case Order = 'order';
    case OrderItem = 'order_item';
    case RefundReason = 'refund_reason';
    case OpenExistingConversation = 'open_existing_conversation';
    case ChooseAnotherItem = 'choose_another_item';
}
