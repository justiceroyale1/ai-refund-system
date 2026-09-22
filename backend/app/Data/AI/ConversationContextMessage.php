<?php

namespace App\Data\AI;

use App\Enums\MessageSender;
use InvalidArgumentException;

final readonly class ConversationContextMessage
{
    public function __construct(
        public MessageSender $sender,
        public string $content,
    ) {
        if (trim($content) === '') {
            throw new InvalidArgumentException('Conversation context messages cannot be blank.');
        }
    }
}
