<?php

namespace App\Http\Controllers\Customer;

use App\Actions\Conversations\StartRefundConversation;
use App\Http\Controllers\Controller;
use App\Http\CurrentCustomer;
use App\Http\Requests\StoreRefundConversationRequest;
use App\Http\Resources\RefundConversationResource;
use App\Models\RefundConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RefundConversationController extends Controller
{
    public function index(CurrentCustomer $currentCustomer): AnonymousResourceCollection
    {
        $conversations = $currentCustomer->owned(RefundConversation::query())
            ->with($this->summaryRelationships())
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(15);

        return RefundConversationResource::collection($conversations);
    }

    public function store(
        StoreRefundConversationRequest $request,
        CurrentCustomer $currentCustomer,
        StartRefundConversation $startRefundConversation,
    ): JsonResponse {
        $request->validated();
        $conversation = $startRefundConversation->handle($currentCustomer->get());

        $conversation->load($this->detailRelationships());

        return (new RefundConversationResource($conversation))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function show(
        string $conversation,
        CurrentCustomer $currentCustomer,
    ): RefundConversationResource {
        $ownedConversation = $currentCustomer->findOwnedOrFail(
            RefundConversation::query()->with($this->detailRelationships()),
            $conversation,
        );

        return new RefundConversationResource($ownedConversation);
    }

    /**
     * @return list<string>
     */
    private function summaryRelationships(): array
    {
        return [
            'order:id,reference',
            'orderItem:id,name',
            'refundRequest:id,refund_conversation_id,decision',
            'latestMessage',
        ];
    }

    /**
     * @return list<string>
     */
    private function detailRelationships(): array
    {
        return [...$this->summaryRelationships(), 'messages'];
    }
}
