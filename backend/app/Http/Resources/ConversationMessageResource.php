<?php

namespace App\Http\Resources;

use App\Models\ConversationMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/** @mixin ConversationMessage */
class ConversationMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof ConversationMessage) {
            throw new LogicException('Conversation message resources require a conversation message model.');
        }

        $sender = $this->resource->sender;
        $createdAt = $this->resource->created_at;

        return [
            'id' => $this->resource->id,
            'client_message_id' => $this->resource->client_message_id,
            'sender' => $sender->value,
            'content' => $this->resource->content,
            'metadata' => $this->resource->metadata,
            'created_at' => $createdAt?->toISOString(),
        ];
    }
}
