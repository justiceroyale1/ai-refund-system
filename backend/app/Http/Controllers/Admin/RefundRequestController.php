<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RefundDecision;
use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListRefundRequestsRequest;
use App\Http\Resources\RefundRequestDetailResource;
use App\Http\Resources\RefundRequestSummaryResource;
use App\Models\RefundRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    public function show(RefundRequest $refundRequest): RefundRequestDetailResource
    {
        $refundRequest->load([
            'customer:id,name,email',
            'order:id,reference,status,ordered_at,delivered_at',
            'orderItem:id,sku,name,quantity,unit_price_cents,final_sale',
            'reviewer:id,name,email',
            'refund:id,refund_request_id,amount_cents,status,processor,processor_reference,attempts,last_error,next_retry_at,processed_at,created_at,updated_at',
            'refund.auditLogs:id,actor_type,actor_id,subject_type,subject_id,event,created_at',
            'refundConversation:id,customer_id,order_id,order_item_id,state,reason,reason_details,status,resolved_at,created_at,updated_at',
            'refundConversation.messages:id,refund_conversation_id,sender,content,metadata,created_at',
            'refundConversation.latestAiAnalysis' => fn (HasOne $query): HasOne => $query->select([
                'ai_analyses.id',
                'ai_analyses.refund_conversation_id',
                'ai_analyses.conversation_message_id',
                'ai_analyses.confidence',
                'ai_analyses.prompt_injection_detected',
                'ai_analyses.conflicting_information',
                'ai_analyses.extracted_data',
                'ai_analyses.created_at',
            ]),
            'refundConversation.auditLogs:id,actor_type,actor_id,subject_type,subject_id,event,created_at',
            'auditLogs:id,actor_type,actor_id,subject_type,subject_id,event,created_at',
        ]);

        return new RefundRequestDetailResource($refundRequest);
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
