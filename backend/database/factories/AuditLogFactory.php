<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\RefundConversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_type' => 'system',
            'actor_id' => null,
            'subject_type' => RefundConversation::class,
            'subject_id' => RefundConversation::factory(),
            'event' => 'policy.evaluated',
            'metadata' => null,
        ];
    }
}
