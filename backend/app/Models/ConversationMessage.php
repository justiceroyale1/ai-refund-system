<?php

namespace App\Models;

use App\Enums\MessageSender;
use Database\Factories\ConversationMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property MessageSender $sender
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 */
#[Fillable(['refund_conversation_id', 'client_message_id', 'sender', 'content', 'metadata'])]
class ConversationMessage extends Model
{
    /** @use HasFactory<ConversationMessageFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $touches = ['refundConversation'];

    /**
     * @return BelongsTo<RefundConversation, $this>
     */
    public function refundConversation(): BelongsTo
    {
        return $this->belongsTo(RefundConversation::class);
    }

    /**
     * @return HasMany<AiAnalysis, $this>
     */
    public function aiAnalyses(): HasMany
    {
        return $this->hasMany(AiAnalysis::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sender' => MessageSender::class,
            'metadata' => 'array',
        ];
    }
}
