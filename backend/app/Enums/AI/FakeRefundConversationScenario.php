<?php

namespace App\Enums\AI;

enum FakeRefundConversationScenario: string
{
    case DamagedItem = 'damaged_item';
    case IncorrectItem = 'incorrect_item';
    case MissingInformation = 'missing_information';
    case PromptInjection = 'prompt_injection';
    case ConflictingInformation = 'conflicting_information';
    case Unavailable = 'unavailable';
}
