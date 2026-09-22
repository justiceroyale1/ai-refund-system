<?php

namespace App\Http\Resources;

use App\Models\ConversationMessage;
use App\Models\RefundConversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/** @mixin RefundConversation */
class RefundConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof RefundConversation) {
            throw new LogicException('Refund conversation resources require a refund conversation model.');
        }

        $state = $this->resource->state;
        $status = $this->resource->status;
        $reason = $this->resource->reason;
        $resolvedAt = $this->resource->resolved_at;
        $createdAt = $this->resource->created_at;
        $updatedAt = $this->resource->updated_at;

        return [
            'id' => $this->resource->id,
            'order' => $this->whenLoaded('order', fn (): ?array => $this->resource->order === null ? null : [
                'id' => $this->resource->order->id,
                'reference' => $this->resource->order->reference,
            ]),
            'order_item' => $this->whenLoaded('orderItem', fn (): ?array => $this->resource->orderItem === null ? null : [
                'id' => $this->resource->orderItem->id,
                'name' => $this->resource->orderItem->name,
            ]),
            'state' => $state->value,
            'status' => $status->value,
            'reason' => $reason?->value,
            'reason_details' => $this->resource->reason_details,
            'decision' => $this->whenLoaded(
                'refundRequest',
                fn (): ?string => $this->currentDecision(),
            ),
            'available_actions' => $this->availableActions(),
            'messages' => ConversationMessageResource::collection($this->whenLoaded('messages')),
            'resolved_at' => $resolvedAt?->toISOString(),
            'created_at' => $createdAt?->toISOString(),
            'updated_at' => $updatedAt?->toISOString(),
        ];
    }

    private function currentDecision(): ?string
    {
        $decision = $this->resource->refundRequest?->decision;

        if ($decision === null) {
            return null;
        }

        return $decision->value;
    }

    /**
     * @return array<int, mixed>
     */
    private function availableActions(): array
    {
        if (! $this->resource->relationLoaded('latestMessage')) {
            return [];
        }

        $latestMessage = $this->resource->getRelation('latestMessage');

        if (! $latestMessage instanceof ConversationMessage) {
            return [];
        }

        $metadata = $latestMessage->metadata;

        if (! is_array($metadata)) {
            return [];
        }

        $actions = $metadata['actions'] ?? [];

        return is_array($actions) ? array_values($actions) : [];
    }
}
