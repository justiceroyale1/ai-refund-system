<?php

namespace Tests\Unit\Data\Refunds;

use App\Data\Refunds\RefundPolicyCheck;
use App\Enums\PolicyCheckCode;
use App\Enums\PolicyCheckResult;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RefundPolicyCheckTest extends TestCase
{
    public function test_serializes_stable_policy_check_values(): void
    {
        $check = new RefundPolicyCheck(
            PolicyCheckCode::FinalSale,
            PolicyCheckResult::Passed,
            'The order item is not marked as final sale.',
        );

        $this->assertSame([
            'code' => 'FINAL_SALE',
            'result' => 'passed',
            'message' => 'The order item is not marked as final sale.',
        ], $check->toArray());
    }

    public function test_rejects_a_blank_human_readable_message(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RefundPolicyCheck(
            PolicyCheckCode::FinalSale,
            PolicyCheckResult::Passed,
            " \n\t ",
        );
    }
}
