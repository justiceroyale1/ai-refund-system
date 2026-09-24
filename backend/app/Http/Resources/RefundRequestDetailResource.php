<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
use App\Models\ConversationMessage;
use App\Models\Refund;
use App\Models\RefundConversation;
use App\Models\RefundRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/** @mixin RefundRequest */
class RefundRequestDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof RefundRequest) {
            throw new LogicException('Refund request detail resources require a refund request model.');
        }

        $reason = $this->resource->reason;
        $initialDecision = $this->resource->initial_decision;
        $decision = $this->resource->decision;
        $decisionSource = $this->resource->decision_source;
        $decisionCode = $this->resource->decision_code;
        $decidedAt = $this->resource->decided_at;
        $createdAt = $this->resource->created_at;
        $updatedAt = $this->resource->updated_at;

        return [
            'id' => $this->resource->id,
            'customer' => $this->whenLoaded('customer', fn (): ?array => $this->resource->customer === null ? null : [
                'id' => $this->resource->customer->id,
                'name' => $this->resource->customer->name,
                'email' => $this->resource->customer->email,
            ]),
            'order' => $this->whenLoaded('order', fn (): ?array => $this->resource->order === null ? null : [
                'id' => $this->resource->order->id,
                'reference' => $this->resource->order->reference,
                'status' => $this->resource->order->status,
                'ordered_at' => $this->resource->order->ordered_at?->toISOString(),
                'delivered_at' => $this->resource->order->delivered_at?->toISOString(),
            ]),
            'order_item' => $this->whenLoaded('orderItem', fn (): ?array => $this->resource->orderItem === null ? null : [
                'id' => $this->resource->orderItem->id,
                'sku' => $this->resource->orderItem->sku,
                'name' => $this->resource->orderItem->name,
                'quantity' => $this->resource->orderItem->quantity,
                'unit_price_cents' => $this->resource->orderItem->unit_price_cents,
                'final_sale' => $this->resource->orderItem->final_sale,
            ]),
            'reason' => $reason->value,
            'reason_details' => $this->resource->reason_details,
            'amount_cents' => $this->resource->amount_cents,
            'policy_checks' => $this->resource->policy_checks,
            'initial_decision' => $initialDecision->value,
            'decision' => $decision?->value,
            'decision_source' => $decisionSource->value,
            'decision_code' => $decisionCode->value,
            'reviewer' => $this->whenLoaded('reviewer', fn (): ?array => $this->resource->reviewer === null ? null : [
                'id' => $this->resource->reviewer->id,
                'name' => $this->resource->reviewer->name,
                'email' => $this->resource->reviewer->email,
            ]),
            'review_note' => $this->resource->review_note,
            'conversation' => $this->whenLoaded(
                'refundConversation',
                fn (): ?array => $this->conversation($this->resource->refundConversation),
            ),
            'latest_ai_analysis' => $this->whenLoaded(
                'refundConversation',
                fn (): ?array => $this->latestAiAnalysis($this->resource->refundConversation),
            ),
            'refund' => $this->whenLoaded(
                'refund',
                fn (): ?array => $this->refund($this->resource->refund),
            ),
            'audit_timeline' => $this->auditTimeline(),
            'decided_at' => $decidedAt?->toISOString(),
            'created_at' => $createdAt?->toISOString(),
            'updated_at' => $updatedAt?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function conversation(?RefundConversation $conversation): ?array
    {
        if ($conversation === null) {
            return null;
        }

        $state = $conversation->state;
        $status = $conversation->status;
        $resolvedAt = $conversation->resolved_at;
        if (! $conversation->relationLoaded('messages')) {
            throw new LogicException('Refund request detail resources require loaded conversation messages.');
        }

        $messages = [];
        foreach ($conversation->messages as $message) {
            $messages[] = $this->message($message);
        }

        return [
            'id' => $conversation->id,
            'state' => $state->value,
            'status' => $status->value,
            'messages' => $messages,
            'resolved_at' => $resolvedAt?->toISOString(),
            'created_at' => $conversation->created_at?->toISOString(),
            'updated_at' => $conversation->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function message(ConversationMessage $message): array
    {
        $sender = $message->sender;

        return [
            'id' => $message->id,
            'sender' => $sender->value,
            'content' => $message->content,
            'metadata' => $message->metadata,
            'created_at' => $message->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestAiAnalysis(?RefundConversation $conversation): ?array
    {
        if ($conversation === null) {
            return null;
        }

        if (! $conversation->relationLoaded('latestAiAnalysis')) {
            throw new LogicException('Refund request detail resources require a loaded latest AI analysis.');
        }

        $analysis = $conversation->latestAiAnalysis;

        if ($analysis === null) {
            return null;
        }

        return [
            'id' => $analysis->id,
            'conversation_message_id' => $analysis->conversation_message_id,
            'confidence' => $analysis->confidence,
            'prompt_injection_detected' => $analysis->prompt_injection_detected,
            'conflicting_information' => $analysis->conflicting_information,
            'extracted_data' => $analysis->extracted_data,
            'created_at' => $analysis->created_at->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function refund(?Refund $refund): ?array
    {
        if ($refund === null) {
            return null;
        }

        $status = $refund->status;

        return [
            'id' => $refund->id,
            'amount_cents' => $refund->amount_cents,
            'status' => $status->value,
            'processor' => $refund->processor,
            'processor_reference' => $refund->processor_reference,
            'attempts' => $refund->attempts,
            'last_error' => $refund->last_error,
            'next_retry_at' => $refund->next_retry_at?->toISOString(),
            'processed_at' => $refund->processed_at?->toISOString(),
            'created_at' => $refund->created_at->toISOString(),
            'updated_at' => $refund->updated_at->toISOString(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function auditTimeline(): array
    {
        $entries = [];
        $conversation = $this->resource->refundConversation;
        $refund = $this->resource->refund;

        if ($conversation instanceof RefundConversation) {
            if (! $conversation->relationLoaded('auditLogs')) {
                throw new LogicException('Refund request detail resources require loaded conversation audit logs.');
            }

            $entries = array_merge(
                $entries,
                $this->auditEntries($conversation->auditLogs, 'conversation'),
            );
        }

        if (! $this->resource->relationLoaded('auditLogs')) {
            throw new LogicException('Refund request detail resources require loaded refund-request audit logs.');
        }

        $entries = array_merge(
            $entries,
            $this->auditEntries($this->resource->auditLogs, 'refund_request'),
        );

        if ($refund instanceof Refund) {
            if (! $refund->relationLoaded('auditLogs')) {
                throw new LogicException('Refund request detail resources require loaded refund audit logs.');
            }

            $entries = array_merge(
                $entries,
                $this->auditEntries($refund->auditLogs, 'refund'),
            );
        }

        usort($entries, static function (array $left, array $right): int {
            return [$left['created_at'], $left['id']] <=> [$right['created_at'], $right['id']];
        });

        return $entries;
    }

    /**
     * @param  Collection<int, AuditLog>  $auditLogs
     * @return list<array<string, mixed>>
     */
    private function auditEntries(Collection $auditLogs, string $subjectType): array
    {
        $entries = [];

        foreach ($auditLogs as $auditLog) {
            $actorType = $auditLog->actor_type;
            $event = $auditLog->event;

            $entries[] = [
                'id' => $auditLog->id,
                'actor_type' => $actorType->value,
                'actor_id' => $auditLog->actor_id,
                'subject_type' => $subjectType,
                'subject_id' => $auditLog->subject_id,
                'event' => $event->value,
                'created_at' => $auditLog->created_at->toISOString(),
            ];
        }

        return $entries;
    }
}
