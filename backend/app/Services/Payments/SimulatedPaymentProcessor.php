<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentProcessor;
use App\Data\Payments\PaymentRefundResult;
use InvalidArgumentException;

final class SimulatedPaymentProcessor implements PaymentProcessor
{
    public const string NAME = 'simulated';

    private const string FORCED_FAILURE_MESSAGE = 'The simulated payment processor was forced to fail.';

    public function __construct(
        private readonly bool $forceFailure = false,
    ) {}

    public function refund(
        string $paymentReference,
        int $amountInCents,
        string $idempotencyKey,
    ): PaymentRefundResult {
        $this->validateRequest($paymentReference, $amountInCents, $idempotencyKey);

        if ($this->forceFailure) {
            return PaymentRefundResult::failed(self::FORCED_FAILURE_MESSAGE);
        }

        return PaymentRefundResult::successful(sprintf(
            'simulated-refund-%s',
            hash('sha256', $idempotencyKey),
        ));
    }

    private function validateRequest(
        string $paymentReference,
        int $amountInCents,
        string $idempotencyKey,
    ): void {
        if (trim($paymentReference) === '' || mb_strlen($paymentReference) > 128) {
            throw new InvalidArgumentException('The payment reference must be a non-empty string of at most 128 characters.');
        }

        if ($amountInCents < 1) {
            throw new InvalidArgumentException('The refund amount must be greater than zero.');
        }

        if (trim($idempotencyKey) === '' || mb_strlen($idempotencyKey) > 128) {
            throw new InvalidArgumentException('The idempotency key must be a non-empty string of at most 128 characters.');
        }
    }
}
