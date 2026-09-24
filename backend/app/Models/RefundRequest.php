<?php

namespace App\Models;

use App\Enums\DecisionCode;
use App\Enums\DecisionSource;
use App\Enums\RefundDecision;
use App\Enums\RefundReason;
use Database\Factories\RefundRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $refund_conversation_id
 * @property int $customer_id
 * @property int $order_id
 * @property int $order_item_id
 * @property RefundReason $reason
 * @property string|null $reason_details
 * @property int $amount_cents
 * @property RefundDecision $initial_decision
 * @property RefundDecision|null $decision
 * @property DecisionSource $decision_source
 * @property DecisionCode $decision_code
 * @property list<array<string, mixed>> $policy_checks
 * @property int|null $reviewed_by
 * @property string|null $review_note
 * @property Carbon|null $decided_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['refund_conversation_id', 'customer_id', 'order_id', 'order_item_id', 'reason', 'reason_details', 'amount_cents', 'initial_decision', 'decision', 'decision_source', 'decision_code', 'policy_checks', 'reviewed_by', 'review_note', 'decided_at'])]
class RefundRequest extends Model
{
    /** @use HasFactory<RefundRequestFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<RefundConversation, $this>
     */
    public function refundConversation(): BelongsTo
    {
        return $this->belongsTo(RefundConversation::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasOne<Refund, $this>
     */
    public function refund(): HasOne
    {
        return $this->hasOne(Refund::class);
    }

    /**
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'subject');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => RefundReason::class,
            'amount_cents' => 'integer',
            'initial_decision' => RefundDecision::class,
            'decision' => RefundDecision::class,
            'decision_source' => DecisionSource::class,
            'decision_code' => DecisionCode::class,
            'policy_checks' => 'array',
            'decided_at' => 'datetime',
        ];
    }
}
