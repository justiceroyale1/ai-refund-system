<?php

namespace Tests\Feature\Http;

use App\Models\Customer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CustomerPrivateChannelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authorizes_the_private_channel_for_the_resolved_customer(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->withDemoCustomer($customer)->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-customers.{$customer->id}",
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_returns_403_for_another_customers_private_channel(): void
    {
        $requestingCustomer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $response = $this->withDemoCustomer($requestingCustomer)->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-customers.{$otherCustomer->id}",
        ]);

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

    public function test_returns_401_when_private_channel_authorization_has_no_customer_identity(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-customers.{$customer->id}",
        ]);

        $response
            ->assertUnauthorized()
            ->assertExactJson([
                'error' => [
                    'code' => 'DEMO_CUSTOMER_ID_REQUIRED',
                    'message' => 'The X-Demo-Customer-Id header is required.',
                    'details' => [],
                ],
            ]);
    }

    private function withDemoCustomer(Customer $customer): static
    {
        return $this->withHeader('X-Demo-Customer-Id', (string) $customer->getKey());
    }
}
