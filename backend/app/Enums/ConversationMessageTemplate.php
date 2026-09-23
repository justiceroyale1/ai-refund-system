<?php

namespace App\Enums;

enum ConversationMessageTemplate: string
{
    case OrderRequested = 'Which delivered order would you like help with?';
    case OrderItemRequested = 'Which item from this order would you like refunded?';
    case RefundReasonRequested = 'What is the reason for your refund request?';
    case DamagedItemDetailsRequested = 'Please describe the damage.';
    case IncorrectItemDetailsRequested = 'Please describe what you received instead.';
    case MissingItemDetailsRequested = 'Please share any details that may help us understand the missing item.';
    case ChangedMindDetailsRequested = 'Please explain why you changed your mind.';
    case GenericDetailsRequested = 'Please provide more details about your refund request.';
    case DuplicateItemDetected = 'This item already has an active refund conversation. You can open it or choose another item.';
    case AlternateItemRequested = 'Please choose another item from this order.';
    case DuplicateConversationResolved = 'This request was closed because the selected item is already being handled in another conversation.';
}
