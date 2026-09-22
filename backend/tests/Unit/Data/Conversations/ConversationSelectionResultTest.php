<?php

namespace Tests\Unit\Data\Conversations;

use App\Data\Conversations\ConversationSelectionResult;
use App\Enums\ConversationSelectionOutcome;
use App\Models\RefundConversation;
use Tests\TestCase;

class ConversationSelectionResultTest extends TestCase
{
    /**
     * @var list<array{type: string, value: int|string, label: string}>
     */
    private array $actions = [
        ['type' => 'order_item', 'value' => 42, 'label' => 'Keyboard'],
    ];

    public function test_creates_an_applied_result(): void
    {
        $conversation = new RefundConversation;

        $result = ConversationSelectionResult::applied($conversation, $this->actions);

        $this->assertSame($conversation, $result->conversation);
        $this->assertSame(ConversationSelectionOutcome::Applied, $result->outcome);
        $this->assertSame($this->actions, $result->actions);
        $this->assertNull($result->existingConversationId);
    }

    public function test_creates_a_duplicate_detected_result(): void
    {
        $conversation = new RefundConversation;

        $result = ConversationSelectionResult::duplicateDetected($conversation, 84, $this->actions);

        $this->assertSame($conversation, $result->conversation);
        $this->assertSame(ConversationSelectionOutcome::DuplicateDetected, $result->outcome);
        $this->assertSame($this->actions, $result->actions);
        $this->assertSame(84, $result->existingConversationId);
    }

    public function test_creates_an_existing_conversation_opened_result(): void
    {
        $conversation = new RefundConversation;

        $result = ConversationSelectionResult::existingConversationOpened($conversation, 84);

        $this->assertSame($conversation, $result->conversation);
        $this->assertSame(ConversationSelectionOutcome::ExistingConversationOpened, $result->outcome);
        $this->assertSame([], $result->actions);
        $this->assertSame(84, $result->existingConversationId);
    }

    public function test_creates_an_alternate_item_requested_result(): void
    {
        $conversation = new RefundConversation;

        $result = ConversationSelectionResult::alternateItemRequested($conversation, $this->actions);

        $this->assertSame($conversation, $result->conversation);
        $this->assertSame(ConversationSelectionOutcome::AlternateItemRequested, $result->outcome);
        $this->assertSame($this->actions, $result->actions);
        $this->assertNull($result->existingConversationId);
    }
}
