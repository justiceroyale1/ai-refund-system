<?php

namespace Tests\Unit\Conversation;

use App\Enums\ConversationState;
use App\Enums\ConversationStatus;
use App\Enums\MessageSender;
use App\Enums\RefundReason;
use App\Models\AiAnalysis;
use App\Models\ConversationMessage;
use App\Models\OrderItem;
use App\Models\RefundConversation;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ConversationModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_models_expose_the_complete_conversation_relationship_graph(): void
    {
        $item = OrderItem::factory()->create();
        $conversation = RefundConversation::factory()->forOrderItem($item)->create();
        $message = ConversationMessage::factory()->for($conversation)->create();
        $analysis = AiAnalysis::factory()
            ->for($conversation, 'refundConversation')
            ->for($message, 'conversationMessage')
            ->create();

        $this->assertTrue($item->order->customer->refundConversations()->whereKey($conversation)->exists());
        $this->assertTrue($item->order->refundConversations()->whereKey($conversation)->exists());
        $this->assertTrue($item->refundConversations()->whereKey($conversation)->exists());
        $this->assertTrue($conversation->customer->is($item->order->customer));
        $this->assertTrue($conversation->order->is($item->order));
        $this->assertTrue($conversation->orderItem->is($item));
        $this->assertTrue($conversation->messages()->whereKey($message)->exists());
        $this->assertTrue($conversation->aiAnalyses()->whereKey($analysis)->exists());
        $this->assertTrue($message->refundConversation->is($conversation));
        $this->assertTrue($message->aiAnalyses()->whereKey($analysis)->exists());
        $this->assertTrue($analysis->refundConversation->is($conversation));
        $this->assertTrue($analysis->conversationMessage->is($message));
    }

    public function test_models_cast_domain_values_and_structured_fields_to_safe_types(): void
    {
        $item = OrderItem::factory()->create();
        $conversation = RefundConversation::factory()
            ->forOrderItem($item)
            ->damagedItem()
            ->resolved()
            ->create();
        $metadata = ['actions' => [['type' => 'choose_another_item']]];
        $message = ConversationMessage::factory()->for($conversation)->create([
            'metadata' => $metadata,
        ]);
        $extractedData = [
            'intent' => 'refund',
            'reason' => 'damaged_item',
        ];
        $analysis = AiAnalysis::factory()
            ->for($conversation, 'refundConversation')
            ->for($message, 'conversationMessage')
            ->create([
                'confidence' => 87,
                'prompt_injection_detected' => true,
                'conflicting_information' => false,
                'extracted_data' => $extractedData,
                'raw_response' => '{"intent":"refund"}',
            ]);

        $this->assertSame(ConversationState::Resolved, $conversation->state);
        $this->assertSame(ConversationStatus::Resolved, $conversation->status);
        $this->assertSame(RefundReason::DamagedItem, $conversation->reason);
        $this->assertInstanceOf(CarbonInterface::class, $conversation->resolved_at);
        $this->assertSame(MessageSender::Customer, $message->sender);
        $this->assertSame($metadata, $message->metadata);
        $this->assertSame(87, $analysis->confidence);
        $this->assertTrue($analysis->prompt_injection_detected);
        $this->assertFalse($analysis->conflicting_information);
        $this->assertSame($extractedData, $analysis->extracted_data);
        $this->assertSame('{"intent":"refund"}', $analysis->raw_response);
        $this->assertInstanceOf(CarbonInterface::class, $analysis->created_at);
    }

    public function test_factories_create_coherent_defaults_and_generated_message_states(): void
    {
        $analysis = AiAnalysis::factory()->create();
        $assistantMessage = ConversationMessage::factory()->assistant()->create();
        $systemMessage = ConversationMessage::factory()->system()->create();

        $this->assertSame(
            $analysis->conversationMessage->refund_conversation_id,
            $analysis->refund_conversation_id,
        );
        $this->assertSame(ConversationState::Started, $analysis->refundConversation->state);
        $this->assertSame(ConversationStatus::Active, $analysis->refundConversation->status);
        $this->assertNull($analysis->refundConversation->reason);
        $this->assertSame(MessageSender::Assistant, $assistantMessage->sender);
        $this->assertNull($assistantMessage->client_message_id);
        $this->assertSame(MessageSender::System, $systemMessage->sender);
        $this->assertNull($systemMessage->client_message_id);
    }

    public function test_enums_expose_the_documented_persisted_values(): void
    {
        $this->assertSame([
            'started',
            'identifying_order',
            'identifying_item',
            'collecting_reason',
            'collecting_details',
            'evaluating',
            'resolved',
        ], array_column(ConversationState::cases(), 'value'));
        $this->assertSame(['active', 'resolved'], array_column(ConversationStatus::cases(), 'value'));
        $this->assertSame(['customer', 'assistant', 'system'], array_column(MessageSender::cases(), 'value'));
        $this->assertSame([
            'damaged_item',
            'incorrect_item',
            'missing_item',
            'changed_mind',
            'other',
            'unknown',
        ], array_column(RefundReason::cases(), 'value'));
    }
}
