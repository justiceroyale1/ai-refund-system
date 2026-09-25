<?php

namespace Tests\Feature\Configuration;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Tests\TestCase;

class CustomerApiConfigurationTest extends TestCase
{
    public function test_stateful_spa_origin_reaches_customer_authentication_without_csrf(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
        ])->postJson('/api/customer/conversations');

        $response->assertUnauthorized();
    }

    public function test_customer_api_routes_do_not_apply_stateful_sanctum_middleware(): void
    {
        $router = app(Router::class);
        $route = $router->getRoutes()->getByName('customer.conversations.store');

        $this->assertInstanceOf(Route::class, $route);
        $this->assertContains(
            EnsureFrontendRequestsAreStateful::class,
            $route->excludedMiddleware(),
        );
    }

    public function test_broadcast_authorization_remains_stateful_and_csrf_protected(): void
    {
        $router = app(Router::class);
        $route = collect($router->getRoutes()->getRoutes())
            ->first(fn (Route $candidate): bool => $candidate->uri() === 'api/broadcasting/auth'
                && in_array('POST', $candidate->methods(), true));

        $this->assertInstanceOf(Route::class, $route);
        $this->assertContains('api', $route->gatherMiddleware());
        $this->assertNotContains(
            EnsureFrontendRequestsAreStateful::class,
            $route->excludedMiddleware(),
        );
        $this->assertContains(
            EnsureFrontendRequestsAreStateful::class,
            app(Kernel::class)->getMiddlewareGroups()['api'],
        );
    }
}
