<?php

namespace App\Enums;

enum ConversationSelectionOutcome: string
{
    case Applied = 'applied';
    case DuplicateDetected = 'duplicate_detected';
    case ExistingConversationOpened = 'existing_conversation_opened';
    case AlternateItemRequested = 'alternate_item_requested';
}
