<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int $refund_request_id
 * @property int $amount_cents
 * @property RefundStatus $status
 * @property string $processor
 * @property string $idempotency_key
 */
#[Fillable(['refund_request_id', 'order_item_id', 'amount_cents', 'status', 'processor', 'idempotency_key', 'processor_reference', 'attempts', 'last_error', 'next_retry_at', 'processed_at'])]
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<RefundRequest, $this>
     */
    public function refundRequest(): BelongsTo
    {
        return $this->belongsTo(RefundRequest::class);
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'subject');
    }

    /**
     * @param  Builder<Refund>  $query
     * @return Builder<Refund>
     */
    #[Scope]
    protected function eligibleForDispatch(Builder $query): Builder
    {
        return $query
            ->where('status', RefundStatus::Pending->value)
            ->whereNull('next_retry_at');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'status' => RefundStatus::class,
            'attempts' => 'integer',
            'next_retry_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
