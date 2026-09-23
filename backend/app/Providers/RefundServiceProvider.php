<?php

namespace App\Providers;

use App\Contracts\Refunds\RefundPolicy;
use App\Services\Refunds\DefaultRefundPolicy;
use Illuminate\Support\ServiceProvider;

class RefundServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(RefundPolicy::class, DefaultRefundPolicy::class);
    }
}
