<?php

namespace Tests\Feature\Http;

use App\Http\CurrentCustomer;
use App\Models\Customer;
use App\Models\RefundConversation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DemoCustomerIdentityTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'demo.customer'])
            ->get('/api/testing/current-customer', function (
                Request $request,
                CurrentCustomer $currentCustomer,
            ): array {
                return [
                    'data' => [
                        'context_id' => $currentCustomer->id(),
                        'request_id' => $request->user()?->getKey(),
                        'guard_id' => $request->user(CurrentCustomer::GUARD)?->getKey(),
                    ],
                ];
            });

        Route::middleware(['api', 'demo.customer'])
            ->get('/api/testing/refund-conversations/{conversation}', function (
                string $conversation,
                CurrentCustomer $currentCustomer,
            ): array {
                $ownedConversation = $currentCustomer->findOwnedOrFail(
                    RefundConversation::query(),
                    $conversation,
                );

                return ['data' => ['id' => $ownedConversation->getKey()]];
            });
    }

    public function test_resolves_a_seeded_customer_consistently_across_request_identity_accessors(): void
    {
        $this->seed();
        $customer = Customer::query()->where('email', 'james.munroe@example.test')->sole();

        $response = $this->withDemoCustomer($customer)
            ->getJson('/api/testing/current-customer');

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'context_id' => $customer->id,
                    'request_id' => $customer->id,
                    'guard_id' => $customer->id,
                ],
            ]);
    }

    public function test_returns_401_when_the_demo_customer_header_is_missing(): void
    {
        $response = $this->getJson('/api/testing/current-customer');

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

    #[DataProvider('malformedCustomerIds')]
    public function test_returns_401_when_the_demo_customer_header_is_malformed(string $customerId): void
    {
        $response = $this->withHeader('X-Demo-Customer-Id', $customerId)
            ->getJson('/api/testing/current-customer');

        $response
            ->assertUnauthorized()
            ->assertExactJson([
                'error' => [
                    'code' => 'DEMO_CUSTOMER_ID_INVALID',
                    'message' => 'The X-Demo-Customer-Id header must contain a valid customer ID.',
                    'details' => [],
                ],
            ]);
    }

    public function test_returns_401_when_the_demo_customer_does_not_exist(): void
    {
        $response = $this->withHeader('X-Demo-Customer-Id', '999999')
            ->getJson('/api/testing/current-customer');

        $response
            ->assertUnauthorized()
            ->assertExactJson([
                'error' => [
                    'code' => 'DEMO_CUSTOMER_NOT_FOUND',
                    'message' => 'The selected demo customer could not be found.',
                    'details' => [],
                ],
            ]);
    }

    public function test_resolves_an_owned_resource_through_the_customer_scope(): void
    {
        $customer = Customer::factory()->create();
        $conversation = RefundConversation::factory()->for($customer)->create();

        $response = $this->withDemoCustomer($customer)
            ->getJson("/api/testing/refund-conversations/{$conversation->id}");

        $response
            ->assertOk()
            ->assertExactJson(['data' => ['id' => $conversation->id]]);
    }

    public function test_returns_404_without_exposing_another_customers_resource(): void
    {
        $requestingCustomer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $conversation = RefundConversation::factory()->for($otherCustomer)->create();

        $response = $this->withDemoCustomer($requestingCustomer)
            ->getJson("/api/testing/refund-conversations/{$conversation->id}");

        $response
            ->assertNotFound()
            ->assertExactJson([
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'The requested resource was not found.',
                    'details' => [],
                ],
            ]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedCustomerIds(): array
    {
        return [
            'zero' => ['0'],
            'negative integer' => ['-1'],
            'decimal number' => ['1.5'],
            'non-numeric value' => ['customer-one'],
            'non-canonical leading zero' => ['01'],
            'integer overflow' => ['999999999999999999999999999999999999'],
        ];
    }

    private function withDemoCustomer(Customer $customer): static
    {
        return $this->withHeader('X-Demo-Customer-Id', (string) $customer->getKey());
    }
}
