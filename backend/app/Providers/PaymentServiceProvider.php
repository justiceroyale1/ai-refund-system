<?php

namespace App\Providers;

use App\Contracts\Payments\PaymentProcessor;
use App\Exceptions\Payments\UnsupportedPaymentProcessorException;
use App\Services\Payments\SimulatedPaymentProcessor;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(SimulatedPaymentProcessor::class, function (Application $app): SimulatedPaymentProcessor {
            $forceFailure = $app->make(Repository::class)
                ->get('payments.processors.simulated.force_failure');

            if (! is_bool($forceFailure) || ($forceFailure && ! $app->environment('testing'))) {
                throw new UnsupportedPaymentProcessorException;
            }

            return new SimulatedPaymentProcessor($forceFailure);
        });

        $this->app->singleton(PaymentProcessor::class, function (Application $app): PaymentProcessor {
            $config = $app->make(Repository::class);
            $processor = $config->get('payments.default');

            if (! is_string($processor) || trim($processor) === '') {
                throw new UnsupportedPaymentProcessorException;
            }

            $driver = $config->get("payments.processors.{$processor}.driver");

            if (! is_string($driver) || ! is_a($driver, PaymentProcessor::class, true)) {
                throw new UnsupportedPaymentProcessorException;
            }

            $implementation = $app->make($driver);

            if (! $implementation instanceof PaymentProcessor) {
                throw new UnsupportedPaymentProcessorException;
            }

            return $implementation;
        });
    }
}
