<?php

namespace Tests\Feature\Http;

use App\Enums\DecisionCode;
use App\Enums\RefundDecision;
use App\Enums\RefundStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\RefundConversation;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminRefundRequestControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_401_for_anonymous_requests(): void
    {
        $response = $this->getJson('/api/admin/refund-requests');

        $response
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_returns_403_for_authenticated_non_admins(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')->getJson('/api/admin/refund-requests');

        $response
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_lists_request_summaries_in_stable_newest_first_order(): void
    {
        $admin = User::factory()->admin()->create();
        $olderRequest = $this->createRefundRequest(
            customerAttributes: [
                'name' => 'Amelia Carter',
                'email' => 'amelia.carter@example.test',
            ],
            orderAttributes: ['reference' => 'ORD-2042'],
            itemAttributes: ['name' => 'Mechanical Keyboard'],
            requestAttributes: [
                'created_at' => '2026-09-20 10:00:00',
                'updated_at' => '2026-09-20 10:00:00',
            ],
        );
        $newerRequest = $this->createRefundRequest(requestAttributes: [
            'created_at' => '2026-09-20 11:00:00',
            'updated_at' => '2026-09-20 11:00:00',
        ]);
        $this->createRefund($newerRequest, RefundStatus::Pending);

        $response = $this->actingAs($admin, 'web')->getJson('/api/admin/refund-requests');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newerRequest->id)
            ->assertJsonPath('data.0.execution_status', RefundStatus::Pending->value)
            ->assertJsonPath('data.1.id', $olderRequest->id)
            ->assertJsonPath('data.1.customer.name', 'Amelia Carter')
            ->assertJsonPath('data.1.customer.email', 'amelia.carter@example.test')
            ->assertJsonPath('data.1.order.reference', 'ORD-2042')
            ->assertJsonPath('data.1.order_item.name', 'Mechanical Keyboard')
            ->assertJsonPath('data.1.execution_status', null)
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'customer' => ['id', 'name', 'email'],
                    'order' => ['id', 'reference'],
                    'order_item' => ['id', 'name'],
                    'reason',
                    'amount_cents',
                    'initial_decision',
                    'decision',
                    'decision_source',
                    'decision_code',
                    'execution_status',
                    'decided_at',
                    'created_at',
                ]],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total'],
            ])
            ->assertJsonMissingPath('data.0.reason_details')
            ->assertJsonMissingPath('data.0.policy_checks')
            ->assertJsonMissingPath('data.0.review_note');
    }

    public function test_paginates_request_summaries_and_preserves_filters_in_links(): void
    {
        $admin = User::factory()->admin()->create();
        RefundRequest::factory()->count(16)->create();

        $response = $this->actingAs($admin, 'web')->getJson(
            '/api/admin/refund-requests?decision=approved&page=2',
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 16)
            ->assertJsonPath('links.prev', fn (mixed $link): bool => is_string($link)
                && str_contains($link, 'decision=approved'));
    }

    #[DataProvider('refundDecisionFilters')]
    public function test_filters_by_current_decision(RefundDecision $decision, RefundDecision $otherDecision): void
    {
        $admin = User::factory()->admin()->create();
        $matchingRequest = $this->createRefundRequest(requestAttributes: $this->decisionAttributes($decision));
        $this->createRefundRequest(requestAttributes: $this->decisionAttributes($otherDecision));

        $response = $this->actingAs($admin, 'web')->getJson(
            '/api/admin/refund-requests?decision='.$decision->value,
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingRequest->id)
            ->assertJsonPath('data.0.decision', $decision->value);
    }

    #[DataProvider('refundStatusFilters')]
    public function test_filters_by_execution_status(RefundStatus $status, RefundStatus $otherStatus): void
    {
        $admin = User::factory()->admin()->create();
        $matchingRequest = $this->createRefundRequest();
        $otherRequest = $this->createRefundRequest();
        $this->createRefund($matchingRequest, $status);
        $this->createRefund($otherRequest, $otherStatus);

        $response = $this->actingAs($admin, 'web')->getJson(
            '/api/admin/refund-requests?execution_status='.$status->value,
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingRequest->id)
            ->assertJsonPath('data.0.execution_status', $status->value);
    }

    #[DataProvider('searchTerms')]
    public function test_searches_customer_name_email_and_order_reference(
        array $customerAttributes,
        array $orderAttributes,
        string $search,
    ): void {
        $admin = User::factory()->admin()->create();
        $matchingRequest = $this->createRefundRequest(
            customerAttributes: $customerAttributes,
            orderAttributes: $orderAttributes,
        );
        $this->createRefundRequest(
            customerAttributes: ['name' => 'Unrelated Customer', 'email' => 'unrelated@example.test'],
            orderAttributes: ['reference' => 'ORD-9999'],
        );

        $response = $this->actingAs($admin, 'web')->getJson(
            '/api/admin/refund-requests?search='.urlencode($search),
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingRequest->id);
    }

    public function test_combines_decision_execution_and_search_filters_with_and_semantics(): void
    {
        $admin = User::factory()->admin()->create();
        $matchingRequest = $this->createRefundRequest(
            customerAttributes: ['name' => 'Target Customer'],
        );
        $wrongCustomer = $this->createRefundRequest();
        $wrongStatus = $this->createRefundRequest(
            customerAttributes: ['name' => 'Target Customer'],
        );
        $this->createRefundRequest(
            customerAttributes: ['name' => 'Target Customer'],
            requestAttributes: $this->decisionAttributes(RefundDecision::Denied),
        );
        $this->createRefund($matchingRequest, RefundStatus::Pending);
        $this->createRefund($wrongCustomer, RefundStatus::Pending);
        $this->createRefund($wrongStatus, RefundStatus::Processed);

        $response = $this->actingAs($admin, 'web')->getJson(
            '/api/admin/refund-requests?decision=approved&execution_status=pending&search=target',
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingRequest->id);
    }

    public function test_treats_like_wildcards_as_literal_search_characters(): void
    {
        $admin = User::factory()->admin()->create();
        $matchingRequest = $this->createRefundRequest(
            customerAttributes: ['name' => 'Literal %_ Customer'],
        );
        $this->createRefundRequest(
            customerAttributes: ['name' => 'Literal Alpha Customer'],
        );

        $response = $this->actingAs($admin, 'web')->getJson(
            '/api/admin/refund-requests?search='.urlencode('%_'),
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingRequest->id);
    }

    public function test_binds_search_input_instead_of_interpolating_it_into_the_query(): void
    {
        $admin = User::factory()->admin()->create();
        $this->createRefundRequest();

        $response = $this->actingAs($admin, 'web')->getJson(
            '/api/admin/refund-requests?search='.urlencode("%' OR 1=1 --"),
        );

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[DataProvider('invalidFilters')]
    public function test_returns_422_for_invalid_filters(string $query, string $field): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'web')->getJson('/api/admin/refund-requests?'.$query);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure([
                'error' => [
                    'details' => [
                        'errors' => [$field],
                    ],
                ],
            ]);
    }

    public function test_eager_loads_the_bounded_list_without_per_record_queries(): void
    {
        $admin = User::factory()->admin()->create();
        $requests = RefundRequest::factory()->count(10)->create();
        foreach ($requests->take(5) as $refundRequest) {
            $this->createRefund($refundRequest, RefundStatus::Pending);
        }

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $response = $this->actingAs($admin, 'web')->getJson('/api/admin/refund-requests');

        $response
            ->assertOk()
            ->assertJsonCount(10, 'data');
        $this->assertLessThanOrEqual(6, $queryCount);
    }

    /**
     * @return array<string, array{RefundDecision, RefundDecision}>
     */
    public static function refundDecisionFilters(): array
    {
        return [
            'approved' => [RefundDecision::Approved, RefundDecision::Denied],
            'denied' => [RefundDecision::Denied, RefundDecision::Escalated],
            'escalated' => [RefundDecision::Escalated, RefundDecision::Approved],
        ];
    }

    /**
     * @return array<string, array{RefundStatus, RefundStatus}>
     */
    public static function refundStatusFilters(): array
    {
        return [
            'pending' => [RefundStatus::Pending, RefundStatus::Processed],
            'processing' => [RefundStatus::Processing, RefundStatus::Pending],
            'processed' => [RefundStatus::Processed, RefundStatus::Failed],
            'failed' => [RefundStatus::Failed, RefundStatus::Processing],
        ];
    }

    /**
     * @return array<string, array{array<string, string>, array<string, string>, string}>
     */
    public static function searchTerms(): array
    {
        return [
            'customer name' => [['name' => 'Amelia Carter'], [], '  AMELIA  '],
            'customer email' => [['email' => 'marcus.bennett@example.test'], [], 'BENNETT@EXAMPLE.TEST'],
            'order reference' => [[], ['reference' => 'ORD-2042'], 'ord-2042'],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidFilters(): array
    {
        return [
            'decision' => ['decision=waiting', 'decision'],
            'execution status' => ['execution_status=queued', 'execution_status'],
            'page zero' => ['page=0', 'page'],
            'non-integer page' => ['page=second', 'page'],
            'search array' => ['search[]=unsafe', 'search'],
            'search over maximum length' => ['search='.str_repeat('a', 256), 'search'],
        ];
    }

    /**
     * @param  array<string, mixed>  $customerAttributes
     * @param  array<string, mixed>  $orderAttributes
     * @param  array<string, mixed>  $itemAttributes
     * @param  array<string, mixed>  $requestAttributes
     */
    private function createRefundRequest(
        array $customerAttributes = [],
        array $orderAttributes = [],
        array $itemAttributes = [],
        array $requestAttributes = [],
    ): RefundRequest {
        $customer = Customer::factory()->create($customerAttributes);
        $order = Order::factory()->for($customer)->create($orderAttributes);
        $orderItem = OrderItem::factory()->for($order)->create($itemAttributes);
        $conversation = RefundConversation::factory()
            ->forOrderItem($orderItem)
            ->damagedItem()
            ->resolved()
            ->create();

        return RefundRequest::factory()->create(array_merge([
            'refund_conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'amount_cents' => $orderItem->unit_price_cents,
        ], $requestAttributes));
    }

    private function createRefund(RefundRequest $refundRequest, RefundStatus $status): Refund
    {
        return Refund::factory()->create([
            'refund_request_id' => $refundRequest->id,
            'order_item_id' => $refundRequest->order_item_id,
            'amount_cents' => $refundRequest->amount_cents,
            'status' => $status,
        ]);
    }

    /**
     * @return array{initial_decision: RefundDecision, decision: RefundDecision, decision_code: DecisionCode}
     */
    private function decisionAttributes(RefundDecision $decision): array
    {
        return [
            'initial_decision' => $decision,
            'decision' => $decision,
            'decision_code' => match ($decision) {
                RefundDecision::Approved => DecisionCode::DamagedItemEligible,
                RefundDecision::Denied => DecisionCode::FinalSaleItem,
                RefundDecision::Escalated => DecisionCode::HighValueReviewRequired,
            },
        ];
    }
}
