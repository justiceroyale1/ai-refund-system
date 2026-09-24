<?php

namespace Tests\Feature\Providers;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class HorizonServiceProviderTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_gates_allow_only_administrators(): void
    {
        $admin = User::factory()->admin()->create();
        $nonAdmin = User::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('access-admin'));
        $this->assertTrue(Gate::forUser($admin)->allows('viewHorizon'));
        $this->assertFalse(Gate::forUser($nonAdmin)->allows('access-admin'));
        $this->assertFalse(Gate::forUser($nonAdmin)->allows('viewHorizon'));
    }

    public function test_horizon_returns_403_for_anonymous_requests_even_locally(): void
    {
        $response = $this->get('/horizon');

        $response->assertForbidden();
    }

    public function test_horizon_returns_403_for_authenticated_non_admins(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')->get('/horizon');

        $response->assertForbidden();
    }

    public function test_horizon_is_accessible_to_authenticated_admins(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'web')->get('/horizon');

        $response
            ->assertOk()
            ->assertSee('Horizon');
    }
}
