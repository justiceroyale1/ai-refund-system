<?php

namespace App\Services\Refunds;

use App\Contracts\Refunds\RefundPolicy;
use App\Data\Refunds\RefundPolicyCheck;
use App\Data\Refunds\RefundPolicyContext;
use App\Data\Refunds\RefundPolicyResult;
use App\Enums\DecisionCode;
use App\Enums\PolicyCheckCode;
use App\Enums\PolicyCheckResult;
use App\Enums\RefundDecision;
use App\Enums\RefundReason;
use LogicException;

final class DefaultRefundPolicy implements RefundPolicy
{
    private const int REFUND_WINDOW_DAYS = 30;

    private const int AUTOMATIC_APPROVAL_LIMIT_CENTS = 50000;

    private const int MINIMUM_CONFIDENCE = 75;

    public function evaluate(RefundPolicyContext $context): RefundPolicyResult
    {
        $checks = [];

        if ($context->alreadyRefunded) {
            $checks[] = $this->check(
                PolicyCheckCode::AlreadyRefunded,
                PolicyCheckResult::Failed,
                'The order item has already been refunded.',
            );

            return $this->result(
                RefundDecision::Denied,
                DecisionCode::AlreadyRefunded,
                $context,
                $checks,
            );
        }

        $checks[] = $this->check(
            PolicyCheckCode::AlreadyRefunded,
            PolicyCheckResult::Passed,
            'The order item has not already been refunded.',
        );

        if ($context->finalSale) {
            $checks[] = $this->check(
                PolicyCheckCode::FinalSale,
                PolicyCheckResult::Failed,
                'The order item is marked as final sale.',
            );

            return $this->result(
                RefundDecision::Denied,
                DecisionCode::FinalSaleItem,
                $context,
                $checks,
            );
        }

        $checks[] = $this->check(
            PolicyCheckCode::FinalSale,
            PolicyCheckResult::Passed,
            'The order item is not marked as final sale.',
        );

        $refundWindowStart = $context->evaluatedAt->modify(sprintf('-%d days', self::REFUND_WINDOW_DAYS));

        if ($context->deliveredAt < $refundWindowStart) {
            $checks[] = $this->check(
                PolicyCheckCode::RefundWindow,
                PolicyCheckResult::Failed,
                'The order item was delivered more than 30 days ago.',
            );

            return $this->result(
                RefundDecision::Denied,
                DecisionCode::RefundWindowExpired,
                $context,
                $checks,
            );
        }

        $checks[] = $this->check(
            PolicyCheckCode::RefundWindow,
            PolicyCheckResult::Passed,
            'The order item was delivered within the 30-day refund window.',
        );

        if ($context->conflictingInformation) {
            $checks[] = $this->check(
                PolicyCheckCode::ConflictingInformation,
                PolicyCheckResult::ReviewRequired,
                'Conflicting customer information requires human review.',
            );

            return $this->result(
                RefundDecision::Escalated,
                DecisionCode::ConflictingInformation,
                $context,
                $checks,
            );
        }

        $checks[] = $this->check(
            PolicyCheckCode::ConflictingInformation,
            PolicyCheckResult::Passed,
            'No conflicting customer information was detected.',
        );

        if ($context->promptInjectionDetected) {
            $checks[] = $this->check(
                PolicyCheckCode::PromptInjection,
                PolicyCheckResult::ReviewRequired,
                'A possible prompt-injection attempt requires human review.',
            );

            return $this->result(
                RefundDecision::Escalated,
                DecisionCode::PromptInjectionDetected,
                $context,
                $checks,
            );
        }

        $checks[] = $this->check(
            PolicyCheckCode::PromptInjection,
            PolicyCheckResult::Passed,
            'No prompt-injection attempt was detected.',
        );

        if ($context->confidence < self::MINIMUM_CONFIDENCE) {
            $checks[] = $this->check(
                PolicyCheckCode::AIConfidence,
                PolicyCheckResult::ReviewRequired,
                sprintf('AI confidence of %d%% is below the 75%% minimum.', $context->confidence),
            );

            return $this->result(
                RefundDecision::Escalated,
                DecisionCode::LowConfidenceReviewRequired,
                $context,
                $checks,
            );
        }

        $checks[] = $this->check(
            PolicyCheckCode::AIConfidence,
            PolicyCheckResult::Passed,
            sprintf('AI confidence of %d%% meets the 75%% minimum.', $context->confidence),
        );

        if ($context->unitPriceCents > self::AUTOMATIC_APPROVAL_LIMIT_CENTS) {
            $checks[] = $this->check(
                PolicyCheckCode::RefundAmount,
                PolicyCheckResult::ReviewRequired,
                sprintf(
                    'The authoritative refund amount of %s exceeds the $500.00 automatic approval limit.',
                    $this->formatCents($context->unitPriceCents),
                ),
            );

            return $this->result(
                RefundDecision::Escalated,
                DecisionCode::HighValueReviewRequired,
                $context,
                $checks,
            );
        }

        $checks[] = $this->check(
            PolicyCheckCode::RefundAmount,
            PolicyCheckResult::Passed,
            sprintf(
                'The authoritative refund amount of %s is within the $500.00 automatic approval limit.',
                $this->formatCents($context->unitPriceCents),
            ),
        );
        $checks[] = $this->check(
            PolicyCheckCode::ReasonDetails,
            PolicyCheckResult::Passed,
            'The customer provided a meaningful description of the refund reason.',
        );

        [$decision, $decisionCode, $checkResult, $message] = match ($context->reason) {
            RefundReason::DamagedItem => [
                RefundDecision::Approved,
                DecisionCode::DamagedItemEligible,
                PolicyCheckResult::Passed,
                'The damaged item is eligible for automatic approval.',
            ],
            RefundReason::IncorrectItem => [
                RefundDecision::Approved,
                DecisionCode::IncorrectItemEligible,
                PolicyCheckResult::Passed,
                'The incorrect item is eligible for automatic approval.',
            ],
            RefundReason::ChangedMind => [
                RefundDecision::Escalated,
                DecisionCode::ChangedMindRequiresReview,
                PolicyCheckResult::ReviewRequired,
                'Changed-mind requests require human review.',
            ],
            RefundReason::MissingItem => [
                RefundDecision::Escalated,
                DecisionCode::MissingItemRequiresReview,
                PolicyCheckResult::ReviewRequired,
                'Missing-item requests require human review.',
            ],
            RefundReason::Other => [
                RefundDecision::Escalated,
                DecisionCode::OtherReasonRequiresReview,
                PolicyCheckResult::ReviewRequired,
                'This refund reason requires human review.',
            ],
            RefundReason::Unknown => throw new LogicException('Unknown refund reasons cannot reach policy evaluation.'),
        };

        $checks[] = $this->check(
            PolicyCheckCode::RefundReason,
            $checkResult,
            $message,
        );

        return $this->result($decision, $decisionCode, $context, $checks);
    }

    private function check(
        PolicyCheckCode $code,
        PolicyCheckResult $result,
        string $message,
    ): RefundPolicyCheck {
        return new RefundPolicyCheck($code, $result, $message);
    }

    private function formatCents(int $amountCents): string
    {
        return sprintf('$%d.%02d', intdiv($amountCents, 100), $amountCents % 100);
    }

    /**
     * @param  list<RefundPolicyCheck>  $checks
     */
    private function result(
        RefundDecision $decision,
        DecisionCode $decisionCode,
        RefundPolicyContext $context,
        array $checks,
    ): RefundPolicyResult {
        return new RefundPolicyResult(
            $decision,
            $decisionCode,
            $context->unitPriceCents,
            $checks,
        );
    }
}
