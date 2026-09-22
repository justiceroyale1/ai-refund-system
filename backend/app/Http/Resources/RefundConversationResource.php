<?php

namespace App\Http\Resources;

use App\Enums\ConversationState;
use App\Enums\ConversationStatus;
use App\Enums\RefundDecision;
use App\Enums\RefundReason;
use App\Models\ConversationMessage;
use App\Models\RefundConversation;
use Carbon\CarbonInterface;
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

        $state = $this->resource->getAttribute('state');
        $status = $this->resource->getAttribute('status');
        $reason = $this->resource->getAttribute('reason');
        $resolvedAt = $this->resource->getAttribute('resolved_at');
        $createdAt = $this->resource->getAttribute('created_at');
        $updatedAt = $this->resource->getAttribute('updated_at');

        if (! $state instanceof ConversationState || ! $status instanceof ConversationStatus) {
            throw new LogicException('Conversation state and status must be cast to domain enums.');
        }

        if ($reason !== null && ! $reason instanceof RefundReason) {
            throw new LogicException('Conversation reason must be cast to a refund reason enum.');
        }

        foreach ([$resolvedAt, $createdAt, $updatedAt] as $timestamp) {
            if ($timestamp !== null && ! $timestamp instanceof CarbonInterface) {
                throw new LogicException('Conversation timestamps must be cast to dates.');
            }
        }

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
        $decision = $this->resource->refundRequest?->getAttribute('decision');

        if ($decision === null) {
            return null;
        }

        if (! $decision instanceof RefundDecision) {
            throw new LogicException('Refund request decisions must be cast to a refund decision enum.');
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

        $metadata = $latestMessage->getAttribute('metadata');

        if (! is_array($metadata)) {
            return [];
        }

        $actions = $metadata['actions'] ?? [];

        return is_array($actions) ? array_values($actions) : [];
    }
}
