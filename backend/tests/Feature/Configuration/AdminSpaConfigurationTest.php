<?php

namespace Tests\Feature\Configuration;

use Illuminate\Contracts\Http\Kernel;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Tests\TestCase;

class AdminSpaConfigurationTest extends TestCase
{
    public function test_api_group_enables_sanctum_stateful_middleware(): void
    {
        $middlewareGroups = app(Kernel::class)->getMiddlewareGroups();

        $this->assertContains(
            EnsureFrontendRequestsAreStateful::class,
            $middlewareGroups['api'],
        );
    }

    public function test_cross_origin_session_configuration_is_credentialed_and_restricted(): void
    {
        $this->assertSame(['http://localhost:3000'], config('cors.allowed_origins'));
        $this->assertTrue(config('cors.supports_credentials'));
        $this->assertContains('localhost:3000', config('sanctum.stateful'));
        $this->assertContains('127.0.0.1:3000', config('sanctum.stateful'));
        $this->assertSame('refund_system_session', config('session.cookie'));
        $this->assertSame('lax', config('session.same_site'));
        $this->assertFalse((bool) config('session.secure'));
    }

    public function test_preflight_allows_the_configured_frontend_with_credentials(): void
    {
        $response = $this->call('OPTIONS', '/api/admin/login', server: [
            'HTTP_ORIGIN' => 'http://localhost:3000',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,x-xsrf-token',
        ]);

        $response
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->assertHeader('Access-Control-Allow-Credentials', 'true')
            ->assertHeader('Access-Control-Allow-Methods');
    }

    public function test_preflight_does_not_reflect_an_unconfigured_origin(): void
    {
        $response = $this->call('OPTIONS', '/api/admin/login', server: [
            'HTTP_ORIGIN' => 'https://untrusted.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        $response
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
    }
}
