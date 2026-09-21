<?php

namespace Tests\Unit\Refund;

use App\Enums\DecisionCode;
use App\Enums\DecisionSource;
use App\Enums\RefundDecision;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Models\AuditLog;
use App\Models\Refund;
use App\Models\RefundRequest;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RefundModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_models_expose_the_complete_refund_relationship_graph(): void
    {
        $reviewer = User::factory()->admin()->create();
        $request = RefundRequest::factory()->create(['reviewed_by' => $reviewer->id]);
        $refund = Refund::factory()->for($request, 'refundRequest')->create();
        $requestAudit = AuditLog::factory()->for($request, 'subject')->create();
        $refundAudit = AuditLog::factory()->for($refund, 'subject')->create();

        $this->assertTrue($request->refundConversation->refundRequest->is($request));
        $this->assertTrue($request->customer->refundRequests()->whereKey($request)->exists());
        $this->assertTrue($request->order->refundRequests()->whereKey($request)->exists());
        $this->assertTrue($request->orderItem->refundRequests()->whereKey($request)->exists());
        $this->assertTrue($request->reviewer->is($reviewer));
        $this->assertTrue($reviewer->reviewedRefundRequests()->whereKey($request)->exists());
        $this->assertTrue($request->refund->is($refund));
        $this->assertTrue($refund->refundRequest->is($request));
        $this->assertTrue($refund->orderItem->is($request->orderItem));
        $this->assertTrue($request->orderItem->refund->is($refund));
        $this->assertTrue($request->auditLogs()->whereKey($requestAudit)->exists());
        $this->assertTrue($refund->auditLogs()->whereKey($refundAudit)->exists());
        $this->assertTrue($requestAudit->subject->is($request));
        $this->assertTrue($refundAudit->subject->is($refund));
    }

    public function test_models_cast_domain_values_and_execution_diagnostics_to_safe_types(): void
    {
        $reviewer = User::factory()->admin()->create();
        $policyChecks = [
            [
                'code' => 'HIGH_VALUE',
                'result' => 'review_required',
                'message' => 'Refund amount requires manual review.',
            ],
        ];
        $request = RefundRequest::factory()->create([
            'reason' => RefundReason::ChangedMind,
            'amount_cents' => 50001,
            'initial_decision' => RefundDecision::Escalated,
            'decision' => RefundDecision::Approved,
            'decision_source' => DecisionSource::Human,
            'decision_code' => DecisionCode::HighValueReviewRequired,
            'policy_checks' => $policyChecks,
            'reviewed_by' => $reviewer->id,
            'review_note' => 'Approved after manual review.',
            'decided_at' => '2026-09-21 09:30:00',
        ]);
        $refund = Refund::factory()->for($request, 'refundRequest')->create([
            'status' => RefundStatus::Processing,
            'attempts' => 2,
            'last_error' => 'Test-only processor error.',
            'next_retry_at' => '2026-09-22 09:30:00',
            'processed_at' => null,
        ]);
        $metadata = ['decision' => 'approved'];
        $audit = AuditLog::factory()->for($request, 'subject')->create([
            'actor_type' => 'user',
            'actor_id' => $reviewer->id,
            'subject_id' => (string) $request->id,
            'metadata' => $metadata,
        ]);

        $this->assertSame(RefundReason::ChangedMind, $request->reason);
        $this->assertSame(50001, $request->amount_cents);
        $this->assertSame(RefundDecision::Escalated, $request->initial_decision);
        $this->assertSame(RefundDecision::Approved, $request->decision);
        $this->assertSame(DecisionSource::Human, $request->decision_source);
        $this->assertSame(DecisionCode::HighValueReviewRequired, $request->decision_code);
        $this->assertSame($policyChecks, $request->policy_checks);
        $this->assertInstanceOf(CarbonInterface::class, $request->decided_at);
        $this->assertSame(RefundStatus::Processing, $refund->status);
        $this->assertSame(2, $refund->attempts);
        $this->assertInstanceOf(CarbonInterface::class, $refund->next_retry_at);
        $this->assertNull($refund->processed_at);
        $this->assertSame($reviewer->id, $audit->actor_id);
        $this->assertSame($request->id, $audit->subject_id);
        $this->assertSame($metadata, $audit->metadata);
        $this->assertInstanceOf(CarbonInterface::class, $audit->created_at);
    }

    public function test_factories_create_coherent_authoritative_defaults_and_nullable_execution_fields(): void
    {
        $refund = Refund::factory()->create();
        $request = $refund->refundRequest;
        $conversation = $request->refundConversation;
        $audit = AuditLog::factory()->create();

        $this->assertSame($conversation->customer_id, $request->customer_id);
        $this->assertSame($conversation->order_id, $request->order_id);
        $this->assertSame($conversation->order_item_id, $request->order_item_id);
        $this->assertSame($request->order_item_id, $refund->order_item_id);
        $this->assertSame($request->amount_cents, $refund->amount_cents);
        $this->assertSame(RefundDecision::Approved, $request->decision);
        $this->assertSame(DecisionSource::PolicyEngine, $request->decision_source);
        $this->assertNull($request->reviewed_by);
        $this->assertNull($request->review_note);
        $this->assertSame(RefundStatus::Pending, $refund->status);
        $this->assertSame(0, $refund->attempts);
        $this->assertNull($refund->processor_reference);
        $this->assertNull($refund->last_error);
        $this->assertNull($refund->next_retry_at);
        $this->assertNull($refund->processed_at);
        $this->assertSame('system', $audit->actor_type);
        $this->assertNull($audit->actor_id);
        $this->assertNull($audit->metadata);
    }

    public function test_enums_expose_the_documented_persisted_values(): void
    {
        $this->assertSame(['approved', 'denied', 'escalated'], array_column(RefundDecision::cases(), 'value'));
        $this->assertSame(['policy_engine', 'human'], array_column(DecisionSource::cases(), 'value'));
        $this->assertSame(['pending', 'processing', 'processed', 'failed'], array_column(RefundStatus::cases(), 'value'));
        $this->assertSame([
            'DAMAGED_ITEM_ELIGIBLE',
            'INCORRECT_ITEM_ELIGIBLE',
            'FINAL_SALE_ITEM',
            'REFUND_WINDOW_EXPIRED',
            'ALREADY_REFUNDED',
            'HIGH_VALUE_REVIEW_REQUIRED',
            'CHANGED_MIND_REQUIRES_REVIEW',
            'MISSING_ITEM_REQUIRES_REVIEW',
            'CONFLICTING_INFORMATION',
            'PROMPT_INJECTION_DETECTED',
            'LOW_CONFIDENCE_REVIEW_REQUIRED',
            'OTHER_REASON_REQUIRES_REVIEW',
        ], array_column(DecisionCode::cases(), 'value'));
    }
}
