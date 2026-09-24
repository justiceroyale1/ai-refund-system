<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class RefundRequestReviewed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly int $refundRequestId) {}
}
