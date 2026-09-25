<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdminPrivateChannelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authorizes_an_admins_own_private_notification_channel(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin, 'web')->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-admins.{$admin->id}",
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_returns_403_for_another_admins_private_notification_channel(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $otherAdmin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin, 'web')->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-admins.{$otherAdmin->id}",
        ])->assertForbidden();
    }

    public function test_returns_403_for_a_non_admins_private_notification_channel(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user, 'web')->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-admins.{$user->id}",
        ])->assertForbidden();
    }

    public function test_returns_401_without_admin_or_customer_identity(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-admins.{$admin->id}",
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'DEMO_CUSTOMER_ID_REQUIRED');
    }
}
