<?php

namespace App\Actions\Conversations;

use App\Data\AI\RefundAnalysisResult;
use App\Data\Conversations\ConversationSelection;
use App\Data\Conversations\ConversationSelectionResult;
use App\Enums\ConversationSelectionOutcome;
use App\Enums\ConversationSelectionType;
use App\Enums\RefundReason;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RefundConversation;
use App\Services\Conversations\ConversationQuickActions;
use App\Services\Conversations\ConversationRequirements;
use App\Services\Conversations\ConversationStateMachine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class ApplyConversationAnalysis
{
    public function __construct(
        private readonly ApplyConversationSelection $applyConversationSelection,
        private readonly ConversationStateMachine $stateMachine,
        private readonly ConversationRequirements $requirements,
        private readonly ConversationQuickActions $quickActions,
    ) {}

    public function handle(
        RefundConversation $conversation,
        RefundAnalysisResult $analysis,
    ): ConversationSelectionResult {
        $this->synchronizeState($conversation);

        if ($conversation->order_id === null && $analysis->orderReference !== null) {
            $order = $this->resolveOrder($conversation, $analysis->orderReference);

            if ($order !== null) {
                $conversation = $this->applyConversationSelection->handle(
                    $conversation,
                    ConversationSelection::fromUntrusted(
                        ConversationSelectionType::Order->value,
                        $order->getKey(),
                    ),
                )->conversation;
            }
        }

        $itemResult = null;

        if (
            $conversation->order_item_id === null
            && $conversation->order_id !== null
            && $analysis->orderItemHint !== null
        ) {
            $orderItem = $this->resolveOrderItem($conversation, $analysis->orderItemHint);

            if ($orderItem !== null) {
                $itemResult = $this->applyConversationSelection->handle(
                    $conversation,
                    ConversationSelection::fromUntrusted(
                        ConversationSelectionType::OrderItem->value,
                        $orderItem->getKey(),
                    ),
                );
                $conversation = $itemResult->conversation;
            }
        }

        if (
            ($conversation->reason === null || $conversation->reason === RefundReason::Unknown)
            && $analysis->reason !== null
            && $analysis->reason !== RefundReason::Unknown
        ) {
            $conversation->setAttribute('reason', $analysis->reason);
        }

        if (
            ! $this->requirements->hasMeaningfulDetails($conversation->reason_details)
            && $analysis->reasonDetails !== null
        ) {
            $conversation->reason_details = $analysis->reasonDetails;
        }

        $this->stateMachine->advanceTo(
            $conversation,
            $this->requirements->nextState($conversation),
        );
        $conversation->save();
        $conversation = $conversation->refresh();

        if (
            $itemResult?->outcome === ConversationSelectionOutcome::DuplicateDetected
            && $itemResult->existingConversationId !== null
        ) {
            return ConversationSelectionResult::duplicateDetected(
                $conversation,
                $itemResult->existingConversationId,
                $itemResult->actions,
            );
        }

        return ConversationSelectionResult::applied(
            $conversation,
            $this->quickActions->for($conversation),
        );
    }

    private function synchronizeState(RefundConversation $conversation): void
    {
        $this->stateMachine->advanceTo(
            $conversation,
            $this->requirements->nextState($conversation),
        );
        $conversation->save();
    }

    private function resolveOrder(RefundConversation $conversation, string $reference): ?Order
    {
        return Order::query()
            ->where('customer_id', $conversation->customer_id)
            ->where('status', 'delivered')
            ->whereNotNull('delivered_at')
            ->whereRaw('LOWER(reference) = ?', [Str::lower(trim($reference))])
            ->first();
    }

    private function resolveOrderItem(RefundConversation $conversation, string $hint): ?OrderItem
    {
        $normalizedHint = $this->normalize($hint);

        /** @var Collection<int, OrderItem> $matches */
        $matches = OrderItem::query()
            ->where('order_id', $conversation->order_id)
            ->whereHas('order', function (Builder $query) use ($conversation): void {
                $query
                    ->where('customer_id', $conversation->customer_id)
                    ->where('status', 'delivered')
                    ->whereNotNull('delivered_at');
            })
            ->orderBy('id')
            ->get(['id', 'order_id', 'sku', 'name'])
            ->filter(fn (OrderItem $orderItem): bool => in_array(
                $normalizedHint,
                [$this->normalize($orderItem->name), $this->normalize($orderItem->sku)],
                true,
            ));

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->squish()->lower()->toString();
    }
}
