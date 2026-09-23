<?php

namespace Tests\Feature\Actions\Refunds;

use App\Actions\Refunds\EvaluateRefundConversation;
use App\Enums\AuditActorType;
use App\Enums\ConversationMessageTemplate;
use App\Enums\ConversationState;
use App\Enums\ConversationStatus;
use App\Enums\DecisionCode;
use App\Enums\DecisionSource;
use App\Enums\MessageSender;
use App\Enums\RefundDecision;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Models\AiAnalysis;
use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RefundConversation;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class EvaluateRefundConversationTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('policyOutcomes')]
    public function test_persists_each_policy_outcome_and_only_approved_requests_create_refunds(
        array $itemAttributes,
        RefundReason $reason,
        RefundDecision $expectedDecision,
        DecisionCode $expectedCode,
        int $expectedRefundCount,
    ): void {
        $this->travelTo('2026-09-23 12:00:00');
        [$conversation, $item] = $this->completeConversation($itemAttributes, $reason);

        $request = app(EvaluateRefundConversation::class)->handle($conversation);

        $this->assertSame($expectedDecision, $request->initial_decision);
        $this->assertSame($expectedDecision, $request->decision);
        $this->assertSame(DecisionSource::PolicyEngine, $request->decision_source);
        $this->assertSame($expectedCode, $request->decision_code);
        $this->assertSame($item->unit_price_cents, $request->amount_cents);
        $this->assertNull($request->reviewed_by);
        $this->assertNull($request->review_note);
        $this->assertNotEmpty($request->policy_checks);
        $this->assertSame($expectedRefundCount, $request->refund()->count());
        $this->assertSame($expectedRefundCount, $item->refund()->count());
        $this->assertDatabaseHas('refund_conversations', [
            'id' => $conversation->id,
            'state' => ConversationState::Resolved->value,
            'status' => ConversationStatus::Resolved->value,
            'resolved_at' => '2026-09-23 12:00:00',
        ]);
        $this->assertDatabaseHas('conversation_messages', [
            'refund_conversation_id' => $conversation->id,
            'sender' => MessageSender::Assistant->value,
            'content' => $this->expectedMessage($expectedDecision)->value,
        ]);
    }

    public function test_approved_outcome_uses_authoritative_amount_and_records_complete_audits(): void
    {
        $this->travelTo('2026-09-23 12:00:00');
        [$conversation, $item] = $this->completeConversation([
            'unit_price_cents' => 12999,
        ]);
        $analysis = $conversation->aiAnalyses()->sole();
        $analysis->update([
            'extracted_data' => [
                'reason' => RefundReason::DamagedItem->value,
                'reason_details' => 'Two keys arrived broken.',
                'refund_amount_cents' => 1,
            ],
        ]);

        $request = app(EvaluateRefundConversation::class)->handle($conversation);

        $refund = $request->refund()->sole();
        $this->assertSame(12999, $request->amount_cents);
        $this->assertSame(12999, $refund->amount_cents);
        $this->assertSame(RefundStatus::Pending, $refund->status);
        $this->assertSame('simulated', $refund->processor);
        $this->assertSame('refund-request-'.$request->id, $refund->idempotency_key);
        $this->assertSame(0, $refund->attempts);

        $policyAudit = $conversation->auditLogs()->where('event', 'policy.evaluated')->sole();
        $this->assertSame(AuditActorType::System, $policyAudit->actor_type);
        $this->assertSame($request->id, $policyAudit->metadata['refund_request_id'] ?? null);
        $this->assertSame(RefundDecision::Approved->value, $policyAudit->metadata['decision'] ?? null);
        $this->assertSame(DecisionCode::DamagedItemEligible->value, $policyAudit->metadata['decision_code'] ?? null);
        $this->assertSame(12999, $policyAudit->metadata['amount_cents'] ?? null);
        $this->assertSame(1, $policyAudit->metadata['ai_analysis_count'] ?? null);
        $this->assertSame(96, $policyAudit->metadata['confidence'] ?? null);
        $this->assertFalse($policyAudit->metadata['prompt_injection_detected'] ?? true);
        $this->assertFalse($policyAudit->metadata['conflicting_information'] ?? true);
        $this->assertSame($request->policy_checks, $policyAudit->metadata['policy_checks'] ?? null);

        $requestAudit = $request->auditLogs()->where('event', 'refund_request.approved')->sole();
        $this->assertSame(DecisionSource::PolicyEngine->value, $requestAudit->metadata['decision_source'] ?? null);
        $this->assertSame(DecisionCode::DamagedItemEligible->value, $requestAudit->metadata['decision_code'] ?? null);
        $this->assertSame(12999, $requestAudit->metadata['amount_cents'] ?? null);

        $refundAudit = $refund->auditLogs()->where('event', 'refund.created')->sole();
        $this->assertSame($request->id, $refundAudit->metadata['refund_request_id'] ?? null);
        $this->assertSame($item->id, $refundAudit->metadata['order_item_id'] ?? null);
        $this->assertSame(RefundStatus::Pending->value, $refundAudit->metadata['status'] ?? null);
    }

    public function test_retains_risk_signals_from_earlier_analyses(): void
    {
        $this->travelTo('2026-09-23 12:00:00');
        [$conversation] = $this->completeConversation();
        $firstAnalysis = $conversation->aiAnalyses()->sole();
        $firstAnalysis->update([
            'confidence' => 74,
            'prompt_injection_detected' => true,
        ]);
        $this->addAnalysis($conversation, [
            'confidence' => 99,
            'prompt_injection_detected' => false,
        ]);

        $request = app(EvaluateRefundConversation::class)->handle($conversation);

        $this->assertSame(RefundDecision::Escalated, $request->decision);
        $this->assertSame(DecisionCode::PromptInjectionDetected, $request->decision_code);
        $this->assertDatabaseCount('refunds', 0);
        $policyAudit = $conversation->auditLogs()->where('event', 'policy.evaluated')->sole();
        $this->assertSame(2, $policyAudit->metadata['ai_analysis_count'] ?? null);
        $this->assertSame(74, $policyAudit->metadata['confidence'] ?? null);
        $this->assertTrue($policyAudit->metadata['prompt_injection_detected'] ?? false);
    }

    public function test_missing_ai_analysis_fails_safe_to_human_review(): void
    {
        $this->travelTo('2026-09-23 12:00:00');
        [$conversation] = $this->completeConversation();
        $conversation->aiAnalyses()->delete();

        $request = app(EvaluateRefundConversation::class)->handle($conversation);

        $this->assertSame(RefundDecision::Escalated, $request->decision);
        $this->assertSame(DecisionCode::LowConfidenceReviewRequired, $request->decision_code);
        $this->assertDatabaseCount('refunds', 0);
        $policyAudit = $conversation->auditLogs()->where('event', 'policy.evaluated')->sole();
        $this->assertSame(0, $policyAudit->metadata['ai_analysis_count'] ?? null);
        $this->assertSame(0, $policyAudit->metadata['confidence'] ?? null);
    }

    public function test_repeated_evaluation_returns_the_single_existing_request_without_repeating_side_effects(): void
    {
        $this->travelTo('2026-09-23 12:00:00');
        [$conversation] = $this->completeConversation();
        $action = app(EvaluateRefundConversation::class);

        $firstRequest = $action->handle($conversation);
        $secondRequest = $action->handle($conversation->refresh());

        $this->assertSame($firstRequest->id, $secondRequest->id);
        $this->assertDatabaseCount('refund_requests', 1);
        $this->assertDatabaseCount('refunds', 1);
        $this->assertSame(1, $conversation->messages()
            ->where('sender', MessageSender::Assistant->value)
            ->count());
        $this->assertSame(1, $conversation->auditLogs()
            ->where('event', 'policy.evaluated')
            ->count());
        $this->assertSame(1, $firstRequest->auditLogs()
            ->where('event', 'refund_request.approved')
            ->count());
    }

    public function test_rolls_back_every_policy_outcome_write_when_a_late_write_fails(): void
    {
        $this->travelTo('2026-09-23 12:00:00');
        [$conversation] = $this->completeConversation();
        Event::listen(QueryExecuted::class, static function (QueryExecuted $query): void {
            if (str_contains($query->sql, 'insert into "conversation_messages"')) {
                throw new RuntimeException('Simulated assistant-message persistence failure.');
            }
        });

        try {
            app(EvaluateRefundConversation::class)->handle($conversation);
            $this->fail('The late persistence failure was not raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated assistant-message persistence failure.', $exception->getMessage());
        }

        $this->assertDatabaseCount('refund_requests', 0);
        $this->assertDatabaseCount('refunds', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('conversation_messages', 1);
        $this->assertDatabaseHas('refund_conversations', [
            'id' => $conversation->id,
            'state' => ConversationState::Evaluating->value,
            'status' => ConversationStatus::Active->value,
            'resolved_at' => null,
        ]);
    }

    /**
     * @return array<string, array{array<string, mixed>, RefundReason, RefundDecision, DecisionCode, int}>
     */
    public static function policyOutcomes(): array
    {
        return [
            'approved damaged item' => [
                ['unit_price_cents' => 12999, 'final_sale' => false],
                RefundReason::DamagedItem,
                RefundDecision::Approved,
                DecisionCode::DamagedItemEligible,
                1,
            ],
            'denied final-sale item' => [
                ['unit_price_cents' => 12999, 'final_sale' => true],
                RefundReason::DamagedItem,
                RefundDecision::Denied,
                DecisionCode::FinalSaleItem,
                0,
            ],
            'escalated changed-mind request' => [
                ['unit_price_cents' => 12999, 'final_sale' => false],
                RefundReason::ChangedMind,
                RefundDecision::Escalated,
                DecisionCode::ChangedMindRequiresReview,
                0,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $itemAttributes
     * @return array{RefundConversation, OrderItem}
     */
    private function completeConversation(
        array $itemAttributes = [],
        RefundReason $reason = RefundReason::DamagedItem,
    ): array {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create([
            'delivered_at' => '2026-09-18 12:00:00',
        ]);
        $item = OrderItem::factory()->for($order)->create(array_replace([
            'quantity' => 1,
            'unit_price_cents' => 12999,
            'final_sale' => false,
        ], $itemAttributes));
        $conversation = RefundConversation::factory()->forOrderItem($item)->create([
            'state' => ConversationState::Evaluating,
            'reason' => $reason,
            'reason_details' => 'The customer provided a complete explanation.',
        ]);
        $this->addAnalysis($conversation);

        return [$conversation, $item];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function addAnalysis(RefundConversation $conversation, array $attributes = []): AiAnalysis
    {
        $message = ConversationMessage::factory()->for($conversation)->create();

        return AiAnalysis::factory()->for($message, 'conversationMessage')->create(array_replace([
            'refund_conversation_id' => $conversation->id,
            'confidence' => 96,
            'prompt_injection_detected' => false,
            'conflicting_information' => false,
        ], $attributes));
    }

    private function expectedMessage(RefundDecision $decision): ConversationMessageTemplate
    {
        return match ($decision) {
            RefundDecision::Approved => ConversationMessageTemplate::RefundApproved,
            RefundDecision::Denied => ConversationMessageTemplate::RefundDenied,
            RefundDecision::Escalated => ConversationMessageTemplate::RefundEscalated,
        };
    }
}
