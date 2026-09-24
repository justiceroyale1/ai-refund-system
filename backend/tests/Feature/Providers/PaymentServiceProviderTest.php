<?php

namespace Tests\Feature\Providers;

use App\Contracts\Payments\PaymentProcessor;
use App\Exceptions\Payments\UnsupportedPaymentProcessorException;
use App\Services\Payments\SimulatedPaymentProcessor;
use stdClass;
use Tests\TestCase;

class PaymentServiceProviderTest extends TestCase
{
    public function test_resolves_the_configured_simulator_as_a_singleton(): void
    {
        $first = $this->app->make(PaymentProcessor::class);
        $second = $this->app->make(PaymentProcessor::class);

        $this->assertInstanceOf(SimulatedPaymentProcessor::class, $first);
        $this->assertSame($first, $second);
    }

    public function test_exposes_safe_default_simulator_configuration(): void
    {
        $this->assertSame(SimulatedPaymentProcessor::NAME, config('payments.default'));
        $this->assertFalse(config('payments.processors.simulated.force_failure'));
    }

    public function test_injects_forced_failure_when_explicitly_enabled_in_tests(): void
    {
        config()->set('payments.processors.simulated.force_failure', true);

        $processor = $this->app->make(PaymentProcessor::class);
        $result = $processor->refund('pay_order_1042', 12999, 'refund-request-42');

        $this->assertInstanceOf(SimulatedPaymentProcessor::class, $processor);
        $this->assertFalse($result->success);
        $this->assertSame('The simulated payment processor was forced to fail.', $result->errorMessage);
    }

    public function test_rejects_forced_failure_configuration_outside_tests(): void
    {
        config()->set('payments.processors.simulated.force_failure', true);
        $this->app->detectEnvironment(static fn (): string => 'production');

        $this->expectException(UnsupportedPaymentProcessorException::class);

        $this->app->make(PaymentProcessor::class);
    }

    public function test_rejects_a_non_boolean_failure_configuration(): void
    {
        config()->set('payments.processors.simulated.force_failure', 'false');

        $this->expectException(UnsupportedPaymentProcessorException::class);

        $this->app->make(PaymentProcessor::class);
    }

    public function test_rejects_an_unknown_configured_processor_without_exposing_its_name(): void
    {
        config()->set('payments.default', 'sensitive-processor-name');

        try {
            $this->app->make(PaymentProcessor::class);
            $this->fail('An unknown payment processor was resolved.');
        } catch (UnsupportedPaymentProcessorException $exception) {
            $this->assertSame('The configured payment processor is not available.', $exception->getMessage());
            $this->assertStringNotContainsString('sensitive-processor-name', $exception->getMessage());
        }
    }

    public function test_rejects_a_driver_that_does_not_implement_the_contract(): void
    {
        config()->set('payments.default', 'invalid');
        config()->set('payments.processors.invalid.driver', stdClass::class);

        $this->expectException(UnsupportedPaymentProcessorException::class);

        $this->app->make(PaymentProcessor::class);
    }
}
