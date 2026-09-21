<?php

namespace App\Enums;

enum ConversationState: string
{
    case Started = 'started';
    case IdentifyingOrder = 'identifying_order';
    case IdentifyingItem = 'identifying_item';
    case CollectingReason = 'collecting_reason';
    case CollectingDetails = 'collecting_details';
    case Evaluating = 'evaluating';
    case Resolved = 'resolved';
}
