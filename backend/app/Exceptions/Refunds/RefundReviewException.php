<?php

namespace App\Exceptions\Refunds;

use App\Enums\Http\ApiErrorCode;
use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

final class RefundReviewException extends RuntimeException implements ShouldntReport
{
    public static function notAwaitingReview(): self
    {
        return new self('This refund request is no longer awaiting review.');
    }

    public function errorCode(): ApiErrorCode
    {
        return ApiErrorCode::Conflict;
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return [];
    }

    public function status(): int
    {
        return 409;
    }
}
