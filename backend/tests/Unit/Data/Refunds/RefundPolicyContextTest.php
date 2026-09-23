<?php

namespace Tests\Unit\Data\Refunds;

use App\Data\Refunds\RefundPolicyContext;
use App\Enums\RefundReason;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RefundPolicyContextTest extends TestCase
{
    public function test_holds_complete_typed_policy_input(): void
    {
        $deliveredAt = new DateTimeImmutable('2026-09-01 12:00:00');
        $evaluatedAt = new DateTimeImmutable('2026-09-20 12:00:00');

        $context = $this->context([
            'deliveredAt' => $deliveredAt,
            'evaluatedAt' => $evaluatedAt,
        ]);

        $this->assertSame(12999, $context->unitPriceCents);
        $this->assertSame(1, $context->quantity);
        $this->assertFalse($context->finalSale);
        $this->assertSame($deliveredAt->format(DATE_ATOM), $context->deliveredAt->format(DATE_ATOM));
        $this->assertNotSame($deliveredAt, $context->deliveredAt);
        $this->assertFalse($context->alreadyRefunded);
        $this->assertSame(RefundReason::DamagedItem, $context->reason);
        $this->assertSame('Two keys were broken when the package was opened.', $context->reasonDetails);
        $this->assertFalse($context->conflictingInformation);
        $this->assertFalse($context->promptInjectionDetected);
        $this->assertSame(96, $context->confidence);
        $this->assertSame($evaluatedAt->format(DATE_ATOM), $context->evaluatedAt->format(DATE_ATOM));
        $this->assertNotSame($evaluatedAt, $context->evaluatedAt);
    }

    #[DataProvider('invalidContexts')]
    public function test_rejects_incomplete_or_untrusted_policy_input(array $overrides): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->context($overrides);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidContexts(): array
    {
        return [
            'negative authoritative price' => [['unitPriceCents' => -1]],
            'zero quantity' => [['quantity' => 0]],
            'quantity above one' => [['quantity' => 2]],
            'future delivery' => [[
                'deliveredAt' => new DateTimeImmutable('2026-09-21 12:00:00'),
            ]],
            'unknown reason' => [['reason' => RefundReason::Unknown]],
            'damaged item with blank details' => [['reasonDetails' => " \n\t "]],
            'incorrect item with blank details' => [[
                'reason' => RefundReason::IncorrectItem,
                'reasonDetails' => " \n\t ",
            ]],
            'oversized reason details' => [['reasonDetails' => str_repeat('D', 4001)]],
            'negative confidence' => [['confidence' => -1]],
            'confidence above one hundred' => [['confidence' => 101]],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function context(array $overrides = []): RefundPolicyContext
    {
        return new RefundPolicyContext(...array_replace([
            'unitPriceCents' => 12999,
            'quantity' => 1,
            'finalSale' => false,
            'deliveredAt' => new DateTimeImmutable('2026-09-01 12:00:00'),
            'alreadyRefunded' => false,
            'reason' => RefundReason::DamagedItem,
            'reasonDetails' => 'Two keys were broken when the package was opened.',
            'conflictingInformation' => false,
            'promptInjectionDetected' => false,
            'confidence' => 96,
            'evaluatedAt' => new DateTimeImmutable('2026-09-20 12:00:00'),
        ], $overrides));
    }
}
