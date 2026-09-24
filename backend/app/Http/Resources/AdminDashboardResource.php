<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class AdminDashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (! is_array($this->resource)) {
            throw new LogicException('Admin dashboard resources require an array of metrics.');
        }

        return [
            'approved_request_count' => (int) ($this->resource['approved_request_count'] ?? 0),
            'denied_request_count' => (int) ($this->resource['denied_request_count'] ?? 0),
            'escalated_request_count' => (int) ($this->resource['escalated_request_count'] ?? 0),
            'pending_refund_count' => (int) ($this->resource['pending_refund_count'] ?? 0),
            'failed_refund_count' => (int) ($this->resource['failed_refund_count'] ?? 0),
        ];
    }
}
