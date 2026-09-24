<?php

use App\Services\Payments\SimulatedPaymentProcessor;

return [
    'default' => SimulatedPaymentProcessor::NAME,

    'processors' => [
        SimulatedPaymentProcessor::NAME => [
            'driver' => SimulatedPaymentProcessor::class,
            'force_failure' => (bool) env('SIMULATED_PAYMENT_FORCE_FAILURE', false),
        ],
    ],
];
