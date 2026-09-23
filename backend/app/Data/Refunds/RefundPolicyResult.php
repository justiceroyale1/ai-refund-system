<?php

namespace App\Data\Refunds;

use App\Enums\DecisionCode;
use App\Enums\RefundDecision;
use InvalidArgumentException;

final readonly class RefundPolicyResult
{
    /**
     * @var list<RefundPolicyCheck>
     */
    public array $checks;

    /**
     * @param  array<array-key, mixed>  $checks
     */
    public function __construct(
        public RefundDecision $decision,
        public DecisionCode $decisionCode,
        public int $amountCents,
        array $checks,
    ) {
        if ($amountCents < 0) {
            throw new InvalidArgumentException('The refund amount must not be negative.');
        }

        if ($checks === [] || ! array_is_list($checks)) {
            throw new InvalidArgumentException('Refund policy checks must be a non-empty list.');
        }

        foreach ($checks as $check) {
            if (! $check instanceof RefundPolicyCheck) {
                throw new InvalidArgumentException('Refund policy results require typed policy checks.');
            }
        }

        $this->checks = $checks;
    }

    /**
     * @return list<array{code: string, result: string, message: string}>
     */
    public function policyChecks(): array
    {
        return array_map(
            static fn (RefundPolicyCheck $check): array => $check->toArray(),
            $this->checks,
        );
    }
}
