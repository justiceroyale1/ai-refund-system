<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RefundDecision;
use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminDashboardResource;
use App\Models\Refund;
use App\Models\RefundRequest;
use Illuminate\Support\Collection;

class AdminDashboardController extends Controller
{
    public function show(): AdminDashboardResource
    {
        /** @var Collection<string, int|string> $requestCounts */
        $requestCounts = RefundRequest::query()
            ->toBase()
            ->select('decision')
            ->selectRaw('COUNT(*) AS aggregate')
            ->whereIn('decision', array_column(RefundDecision::cases(), 'value'))
            ->groupBy('decision')
            ->pluck('aggregate', 'decision');

        /** @var Collection<string, int|string> $refundCounts */
        $refundCounts = Refund::query()
            ->toBase()
            ->select('status')
            ->selectRaw('COUNT(*) AS aggregate')
            ->whereIn('status', [RefundStatus::Pending->value, RefundStatus::Failed->value])
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return new AdminDashboardResource([
            'approved_request_count' => (int) $requestCounts->get(RefundDecision::Approved->value, 0),
            'denied_request_count' => (int) $requestCounts->get(RefundDecision::Denied->value, 0),
            'escalated_request_count' => (int) $requestCounts->get(RefundDecision::Escalated->value, 0),
            'pending_refund_count' => (int) $refundCounts->get(RefundStatus::Pending->value, 0),
            'failed_refund_count' => (int) $refundCounts->get(RefundStatus::Failed->value, 0),
        ]);
    }
}
