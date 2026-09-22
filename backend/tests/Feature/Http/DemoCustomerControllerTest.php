<?php

namespace Tests\Feature\Http;

use App\Models\Customer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DemoCustomerControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_demo_customers_in_name_order(): void
    {
        $zoe = Customer::factory()->create([
            'name' => 'Zoe Adams',
            'email' => 'zoe.adams@example.test',
        ]);
        $amira = Customer::factory()->create([
            'name' => 'Amira Bello',
            'email' => 'amira.bello@example.test',
        ]);

        $response = $this->getJson('/api/demo/customers');

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    [
                        'id' => $amira->id,
                        'name' => 'Amira Bello',
                        'email' => 'amira.bello@example.test',
                    ],
                    [
                        'id' => $zoe->id,
                        'name' => 'Zoe Adams',
                        'email' => 'zoe.adams@example.test',
                    ],
                ],
            ]);
    }

    public function test_returns_all_seeded_customers_for_the_demo_switcher(): void
    {
        $this->seed();

        $response = $this->getJson('/api/demo/customers');

        $response
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonFragment([
                'name' => 'James Munroe',
                'email' => 'james.munroe@example.test',
            ]);
    }
}
