<?php

namespace App\Http\Resources;

use App\Enums\MessageSender;
use App\Models\ConversationMessage;
use Carbon\CarbonInterface;
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

        $sender = $this->resource->getAttribute('sender');
        $createdAt = $this->resource->getAttribute('created_at');

        if (! $sender instanceof MessageSender) {
            throw new LogicException('Conversation message sender must be cast to a message sender enum.');
        }

        if ($createdAt !== null && ! $createdAt instanceof CarbonInterface) {
            throw new LogicException('Conversation message creation time must be cast to a date.');
        }

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
