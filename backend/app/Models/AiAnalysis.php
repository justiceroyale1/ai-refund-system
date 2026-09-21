<?php

namespace App\Models;

use Database\Factories\AiAnalysisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['refund_conversation_id', 'conversation_message_id', 'provider', 'model', 'prompt_version', 'confidence', 'prompt_injection_detected', 'conflicting_information', 'extracted_data', 'raw_response'])]
class AiAnalysis extends Model
{
    /** @use HasFactory<AiAnalysisFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<RefundConversation, $this>
     */
    public function refundConversation(): BelongsTo
    {
        return $this->belongsTo(RefundConversation::class);
    }

    /**
     * @return BelongsTo<ConversationMessage, $this>
     */
    public function conversationMessage(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confidence' => 'integer',
            'prompt_injection_detected' => 'boolean',
            'conflicting_information' => 'boolean',
            'extracted_data' => 'array',
        ];
    }
}
