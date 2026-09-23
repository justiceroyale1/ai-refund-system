<?php

namespace Tests\Unit\Services\Refunds;

use App\Data\Refunds\RefundPolicyContext;
use App\Enums\DecisionCode;
use App\Enums\PolicyCheckCode;
use App\Enums\PolicyCheckResult;
use App\Enums\RefundDecision;
use App\Enums\RefundReason;
use App\Services\Refunds\DefaultRefundPolicy;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DefaultRefundPolicyTest extends TestCase
{
    #[DataProvider('policyOutcomes')]
    public function test_returns_the_terminal_outcome_for_every_policy_rule(
        array $overrides,
        RefundDecision $expectedDecision,
        DecisionCode $expectedDecisionCode,
        PolicyCheckCode $expectedTerminalCheck,
        PolicyCheckResult $expectedTerminalResult,
    ): void {
        $result = (new DefaultRefundPolicy)->evaluate($this->context($overrides));

        $terminalCheck = $result->checks[array_key_last($result->checks)];

        $this->assertSame($expectedDecision, $result->decision);
        $this->assertSame($expectedDecisionCode, $result->decisionCode);
        $this->assertSame($overrides['unitPriceCents'] ?? 12999, $result->amountCents);
        $this->assertSame($expectedTerminalCheck, $terminalCheck->code);
        $this->assertSame($expectedTerminalResult, $terminalCheck->result);
    }

    #[DataProvider('inclusiveBoundaries')]
    public function test_keeps_inclusive_automatic_approval_boundaries(array $overrides): void
    {
        $result = (new DefaultRefundPolicy)->evaluate($this->context($overrides));

        $this->assertSame(RefundDecision::Approved, $result->decision);
        $this->assertSame(DecisionCode::DamagedItemEligible, $result->decisionCode);
    }

    #[DataProvider('precedenceCombinations')]
    public function test_applies_policy_rules_in_strict_precedence(
        array $overrides,
        RefundDecision $expectedDecision,
        DecisionCode $expectedDecisionCode,
    ): void {
        $result = (new DefaultRefundPolicy)->evaluate($this->context($overrides));

        $this->assertSame($expectedDecision, $result->decision);
        $this->assertSame($expectedDecisionCode, $result->decisionCode);
    }

    public function test_derives_the_refund_amount_from_the_authoritative_quantity_one_item_price(): void
    {
        $result = (new DefaultRefundPolicy)->evaluate($this->context([
            'unitPriceCents' => 34789,
        ]));

        $this->assertSame(34789, $result->amountCents);
    }

    public function test_returns_ordered_human_readable_checks_ready_for_json_persistence(): void
    {
        $result = (new DefaultRefundPolicy)->evaluate($this->context());

        $this->assertSame([
            ['code' => 'ALREADY_REFUNDED', 'result' => 'passed', 'message' => 'The order item has not already been refunded.'],
            ['code' => 'FINAL_SALE', 'result' => 'passed', 'message' => 'The order item is not marked as final sale.'],
            ['code' => 'REFUND_WINDOW', 'result' => 'passed', 'message' => 'The order item was delivered within the 30-day refund window.'],
            ['code' => 'CONFLICTING_INFORMATION', 'result' => 'passed', 'message' => 'No conflicting customer information was detected.'],
            ['code' => 'PROMPT_INJECTION', 'result' => 'passed', 'message' => 'No prompt-injection attempt was detected.'],
            ['code' => 'AI_CONFIDENCE', 'result' => 'passed', 'message' => 'AI confidence of 96% meets the 75% minimum.'],
            ['code' => 'REFUND_AMOUNT', 'result' => 'passed', 'message' => 'The authoritative refund amount of $129.99 is within the $500.00 automatic approval limit.'],
            ['code' => 'REASON_DETAILS', 'result' => 'passed', 'message' => 'The customer provided a meaningful description of the refund reason.'],
            ['code' => 'REFUND_REASON', 'result' => 'passed', 'message' => 'The damaged item is eligible for automatic approval.'],
        ], $result->policyChecks());
    }

    /**
     * @return array<string, array{array<string, mixed>, RefundDecision, DecisionCode, PolicyCheckCode, PolicyCheckResult}>
     */
    public static function policyOutcomes(): array
    {
        return [
            'already refunded' => [
                ['alreadyRefunded' => true],
                RefundDecision::Denied,
                DecisionCode::AlreadyRefunded,
                PolicyCheckCode::AlreadyRefunded,
                PolicyCheckResult::Failed,
            ],
            'final sale' => [
                ['finalSale' => true],
                RefundDecision::Denied,
                DecisionCode::FinalSaleItem,
                PolicyCheckCode::FinalSale,
                PolicyCheckResult::Failed,
            ],
            'refund window expired by one second' => [
                ['deliveredAt' => new DateTimeImmutable('2026-08-21 11:59:59')],
                RefundDecision::Denied,
                DecisionCode::RefundWindowExpired,
                PolicyCheckCode::RefundWindow,
                PolicyCheckResult::Failed,
            ],
            'conflicting information' => [
                ['conflictingInformation' => true],
                RefundDecision::Escalated,
                DecisionCode::ConflictingInformation,
                PolicyCheckCode::ConflictingInformation,
                PolicyCheckResult::ReviewRequired,
            ],
            'prompt injection' => [
                ['promptInjectionDetected' => true],
                RefundDecision::Escalated,
                DecisionCode::PromptInjectionDetected,
                PolicyCheckCode::PromptInjection,
                PolicyCheckResult::ReviewRequired,
            ],
            'confidence below threshold' => [
                ['confidence' => 74],
                RefundDecision::Escalated,
                DecisionCode::LowConfidenceReviewRequired,
                PolicyCheckCode::AIConfidence,
                PolicyCheckResult::ReviewRequired,
            ],
            'amount above threshold' => [
                ['unitPriceCents' => 50001],
                RefundDecision::Escalated,
                DecisionCode::HighValueReviewRequired,
                PolicyCheckCode::RefundAmount,
                PolicyCheckResult::ReviewRequired,
            ],
            'changed mind' => [
                ['reason' => RefundReason::ChangedMind],
                RefundDecision::Escalated,
                DecisionCode::ChangedMindRequiresReview,
                PolicyCheckCode::RefundReason,
                PolicyCheckResult::ReviewRequired,
            ],
            'missing item' => [
                ['reason' => RefundReason::MissingItem],
                RefundDecision::Escalated,
                DecisionCode::MissingItemRequiresReview,
                PolicyCheckCode::RefundReason,
                PolicyCheckResult::ReviewRequired,
            ],
            'damaged item' => [
                ['reason' => RefundReason::DamagedItem],
                RefundDecision::Approved,
                DecisionCode::DamagedItemEligible,
                PolicyCheckCode::RefundReason,
                PolicyCheckResult::Passed,
            ],
            'incorrect item' => [
                ['reason' => RefundReason::IncorrectItem],
                RefundDecision::Approved,
                DecisionCode::IncorrectItemEligible,
                PolicyCheckCode::RefundReason,
                PolicyCheckResult::Passed,
            ],
            'other reason' => [
                ['reason' => RefundReason::Other],
                RefundDecision::Escalated,
                DecisionCode::OtherReasonRequiresReview,
                PolicyCheckCode::RefundReason,
                PolicyCheckResult::ReviewRequired,
            ],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function inclusiveBoundaries(): array
    {
        return [
            'exactly thirty days after delivery' => [[
                'deliveredAt' => new DateTimeImmutable('2026-08-21 12:00:00'),
            ]],
            'exactly five hundred dollars' => [['unitPriceCents' => 50000]],
            'exactly seventy five percent confidence' => [['confidence' => 75]],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>, RefundDecision, DecisionCode}>
     */
    public static function precedenceCombinations(): array
    {
        return [
            'already refunded before every later rule' => [[
                'alreadyRefunded' => true,
                'finalSale' => true,
                'deliveredAt' => new DateTimeImmutable('2026-08-01 12:00:00'),
                'conflictingInformation' => true,
                'promptInjectionDetected' => true,
                'confidence' => 0,
                'unitPriceCents' => 50001,
                'reason' => RefundReason::ChangedMind,
            ], RefundDecision::Denied, DecisionCode::AlreadyRefunded],
            'final sale before expiry and suspicious signals' => [[
                'finalSale' => true,
                'deliveredAt' => new DateTimeImmutable('2026-08-01 12:00:00'),
                'conflictingInformation' => true,
                'promptInjectionDetected' => true,
                'confidence' => 0,
            ], RefundDecision::Denied, DecisionCode::FinalSaleItem],
            'expiry before suspicious signals and high value' => [[
                'deliveredAt' => new DateTimeImmutable('2026-08-01 12:00:00'),
                'conflictingInformation' => true,
                'promptInjectionDetected' => true,
                'confidence' => 0,
                'unitPriceCents' => 50001,
            ], RefundDecision::Denied, DecisionCode::RefundWindowExpired],
            'conflict before other suspicious signals and high value' => [[
                'conflictingInformation' => true,
                'promptInjectionDetected' => true,
                'confidence' => 0,
                'unitPriceCents' => 50001,
            ], RefundDecision::Escalated, DecisionCode::ConflictingInformation],
            'prompt injection before low confidence and high value' => [[
                'promptInjectionDetected' => true,
                'confidence' => 0,
                'unitPriceCents' => 50001,
            ], RefundDecision::Escalated, DecisionCode::PromptInjectionDetected],
            'low confidence before high value' => [[
                'confidence' => 74,
                'unitPriceCents' => 50001,
            ], RefundDecision::Escalated, DecisionCode::LowConfidenceReviewRequired],
            'high value before refund reason' => [[
                'unitPriceCents' => 50001,
                'reason' => RefundReason::ChangedMind,
            ], RefundDecision::Escalated, DecisionCode::HighValueReviewRequired],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function context(array $overrides = []): RefundPolicyContext
    {
        return new RefundPolicyContext(...array_replace([
            'unitPriceCents' => 12999,
            'quantity' => 1,
            'finalSale' => false,
            'deliveredAt' => new DateTimeImmutable('2026-09-01 12:00:00'),
            'alreadyRefunded' => false,
            'reason' => RefundReason::DamagedItem,
            'reasonDetails' => 'Two keys were broken when the package was opened.',
            'conflictingInformation' => false,
            'promptInjectionDetected' => false,
            'confidence' => 96,
            'evaluatedAt' => new DateTimeImmutable('2026-09-20 12:00:00'),
        ], $overrides));
    }
}
