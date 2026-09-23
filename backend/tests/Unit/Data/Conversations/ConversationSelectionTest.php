<?php

namespace Tests\Unit\Data\Conversations;

use App\Data\Conversations\ConversationSelection;
use App\Enums\ConversationSelectionType;
use App\Enums\Http\ApiErrorCode;
use App\Exceptions\Conversations\ConversationWorkflowException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConversationSelectionTest extends TestCase
{
    public function test_normalizes_supported_identifier_and_reason_selections(): void
    {
        $order = ConversationSelection::fromUntrusted('order', '42');
        $item = ConversationSelection::fromUntrusted('order_item', 84);
        $reason = ConversationSelection::fromUntrusted('refund_reason', 'damaged_item');

        $this->assertSame(ConversationSelectionType::Order, $order->type);
        $this->assertSame(42, $order->value);
        $this->assertSame(ConversationSelectionType::OrderItem, $item->type);
        $this->assertSame(84, $item->value);
        $this->assertSame(ConversationSelectionType::RefundReason, $reason->type);
        $this->assertSame('damaged_item', $reason->value);
    }

    #[DataProvider('invalidSelections')]
    public function test_rejects_unsupported_selection_types_and_values(mixed $type, mixed $value): void
    {
        try {
            ConversationSelection::fromUntrusted($type, $value);
            $this->fail('An invalid selection was accepted.');
        } catch (ConversationWorkflowException $exception) {
            $this->assertSame(ApiErrorCode::InvalidConversationSelection, $exception->errorCode());
            $this->assertSame(422, $exception->status());
            $this->assertArrayHasKey('field', $exception->details());
        }
    }

    /**
     * @return array<string, array{mixed, mixed}>
     */
    public static function invalidSelections(): array
    {
        return [
            'non-string type' => [1, 1],
            'unknown type' => ['unsupported', 1],
            'zero identifier' => ['order', 0],
            'negative identifier' => ['order_item', -1],
            'decimal identifier' => ['order', '1.5'],
            'leading-zero identifier' => ['open_existing_conversation', '01'],
            'unknown reason' => ['refund_reason', 'unknown'],
            'unsupported reason' => ['refund_reason', 'not_a_reason'],
        ];
    }
}
