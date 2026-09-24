<?php

namespace Tests\Unit\Services\Payments;

use App\Contracts\Payments\PaymentProcessor;
use App\Services\Payments\SimulatedPaymentProcessor;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SimulatedPaymentProcessorTest extends TestCase
{
    public function test_implements_the_payment_processor_contract(): void
    {
        $processor = new SimulatedPaymentProcessor;

        $this->assertInstanceOf(PaymentProcessor::class, $processor);
    }

    public function test_returns_the_same_success_across_processor_instances(): void
    {
        $first = (new SimulatedPaymentProcessor)->refund('pay_order_1042', 12999, 'refund-request-42');
        $second = (new SimulatedPaymentProcessor)->refund('pay_order_1042', 12999, 'refund-request-42');

        $this->assertTrue($first->success);
        $this->assertSame(
            'simulated-refund-92067ee4ec5d6ce6b30977878a6ea031d62150a5236c32d6ae721cc291971612',
            $first->processorReference,
        );
        $this->assertNull($first->errorMessage);
        $this->assertTrue($second->success);
        $this->assertSame($first->processorReference, $second->processorReference);
        $this->assertSame($first->errorMessage, $second->errorMessage);
    }

    #[DataProvider('alternatePaymentInputs')]
    public function test_same_idempotency_key_cannot_produce_a_different_execution_reference(
        string $paymentReference,
        int $amountInCents,
    ): void {
        $first = (new SimulatedPaymentProcessor)->refund('pay_order_1042', 12999, 'refund-request-42');
        $second = (new SimulatedPaymentProcessor)->refund(
            $paymentReference,
            $amountInCents,
            'refund-request-42',
        );

        $this->assertTrue($second->success);
        $this->assertSame($first->processorReference, $second->processorReference);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function alternatePaymentInputs(): array
    {
        return [
            'different payment reference' => ['pay_order_2048', 12999],
            'different amount' => ['pay_order_1042', 13000],
        ];
    }

    public function test_different_idempotency_keys_produce_different_execution_references(): void
    {
        $first = (new SimulatedPaymentProcessor)->refund('pay_order_1042', 12999, 'refund-request-42');
        $second = (new SimulatedPaymentProcessor)->refund('pay_order_1042', 12999, 'refund-request-43');

        $this->assertSame(
            'simulated-refund-92067ee4ec5d6ce6b30977878a6ea031d62150a5236c32d6ae721cc291971612',
            $first->processorReference,
        );
        $this->assertSame(
            'simulated-refund-cef488a0a72a4cda4169a408160153facfe845cf72976747637fb968ff38fd6e',
            $second->processorReference,
        );
        $this->assertNotSame($first->processorReference, $second->processorReference);
    }

    public function test_forced_failure_is_available_only_through_explicit_injection(): void
    {
        $normal = (new SimulatedPaymentProcessor)->refund('pay_order_1042', 12999, 'refund-request-42');
        $forced = (new SimulatedPaymentProcessor(forceFailure: true))
            ->refund('pay_order_1042', 12999, 'refund-request-42');

        $this->assertTrue($normal->success);
        $this->assertFalse($forced->success);
        $this->assertNull($forced->processorReference);
        $this->assertSame('The simulated payment processor was forced to fail.', $forced->errorMessage);
    }

    #[DataProvider('invalidRefundRequests')]
    public function test_rejects_invalid_refund_inputs(
        string $paymentReference,
        int $amountInCents,
        string $idempotencyKey,
    ): void {
        $this->expectException(InvalidArgumentException::class);

        (new SimulatedPaymentProcessor)->refund($paymentReference, $amountInCents, $idempotencyKey);
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function invalidRefundRequests(): array
    {
        return [
            'blank payment reference' => [' ', 12999, 'refund-request-42'],
            'payment reference too long' => [str_repeat('p', 129), 12999, 'refund-request-42'],
            'zero amount' => ['pay_order_1042', 0, 'refund-request-42'],
            'negative amount' => ['pay_order_1042', -1, 'refund-request-42'],
            'blank idempotency key' => ['pay_order_1042', 12999, "\n"],
            'idempotency key too long' => ['pay_order_1042', 12999, str_repeat('i', 129)],
        ];
    }
}
