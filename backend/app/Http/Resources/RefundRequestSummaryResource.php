<?php

namespace App\Http\Resources;

use App\Models\RefundRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/** @mixin RefundRequest */
class RefundRequestSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof RefundRequest) {
            throw new LogicException('Refund request summary resources require a refund request model.');
        }

        $reason = $this->resource->reason;
        $initialDecision = $this->resource->initial_decision;
        $decision = $this->resource->decision;
        $decisionSource = $this->resource->decision_source;
        $decisionCode = $this->resource->decision_code;
        $decidedAt = $this->resource->decided_at;
        $createdAt = $this->resource->created_at;

        return [
            'id' => $this->resource->id,
            'customer' => $this->whenLoaded('customer', fn (): ?array => $this->resource->customer === null ? null : [
                'id' => $this->resource->customer->id,
                'name' => $this->resource->customer->name,
                'email' => $this->resource->customer->email,
            ]),
            'order' => $this->whenLoaded('order', fn (): ?array => $this->resource->order === null ? null : [
                'id' => $this->resource->order->id,
                'reference' => $this->resource->order->reference,
            ]),
            'order_item' => $this->whenLoaded('orderItem', fn (): ?array => $this->resource->orderItem === null ? null : [
                'id' => $this->resource->orderItem->id,
                'name' => $this->resource->orderItem->name,
            ]),
            'reason' => $reason->value,
            'amount_cents' => $this->resource->amount_cents,
            'initial_decision' => $initialDecision->value,
            'decision' => $decision?->value,
            'decision_source' => $decisionSource->value,
            'decision_code' => $decisionCode->value,
            'execution_status' => $this->whenLoaded(
                'refund',
                fn (): ?string => $this->resource->refund?->status->value,
            ),
            'decided_at' => $decidedAt?->toISOString(),
            'created_at' => $createdAt?->toISOString(),
        ];
    }
}
