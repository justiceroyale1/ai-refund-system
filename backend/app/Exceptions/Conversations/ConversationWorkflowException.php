<?php

namespace App\Exceptions\Conversations;

use App\Enums\ConversationState;
use App\Enums\Http\ApiErrorCode;
use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

final class ConversationWorkflowException extends RuntimeException implements ShouldntReport
{
    /**
     * @param  array<string, mixed>  $details
     */
    private function __construct(
        private readonly ApiErrorCode $errorCode,
        string $message,
        private readonly array $details,
        private readonly int $status,
    ) {
        parent::__construct($message);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function invalidSelection(string $message, array $details = []): self
    {
        return new self(
            ApiErrorCode::InvalidConversationSelection,
            $message,
            $details,
            422,
        );
    }

    public static function invalidTransition(ConversationState $from, ConversationState $to): self
    {
        return new self(
            ApiErrorCode::InvalidConversationTransition,
            ApiErrorCode::InvalidConversationTransition->defaultMessage(),
            [
                'from' => $from->value,
                'to' => $to->value,
            ],
            409,
        );
    }

    public static function alreadyResolved(): self
    {
        return new self(
            ApiErrorCode::ConversationAlreadyResolved,
            ApiErrorCode::ConversationAlreadyResolved->defaultMessage(),
            [],
            409,
        );
    }

    public function errorCode(): ApiErrorCode
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return $this->details;
    }

    public function status(): int
    {
        return $this->status;
    }
}
