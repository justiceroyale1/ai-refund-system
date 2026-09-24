<?php

namespace Tests\Feature\Http;

use App\Enums\DecisionCode;
use App\Enums\RefundDecision;
use App\Enums\RefundStatus;
use App\Models\Refund;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminDashboardControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_401_for_anonymous_requests(): void
    {
        $response = $this->getJson('/api/admin/dashboard');

        $response
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_returns_403_for_authenticated_non_admins(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')->getJson('/api/admin/dashboard');

        $response
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_returns_zero_for_every_metric_when_no_requests_exist(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'web')->getJson('/api/admin/dashboard');

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'approved_request_count' => 0,
                    'denied_request_count' => 0,
                    'escalated_request_count' => 0,
                    'pending_refund_count' => 0,
                    'failed_refund_count' => 0,
                ],
            ]);
    }

    public function test_returns_current_decision_and_execution_metrics_in_two_bounded_queries(): void
    {
        $admin = User::factory()->admin()->create();
        $pendingRequest = $this->createRefundRequest(RefundDecision::Approved);
        $failedRequest = $this->createRefundRequest(RefundDecision::Approved);
        $processingRequest = $this->createRefundRequest(RefundDecision::Approved);
        $processedRequest = $this->createRefundRequest(RefundDecision::Approved);
        $this->createRefundRequest(RefundDecision::Denied);
        $this->createRefundRequest(RefundDecision::Escalated);
        $this->createRefundRequest(RefundDecision::Escalated);

        $this->createRefund($pendingRequest, RefundStatus::Pending);
        $this->createRefund($failedRequest, RefundStatus::Failed);
        $this->createRefund($processingRequest, RefundStatus::Processing);
        $this->createRefund($processedRequest, RefundStatus::Processed);

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $response = $this->actingAs($admin, 'web')->getJson('/api/admin/dashboard');

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'approved_request_count' => 4,
                    'denied_request_count' => 1,
                    'escalated_request_count' => 2,
                    'pending_refund_count' => 1,
                    'failed_refund_count' => 1,
                ],
            ]);
        $this->assertLessThanOrEqual(2, $queryCount);
    }

    private function createRefundRequest(RefundDecision $decision): RefundRequest
    {
        $decisionCode = match ($decision) {
            RefundDecision::Approved => DecisionCode::DamagedItemEligible,
            RefundDecision::Denied => DecisionCode::FinalSaleItem,
            RefundDecision::Escalated => DecisionCode::HighValueReviewRequired,
        };

        return RefundRequest::factory()->create([
            'initial_decision' => $decision,
            'decision' => $decision,
            'decision_code' => $decisionCode,
        ]);
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
}
