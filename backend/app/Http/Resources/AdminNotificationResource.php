<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use LogicException;

/** @mixin DatabaseNotification */
class AdminNotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof DatabaseNotification) {
            throw new LogicException('Admin notification resources require a database notification model.');
        }

        $data = $this->resource->data;
        /** @var Carbon|null $readAt */
        $readAt = $this->resource->read_at;
        /** @var Carbon|null $createdAt */
        $createdAt = $this->resource->created_at;

        return [
            'id' => $this->resource->id,
            'type' => (string) ($data['type'] ?? ''),
            'title' => (string) ($data['title'] ?? ''),
            'message' => (string) ($data['message'] ?? ''),
            'refund_request_id' => (int) ($data['refund_request_id'] ?? 0),
            'error_summary' => isset($data['error_summary'])
                ? (string) $data['error_summary']
                : null,
            'read_at' => $readAt?->toISOString(),
            'created_at' => $createdAt?->toISOString(),
        ];
    }
}
