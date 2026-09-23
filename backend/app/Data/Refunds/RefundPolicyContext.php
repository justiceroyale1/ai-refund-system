<?php

namespace App\Data\Refunds;

use App\Data\AI\RefundAnalysisConstraints;
use App\Enums\RefundReason;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class RefundPolicyContext
{
    public DateTimeImmutable $deliveredAt;

    public DateTimeImmutable $evaluatedAt;

    public function __construct(
        public int $unitPriceCents,
        public int $quantity,
        public bool $finalSale,
        DateTimeInterface $deliveredAt,
        public bool $alreadyRefunded,
        public RefundReason $reason,
        public string $reasonDetails,
        public bool $conflictingInformation,
        public bool $promptInjectionDetected,
        public int $confidence,
        DateTimeInterface $evaluatedAt,
    ) {
        $this->deliveredAt = DateTimeImmutable::createFromInterface($deliveredAt);
        $this->evaluatedAt = DateTimeImmutable::createFromInterface($evaluatedAt);

        if ($unitPriceCents < 0) {
            throw new InvalidArgumentException('The authoritative order-item price must not be negative.');
        }

        if ($quantity !== 1) {
            throw new InvalidArgumentException('Refund policy evaluation supports quantity-one order items only.');
        }

        if ($this->deliveredAt > $this->evaluatedAt) {
            throw new InvalidArgumentException('The delivery time must not be after the policy evaluation time.');
        }

        if ($reason === RefundReason::Unknown) {
            throw new InvalidArgumentException('A known refund reason is required for policy evaluation.');
        }

        if (
            trim($reasonDetails) === ''
            || Str::length($reasonDetails) > RefundAnalysisConstraints::REASON_DETAILS_MAX_LENGTH
        ) {
            throw new InvalidArgumentException('Meaningful refund reason details are required for policy evaluation.');
        }

        if ($confidence < 0 || $confidence > 100) {
            throw new InvalidArgumentException('AI confidence must be between 0 and 100.');
        }
    }
}
