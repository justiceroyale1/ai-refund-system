<?php

namespace App\Services\Conversations;

use App\Enums\ConversationSelectionType;
use App\Enums\ConversationState;
use App\Enums\RefundReason;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RefundConversation;

final class ConversationQuickActions
{
    /**
     * @return list<array{type: string, value: int|string, label: string}>
     */
    public function for(RefundConversation $conversation, ?int $excludedOrderItemId = null): array
    {
        $state = $conversation->state;

        return match ($state) {
            ConversationState::IdentifyingOrder => $this->orders($conversation),
            ConversationState::IdentifyingItem => $this->orderItems($conversation, $excludedOrderItemId),
            ConversationState::CollectingReason => $this->reasons(),
            default => [],
        };
    }

    /**
     * @return list<array{type: string, value: int, label: string}>
     */
    public function duplicate(RefundConversation $existingConversation, OrderItem $orderItem): array
    {
        return [
            [
                'type' => ConversationSelectionType::OpenExistingConversation->value,
                'value' => (int) $existingConversation->getKey(),
                'label' => 'Open existing conversation',
            ],
            [
                'type' => ConversationSelectionType::ChooseAnotherItem->value,
                'value' => (int) $orderItem->getKey(),
                'label' => 'Choose another item',
            ],
        ];
    }

    /**
     * @return list<array{type: string, value: int, label: string}>
     */
    private function orders(RefundConversation $conversation): array
    {
        return array_values(Order::query()
            ->where('customer_id', $conversation->customer_id)
            ->where('status', 'delivered')
            ->whereNotNull('delivered_at')
            ->orderByDesc('delivered_at')
            ->orderByDesc('id')
            ->get(['id', 'reference'])
            ->map(fn (Order $order): array => [
                'type' => ConversationSelectionType::Order->value,
                'value' => (int) $order->getKey(),
                'label' => $order->reference,
            ])
            ->values()
            ->all());
    }

    /**
     * @return list<array{type: string, value: int, label: string}>
     */
    private function orderItems(RefundConversation $conversation, ?int $excludedOrderItemId): array
    {
        if ($conversation->order_id === null) {
            return [];
        }

        return array_values(OrderItem::query()
            ->where('order_id', $conversation->order_id)
            ->when(
                $excludedOrderItemId !== null,
                fn ($query) => $query->whereKeyNot($excludedOrderItemId),
            )
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (OrderItem $orderItem): array => [
                'type' => ConversationSelectionType::OrderItem->value,
                'value' => (int) $orderItem->getKey(),
                'label' => $orderItem->name,
            ])
            ->values()
            ->all());
    }

    /**
     * @return list<array{type: string, value: string, label: string}>
     */
    private function reasons(): array
    {
        return array_map(
            static fn (RefundReason $reason): array => [
                'type' => ConversationSelectionType::RefundReason->value,
                'value' => $reason->value,
                'label' => $reason->label(),
            ],
            array_filter(
                RefundReason::cases(),
                static fn (RefundReason $reason): bool => $reason !== RefundReason::Unknown,
            ),
        );
    }
}
