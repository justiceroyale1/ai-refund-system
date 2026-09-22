<?php

namespace Tests\Feature\Services\Conversations;

use App\Enums\ConversationMessageTemplate;
use App\Enums\MessageSender;
use App\Models\RefundConversation;
use App\Services\Conversations\ConversationMessageService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ConversationMessageServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_persists_message_templates_and_raw_content(): void
    {
        $conversation = RefundConversation::factory()->create();
        $messages = app(ConversationMessageService::class);

        $assistantTemplate = $messages->assistant(
            $conversation,
            ConversationMessageTemplate::DuplicateItemDetected,
        );
        $systemTemplate = $messages->system(
            $conversation,
            ConversationMessageTemplate::DuplicateConversationResolved,
        );
        $rawAssistant = $messages->assistant(
            $conversation,
            'A provider-generated assistant response.',
        );

        $this->assertSame(MessageSender::Assistant, $assistantTemplate->sender);
        $this->assertSame(
            ConversationMessageTemplate::DuplicateItemDetected->value,
            $assistantTemplate->content,
        );
        $this->assertSame(MessageSender::System, $systemTemplate->sender);
        $this->assertSame(
            ConversationMessageTemplate::DuplicateConversationResolved->value,
            $systemTemplate->content,
        );
        $this->assertSame(MessageSender::Assistant, $rawAssistant->sender);
        $this->assertSame('A provider-generated assistant response.', $rawAssistant->content);
        $this->assertDatabaseCount('conversation_messages', 3);
    }
}
