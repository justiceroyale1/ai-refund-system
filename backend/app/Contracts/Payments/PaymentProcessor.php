<?php

namespace App\Contracts\Payments;

use App\Data\Payments\PaymentRefundResult;

interface PaymentProcessor
{
    public function refund(
        string $paymentReference,
        int $amountInCents,
        string $idempotencyKey,
    ): PaymentRefundResult;
}
