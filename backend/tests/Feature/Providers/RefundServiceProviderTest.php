<?php

namespace Tests\Feature\Providers;

use App\Contracts\Refunds\RefundPolicy;
use App\Services\Refunds\DefaultRefundPolicy;
use Tests\TestCase;

class RefundServiceProviderTest extends TestCase
{
    public function test_resolves_the_default_refund_policy_as_a_singleton(): void
    {
        $first = $this->app->make(RefundPolicy::class);
        $second = $this->app->make(RefundPolicy::class);

        $this->assertInstanceOf(DefaultRefundPolicy::class, $first);
        $this->assertSame($first, $second);
    }
}
