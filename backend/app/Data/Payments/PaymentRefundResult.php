<?php

namespace App\Data\Payments;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class PaymentRefundResult
{
    private const int MAXIMUM_ERROR_MESSAGE_LENGTH = 2000;

    private const int MAXIMUM_PROCESSOR_REFERENCE_LENGTH = 128;

    private function __construct(
        public bool $success,
        public ?string $processorReference,
        public ?string $errorMessage,
    ) {}

    public static function successful(string $processorReference): self
    {
        if (
            trim($processorReference) === ''
            || Str::length($processorReference) > self::MAXIMUM_PROCESSOR_REFERENCE_LENGTH
        ) {
            throw new InvalidArgumentException('A successful payment refund requires a valid processor reference.');
        }

        return new self(true, $processorReference, null);
    }

    public static function failed(string $errorMessage): self
    {
        if (
            trim($errorMessage) === ''
            || Str::length($errorMessage) > self::MAXIMUM_ERROR_MESSAGE_LENGTH
        ) {
            throw new InvalidArgumentException('A failed payment refund requires a valid error message.');
        }

        return new self(false, null, $errorMessage);
    }
}
