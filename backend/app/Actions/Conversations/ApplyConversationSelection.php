<?php

namespace App\Actions\Conversations;

use App\Data\Conversations\ConversationSelection;
use App\Data\Conversations\ConversationSelectionResult;
use App\Enums\AuditActorType;
use App\Enums\ConversationMessageTemplate;
use App\Enums\ConversationSelectionType;
use App\Enums\ConversationState;
use App\Enums\ConversationStatus;
use App\Enums\MessageSender;
use App\Enums\RefundReason;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RefundConversation;
use App\Services\Conversations\ConversationMessageService;
use App\Services\Conversations\ConversationQuickActions;
use App\Services\Conversations\ConversationRequirements;
use App\Services\Conversations\ConversationStateMachine;
use App\Services\Conversations\ConversationWorkflowException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class ApplyConversationSelection
{
    public function __construct(
        private readonly ConversationStateMachine $stateMachine,
        private readonly ConversationRequirements $requirements,
        private readonly ConversationQuickActions $quickActions,
        private readonly ConversationMessageService $messages,
    ) {}

    public function handle(
        RefundConversation $conversation,
        ConversationSelection $selection,
    ): ConversationSelectionResult {
        return match ($selection->type) {
            ConversationSelectionType::Order => $this->selectOrder($conversation, $selection->value),
            ConversationSelectionType::OrderItem => $this->selectOrderItem($conversation, $selection->value),
            ConversationSelectionType::RefundReason => $this->selectRefundReason($conversation, $selection->value),
            ConversationSelectionType::OpenExistingConversation => $this->openExistingConversation($conversation, $selection->value),
            ConversationSelectionType::ChooseAnotherItem => $this->chooseAnotherItem($conversation, $selection->value),
        };
    }

    private function selectOrder(RefundConversation $conversation, int|string $value): ConversationSelectionResult
    {
        return DB::transaction(function () use ($conversation, $value): ConversationSelectionResult {
            $lockedConversation = $this->lockConversation($conversation);
            $this->assertConversationIsActiveAndInState($lockedConversation, ConversationState::IdentifyingOrder);
            $orderId = $this->integerValue($value);

            $order = Order::query()
                ->whereKey($orderId)
                ->where('customer_id', $lockedConversation->customer_id)
                ->where('status', 'delivered')
                ->whereNotNull('delivered_at')
                ->first();

            if ($order === null) {
                throw ConversationWorkflowException::invalidSelection(
                    'The selected order is not available for this refund conversation.',
                    ['field' => 'selection.value'],
                );
            }

            $lockedConversation->order_id = $order->getKey();
            $this->stateMachine->transition(
                $lockedConversation,
                $this->requirements->nextState($lockedConversation),
            );
            $lockedConversation->save();

            $lockedConversation->auditLogs()->create([
                'actor_type' => AuditActorType::Customer,
                'actor_id' => $lockedConversation->customer_id,
                'event' => 'conversation.order_identified',
                'metadata' => [
                    'order_id' => $order->getKey(),
                    'order_reference' => $order->reference,
                ],
            ]);

            return $this->appliedResult($lockedConversation);
        });
    }

    private function selectOrderItem(RefundConversation $conversation, int|string $value): ConversationSelectionResult
    {
        return DB::transaction(function () use ($conversation, $value): ConversationSelectionResult {
            $lockedConversation = $this->lockConversation($conversation);
            $this->assertConversationIsActiveAndInState($lockedConversation, ConversationState::IdentifyingItem);
            $orderItemId = $this->integerValue($value);
            $orderItem = $this->lockSelectableOrderItem($lockedConversation, $orderItemId);

            $existingConversation = RefundConversation::query()
                ->where('customer_id', $lockedConversation->customer_id)
                ->where('order_item_id', $orderItem->getKey())
                ->where('status', ConversationStatus::Active->value)
                ->whereKeyNot($lockedConversation->getKey())
                ->lockForUpdate()
                ->first();

            if ($existingConversation !== null) {
                $actions = $this->quickActions->duplicate($existingConversation, $orderItem);

                $this->messages->assistant(
                    $lockedConversation,
                    ConversationMessageTemplate::DuplicateItemDetected,
                    $actions,
                );

                return ConversationSelectionResult::duplicateDetected(
                    $lockedConversation->refresh(),
                    (int) $existingConversation->getKey(),
                    $actions,
                );
            }

            $lockedConversation->order_item_id = $orderItem->getKey();
            $this->stateMachine->transition(
                $lockedConversation,
                $this->requirements->nextState($lockedConversation),
            );
            $lockedConversation->save();

            $lockedConversation->auditLogs()->create([
                'actor_type' => AuditActorType::Customer,
                'actor_id' => $lockedConversation->customer_id,
                'event' => 'conversation.item_identified',
                'metadata' => [
                    'order_item_id' => $orderItem->getKey(),
                    'sku' => $orderItem->sku,
                ],
            ]);

            return $this->appliedResult($lockedConversation);
        });
    }

    private function selectRefundReason(RefundConversation $conversation, int|string $value): ConversationSelectionResult
    {
        return DB::transaction(function () use ($conversation, $value): ConversationSelectionResult {
            $lockedConversation = $this->lockConversation($conversation);
            $this->assertConversationIsActiveAndInState($lockedConversation, ConversationState::CollectingReason);
            $reason = is_string($value) ? RefundReason::tryFrom($value) : null;

            if ($reason === null || $reason === RefundReason::Unknown) {
                throw ConversationWorkflowException::invalidSelection(
                    'The selected refund reason is not supported.',
                    ['field' => 'selection.value'],
                );
            }

            $lockedConversation->setAttribute('reason', $reason);
            $this->stateMachine->transition(
                $lockedConversation,
                $this->requirements->nextState($lockedConversation),
            );
            $lockedConversation->save();

            return $this->appliedResult($lockedConversation);
        });
    }

    private function openExistingConversation(RefundConversation $conversation, int|string $value): ConversationSelectionResult
    {
        return DB::transaction(function () use ($conversation, $value): ConversationSelectionResult {
            $lockedConversation = $this->lockConversation($conversation);
            $this->assertConversationIsActiveAndInState($lockedConversation, ConversationState::IdentifyingItem);
            $existingConversationId = $this->integerValue($value);
            $this->assertActionWasOffered(
                $lockedConversation,
                ConversationSelectionType::OpenExistingConversation,
                $existingConversationId,
            );

            $existingConversation = RefundConversation::query()
                ->whereKey($existingConversationId)
                ->whereKeyNot($lockedConversation->getKey())
                ->where('customer_id', $lockedConversation->customer_id)
                ->where('order_id', $lockedConversation->order_id)
                ->whereNotNull('order_item_id')
                ->where('status', ConversationStatus::Active->value)
                ->lockForUpdate()
                ->first();

            if ($existingConversation === null) {
                throw ConversationWorkflowException::invalidSelection(
                    'The existing refund conversation is no longer available.',
                    ['field' => 'selection.value'],
                );
            }

            $this->stateMachine->resolveAsDuplicate($lockedConversation);
            $lockedConversation->save();

            $this->messages->system(
                $lockedConversation,
                ConversationMessageTemplate::DuplicateConversationResolved,
                [
                    'existing_conversation_id' => $existingConversation->getKey(),
                ],
            );

            $lockedConversation->auditLogs()->create([
                'actor_type' => AuditActorType::Customer,
                'actor_id' => $lockedConversation->customer_id,
                'event' => 'conversation.duplicate_resolved',
                'metadata' => [
                    'existing_conversation_id' => $existingConversation->getKey(),
                    'order_item_id' => $existingConversation->order_item_id,
                ],
            ]);

            return ConversationSelectionResult::existingConversationOpened(
                $lockedConversation->refresh(),
                (int) $existingConversation->getKey(),
            );
        });
    }

    private function chooseAnotherItem(RefundConversation $conversation, int|string $value): ConversationSelectionResult
    {
        return DB::transaction(function () use ($conversation, $value): ConversationSelectionResult {
            $lockedConversation = $this->lockConversation($conversation);
            $this->assertConversationIsActiveAndInState($lockedConversation, ConversationState::IdentifyingItem);
            $excludedOrderItemId = $this->integerValue($value);
            $this->assertActionWasOffered(
                $lockedConversation,
                ConversationSelectionType::ChooseAnotherItem,
                $excludedOrderItemId,
            );
            $this->lockSelectableOrderItem($lockedConversation, $excludedOrderItemId);
            $actions = $this->quickActions->for($lockedConversation, $excludedOrderItemId);

            $this->messages->assistant(
                $lockedConversation,
                ConversationMessageTemplate::AlternateItemRequested,
                $actions,
            );

            return ConversationSelectionResult::alternateItemRequested(
                $lockedConversation->refresh(),
                $actions,
            );
        });
    }

    private function lockConversation(RefundConversation $conversation): RefundConversation
    {
        return RefundConversation::query()
            ->whereKey($conversation->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertConversationIsActiveAndInState(
        RefundConversation $conversation,
        ConversationState $expectedState,
    ): void {
        $status = $conversation->status;
        $actualState = $conversation->state;

        if ($status === ConversationStatus::Resolved) {
            throw ConversationWorkflowException::alreadyResolved();
        }

        if ($actualState !== $expectedState) {
            throw ConversationWorkflowException::invalidTransition($actualState, $expectedState);
        }
    }

    private function lockSelectableOrderItem(
        RefundConversation $conversation,
        int $orderItemId,
    ): OrderItem {
        $orderItem = OrderItem::query()
            ->whereKey($orderItemId)
            ->where('order_id', $conversation->order_id)
            ->whereHas('order', function (Builder $query) use ($conversation): void {
                $query
                    ->where('customer_id', $conversation->customer_id)
                    ->where('status', 'delivered')
                    ->whereNotNull('delivered_at');
            })
            ->lockForUpdate()
            ->first();

        if ($orderItem === null) {
            throw ConversationWorkflowException::invalidSelection(
                'The selected item is not available for this refund conversation.',
                ['field' => 'selection.value'],
            );
        }

        return $orderItem;
    }

    private function assertActionWasOffered(
        RefundConversation $conversation,
        ConversationSelectionType $selectionType,
        int $value,
    ): void {
        $latestMessage = $conversation->messages()
            ->where('sender', MessageSender::Assistant->value)
            ->latest('created_at')
            ->latest('id')
            ->first();
        $metadata = $latestMessage?->metadata;

        if (! is_array($metadata)) {
            throw ConversationWorkflowException::invalidSelection(
                'The selected conversation action is no longer available.',
                ['field' => 'selection'],
            );
        }

        $actions = $metadata['actions'] ?? null;

        if (! is_array($actions)) {
            throw ConversationWorkflowException::invalidSelection(
                'The selected conversation action is no longer available.',
                ['field' => 'selection'],
            );
        }

        foreach ($actions as $action) {
            if (
                is_array($action)
                && ($action['type'] ?? null) === $selectionType->value
                && ($action['value'] ?? null) === $value
            ) {
                return;
            }
        }

        throw ConversationWorkflowException::invalidSelection(
            'The selected conversation action is no longer available.',
            ['field' => 'selection'],
        );
    }

    private function appliedResult(RefundConversation $conversation): ConversationSelectionResult
    {
        return ConversationSelectionResult::applied(
            $conversation->refresh(),
            $this->quickActions->for($conversation),
        );
    }

    private function integerValue(int|string $value): int
    {
        if (! is_int($value)) {
            throw ConversationWorkflowException::invalidSelection(
                'The selection value must be a positive integer identifier.',
                ['field' => 'selection.value'],
            );
        }

        return $value;
    }
}
