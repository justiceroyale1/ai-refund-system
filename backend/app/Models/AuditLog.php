<?php

namespace App\Models;

use App\Enums\AuditActorType;
use App\Enums\AuditEvent;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property AuditActorType $actor_type
 * @property AuditEvent $event
 */
#[Fillable(['actor_type', 'actor_id', 'subject_type', 'subject_id', 'event', 'metadata'])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actor_type' => AuditActorType::class,
            'event' => AuditEvent::class,
            'actor_id' => 'integer',
            'subject_id' => 'integer',
            'metadata' => 'array',
        ];
    }
}
