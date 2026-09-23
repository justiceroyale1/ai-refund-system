<?php

namespace App\Data\Refunds;

use App\Enums\PolicyCheckCode;
use App\Enums\PolicyCheckResult;
use InvalidArgumentException;

final readonly class RefundPolicyCheck
{
    public function __construct(
        public PolicyCheckCode $code,
        public PolicyCheckResult $result,
        public string $message,
    ) {
        if (trim($message) === '') {
            throw new InvalidArgumentException('A refund policy check message must not be blank.');
        }
    }

    /**
     * @return array{code: string, result: string, message: string}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code->value,
            'result' => $this->result->value,
            'message' => $this->message,
        ];
    }
}
