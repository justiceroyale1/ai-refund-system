<?php

namespace Tests\Unit\Data\Payments;

use App\Data\Payments\PaymentRefundResult;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentRefundResultTest extends TestCase
{
    public function test_creates_a_successful_result_with_a_processor_reference(): void
    {
        $result = PaymentRefundResult::successful('simulated-refund-reference');

        $this->assertTrue($result->success);
        $this->assertSame('simulated-refund-reference', $result->processorReference);
        $this->assertNull($result->errorMessage);
    }

    public function test_creates_a_failed_result_with_an_error_message(): void
    {
        $result = PaymentRefundResult::failed('The processor rejected the refund.');

        $this->assertFalse($result->success);
        $this->assertNull($result->processorReference);
        $this->assertSame('The processor rejected the refund.', $result->errorMessage);
    }

    #[DataProvider('invalidSuccessfulResults')]
    public function test_rejects_an_invalid_successful_result(string $processorReference): void
    {
        $this->expectException(InvalidArgumentException::class);

        PaymentRefundResult::successful($processorReference);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidSuccessfulResults(): array
    {
        return [
            'blank' => ['  '],
            'too long' => [str_repeat('r', 129)],
        ];
    }

    #[DataProvider('invalidFailedResults')]
    public function test_rejects_an_invalid_failed_result(string $errorMessage): void
    {
        $this->expectException(InvalidArgumentException::class);

        PaymentRefundResult::failed($errorMessage);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidFailedResults(): array
    {
        return [
            'blank' => [" \n\t "],
            'too long' => [str_repeat('e', 2001)],
        ];
    }
}
