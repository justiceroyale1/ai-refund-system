<?php

use App\Providers\AIServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\PaymentServiceProvider;
use App\Providers\RefundServiceProvider;

return [
    AIServiceProvider::class,
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    PaymentServiceProvider::class,
    RefundServiceProvider::class,
];
