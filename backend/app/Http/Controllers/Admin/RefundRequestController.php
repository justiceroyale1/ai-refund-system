<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RefundDecision;
use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListRefundRequestsRequest;
use App\Http\Resources\RefundRequestSummaryResource;
use App\Models\RefundRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class RefundRequestController extends Controller
{
    public function index(ListRefundRequestsRequest $request): AnonymousResourceCollection
    {
        $decision = $request->enum('decision', RefundDecision::class);
        $executionStatus = $request->enum('execution_status', RefundStatus::class);
        $search = $request->string('search')->toString();

        $refundRequests = RefundRequest::query()
            ->select([
                'id',
                'customer_id',
                'order_id',
                'order_item_id',
                'reason',
                'amount_cents',
                'initial_decision',
                'decision',
                'decision_source',
                'decision_code',
                'decided_at',
                'created_at',
            ])
            ->with([
                'customer:id,name,email',
                'order:id,reference',
                'orderItem:id,name',
                'refund:id,refund_request_id,status',
            ])
            ->when(
                $decision !== null,
                fn (Builder $query): Builder => $query->where('decision', $decision?->value),
            )
            ->when(
                $executionStatus !== null,
                fn (Builder $query): Builder => $query->whereHas(
                    'refund',
                    fn (Builder $refundQuery): Builder => $refundQuery->where('status', $executionStatus?->value),
                ),
            )
            ->when($search !== '', function (Builder $query) use ($search): void {
                $pattern = $this->literalSearchPattern($search);

                $query->where(function (Builder $searchQuery) use ($pattern): void {
                    $searchQuery
                        ->whereHas('customer', function (Builder $customerQuery) use ($pattern): void {
                            $customerQuery
                                ->whereRaw("LOWER(name) LIKE ? ESCAPE '\\'", [$pattern])
                                ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '\\'", [$pattern]);
                        })
                        ->orWhereHas('order', function (Builder $orderQuery) use ($pattern): void {
                            $orderQuery->whereRaw("LOWER(reference) LIKE ? ESCAPE '\\'", [$pattern]);
                        });
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return RefundRequestSummaryResource::collection($refundRequests);
    }

    private function literalSearchPattern(string $search): string
    {
        $normalizedSearch = Str::lower($search);
        $escapedSearch = str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $normalizedSearch,
        );

        return "%{$escapedSearch}%";
    }
}
