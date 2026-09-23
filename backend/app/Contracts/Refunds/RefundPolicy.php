<?php

namespace App\Contracts\Refunds;

use App\Data\Refunds\RefundPolicyContext;
use App\Data\Refunds\RefundPolicyResult;

interface RefundPolicy
{
    public function evaluate(RefundPolicyContext $context): RefundPolicyResult;
}
