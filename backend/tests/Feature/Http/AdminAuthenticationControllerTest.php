<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdminAuthenticationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_csrf_cookie_endpoint_supports_the_configured_frontend_origin(): void
    {
        $response = $this->withHeader('Origin', 'http://localhost:3000')
            ->get('/sanctum/csrf-cookie');

        $response
            ->assertNoContent()
            ->assertCookie('XSRF-TOKEN')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_returns_422_when_login_fields_are_missing(): void
    {
        $response = $this->postJson('/api/admin/login');

        $response
            ->assertUnprocessable()
            ->assertExactJson([
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'The given data was invalid.',
                    'details' => [
                        'errors' => [
                            'email' => ['The email field is required.'],
                            'password' => ['The password field is required.'],
                        ],
                    ],
                ],
            ]);
    }

    public function test_returns_422_and_remains_guest_when_credentials_are_invalid(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@example.test',
            'password' => 'correct-password',
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'admin@example.test',
            'password' => 'incorrect-password',
        ]);

        $response
            ->assertUnprocessable()
            ->assertExactJson([
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'The given data was invalid.',
                    'details' => [
                        'errors' => [
                            'email' => ['The provided credentials do not match our records.'],
                        ],
                    ],
                ],
            ]);
        $this->assertGuest('web');
    }

    public function test_returns_403_and_clears_the_session_when_a_non_admin_logs_in(): void
    {
        User::factory()->create([
            'email' => 'customer-support@example.test',
            'password' => 'password',
        ]);
        $this->withSession(['session_marker' => 'must-be-cleared']);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'customer-support@example.test',
            'password' => 'password',
        ]);

        $response
            ->assertForbidden()
            ->assertExactJson([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You are not authorized to perform this action.',
                    'details' => [],
                ],
            ])
            ->assertSessionMissing('session_marker');
        $this->assertGuest('web');
    }

    public function test_seeded_admin_credentials_authenticate_and_regenerate_the_session_id(): void
    {
        $this->seed();
        $this->withSession(['session_marker' => 'preserved']);
        $originalSessionId = session()->getId();

        $response = $this->postJson('/api/admin/login', [
            'email' => 'talia.mercer@example.test',
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Talia Mercer')
            ->assertJsonPath('data.email', 'talia.mercer@example.test')
            ->assertJsonStructure(['data' => ['id', 'name', 'email']])
            ->assertSessionHas('session_marker', 'preserved');
        $this->assertNotSame($originalSessionId, session()->getId());
        $this->assertAuthenticated('web');
    }

    public function test_current_admin_returns_401_for_anonymous_requests(): void
    {
        $response = $this->getJson('/api/admin/me');

        $response
            ->assertUnauthorized()
            ->assertExactJson([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentication is required.',
                    'details' => [],
                ],
            ]);
    }

    public function test_current_admin_returns_403_for_authenticated_non_admins(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')->getJson('/api/admin/me');

        $response
            ->assertForbidden()
            ->assertExactJson([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You are not authorized to perform this action.',
                    'details' => [],
                ],
            ]);
    }

    public function test_current_admin_returns_the_authenticated_admin(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Talia Mercer',
            'email' => 'talia.mercer@example.test',
        ]);

        $response = $this->actingAs($admin, 'web')->getJson('/api/admin/me');

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $admin->id,
                    'name' => 'Talia Mercer',
                    'email' => 'talia.mercer@example.test',
                ],
            ]);
    }

    public function test_logout_clears_authentication_and_rotates_session_state(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'web')->withSession(['session_marker' => 'must-be-cleared']);
        $originalSessionId = session()->getId();
        $originalCsrfToken = session()->token();

        $response = $this->postJson('/api/admin/logout');

        $response
            ->assertNoContent()
            ->assertSessionMissing('session_marker');
        $this->assertGuest('web');
        $this->assertNotSame($originalSessionId, session()->getId());
        $this->assertNotSame($originalCsrfToken, session()->token());
    }
}
