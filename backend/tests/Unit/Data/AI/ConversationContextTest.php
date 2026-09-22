<?php

namespace Tests\Unit\Data\AI;

use App\Data\AI\ConversationContext;
use App\Data\AI\ConversationContextMessage;
use App\Enums\ConversationState;
use App\Enums\MessageSender;
use App\Enums\RefundReason;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConversationContextTest extends TestCase
{
    public function test_holds_only_typed_conversation_context(): void
    {
        $message = new ConversationContextMessage(
            MessageSender::Customer,
            'The keyboard arrived with two broken keys.',
        );

        $context = new ConversationContext(
            state: ConversationState::CollectingDetails,
            orderReference: 'ORD-1042',
            orderItemName: 'Mechanical Keyboard',
            reason: RefundReason::DamagedItem,
            reasonDetails: 'Two keys were broken.',
            messages: [$message],
        );

        $this->assertSame(ConversationState::CollectingDetails, $context->state);
        $this->assertSame('ORD-1042', $context->orderReference);
        $this->assertSame('Mechanical Keyboard', $context->orderItemName);
        $this->assertSame(RefundReason::DamagedItem, $context->reason);
        $this->assertSame('Two keys were broken.', $context->reasonDetails);
        $this->assertSame([$message], $context->messages);
    }

    public function test_rejects_blank_known_context_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ConversationContext(
            state: ConversationState::IdentifyingItem,
            orderReference: '   ',
        );
    }

    #[DataProvider('boundedContextValues')]
    public function test_accepts_context_values_at_the_shared_maximum_lengths(
        array $values,
        string $property,
        string $expected,
    ): void {
        $context = new ConversationContext(...[
            'state' => ConversationState::IdentifyingItem,
            ...$values,
        ]);

        $this->assertSame($expected, $context->{$property});
    }

    #[DataProvider('oversizedContextValues')]
    public function test_rejects_context_values_that_exceed_shared_maximum_lengths(array $values): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ConversationContext(...[
            'state' => ConversationState::IdentifyingItem,
            ...$values,
        ]);
    }

    public function test_rejects_untyped_context_messages(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ConversationContext(
            state: ConversationState::Started,
            messages: [['sender' => 'customer', 'content' => 'I need a refund.']],
        );
    }

    public function test_rejects_blank_context_message_content(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ConversationContextMessage(MessageSender::Customer, '');
    }

    /**
     * @return array<string, array{array<string, string>, string, string}>
     */
    public static function boundedContextValues(): array
    {
        $orderReference = str_repeat('R', 32);
        $orderItemName = str_repeat('I', 255);
        $reasonDetails = str_repeat('D', 4000);

        return [
            'order reference' => [['orderReference' => $orderReference], 'orderReference', $orderReference],
            'order item name' => [['orderItemName' => $orderItemName], 'orderItemName', $orderItemName],
            'reason details' => [['reasonDetails' => $reasonDetails], 'reasonDetails', $reasonDetails],
        ];
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function oversizedContextValues(): array
    {
        return [
            'order reference' => [['orderReference' => str_repeat('R', 33)]],
            'order item name' => [['orderItemName' => str_repeat('I', 256)]],
            'reason details' => [['reasonDetails' => str_repeat('D', 4001)]],
        ];
    }
}
