<?php

namespace Tests\Unit\Conversation;

use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RefundConversation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConversationSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_schema_contains_conversation_and_ai_columns_with_query_indexes(): void
    {
        $this->assertTrue(Schema::hasColumns('refund_conversations', [
            'id',
            'customer_id',
            'order_id',
            'order_item_id',
            'state',
            'reason',
            'reason_details',
            'status',
            'resolved_at',
            'created_at',
            'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('conversation_messages', [
            'id',
            'refund_conversation_id',
            'client_message_id',
            'sender',
            'content',
            'metadata',
            'created_at',
            'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('ai_analyses', [
            'id',
            'refund_conversation_id',
            'conversation_message_id',
            'provider',
            'model',
            'prompt_version',
            'confidence',
            'prompt_injection_detected',
            'conflicting_information',
            'extracted_data',
            'raw_response',
            'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('ai_analyses', 'updated_at'));

        $conversationIndexes = array_column(Schema::getIndexes('refund_conversations'), 'name');
        $messageIndexes = array_column(Schema::getIndexes('conversation_messages'), 'name');
        $analysisIndexes = array_column(Schema::getIndexes('ai_analyses'), 'name');

        $this->assertContains('refund_conversations_customer_id_status_updated_at_index', $conversationIndexes);
        $this->assertContains('refund_conversations_order_id_index', $conversationIndexes);
        $this->assertContains('refund_conversations_order_item_id_index', $conversationIndexes);
        $this->assertContains('refund_conversations_active_customer_item_unique', $conversationIndexes);
        $this->assertContains('conversation_messages_refund_conversation_id_created_at_index', $messageIndexes);
        $this->assertContains('conversation_messages_customer_client_unique', $messageIndexes);
        $this->assertContains('ai_analyses_refund_conversation_id_created_at_index', $analysisIndexes);
        $this->assertContains('ai_analyses_conversation_message_id_index', $analysisIndexes);
    }

    public function test_rejects_duplicate_active_conversations_for_the_same_customer_and_item(): void
    {
        $item = OrderItem::factory()->create();
        RefundConversation::factory()->forOrderItem($item)->create();

        $this->expectException(QueryException::class);

        RefundConversation::factory()->forOrderItem($item)->create();
    }

    public function test_permits_active_conversations_for_different_items_owned_by_the_same_customer(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create();
        $firstItem = OrderItem::factory()->for($order)->create();
        $secondItem = OrderItem::factory()->for($order)->create();

        RefundConversation::factory()->forOrderItem($firstItem)->create();
        RefundConversation::factory()->forOrderItem($secondItem)->create();

        $this->assertSame(2, $customer->refundConversations()->count());
    }

    public function test_permits_multiple_active_unbound_conversations_for_the_same_customer(): void
    {
        $customer = Customer::factory()->create();

        RefundConversation::factory()->count(2)->for($customer)->create();

        $this->assertSame(2, $customer->refundConversations()->count());
    }

    public function test_permits_a_new_active_conversation_after_an_item_conversation_is_resolved(): void
    {
        $item = OrderItem::factory()->create();

        RefundConversation::factory()->forOrderItem($item)->resolved()->create();
        $activeConversation = RefundConversation::factory()->forOrderItem($item)->create();

        $this->assertModelExists($activeConversation);
        $this->assertSame(2, RefundConversation::query()->where('order_item_id', $item->id)->count());
    }

    public function test_rejects_duplicate_customer_message_uuid_within_one_conversation(): void
    {
        $conversation = RefundConversation::factory()->create();
        $clientMessageId = '6f92fcbb-b660-4fba-b07f-8329381da397';
        ConversationMessage::factory()->for($conversation)->create([
            'client_message_id' => $clientMessageId,
        ]);

        $this->expectException(QueryException::class);

        ConversationMessage::factory()->for($conversation)->create([
            'client_message_id' => $clientMessageId,
        ]);
    }

    public function test_permits_the_same_customer_message_uuid_in_different_conversations(): void
    {
        $customer = Customer::factory()->create();
        $firstConversation = RefundConversation::factory()->for($customer)->create();
        $secondConversation = RefundConversation::factory()->for($customer)->create();
        $clientMessageId = '76f06743-b329-49a6-b060-a2aa77d34492';

        ConversationMessage::factory()->for($firstConversation)->create([
            'client_message_id' => $clientMessageId,
        ]);
        ConversationMessage::factory()->for($secondConversation)->create([
            'client_message_id' => $clientMessageId,
        ]);

        $this->assertSame(2, ConversationMessage::query()->where('client_message_id', $clientMessageId)->count());
    }

    public function test_permits_multiple_generated_messages_without_client_uuids(): void
    {
        $conversation = RefundConversation::factory()->create();

        ConversationMessage::factory()->count(2)->for($conversation)->assistant()->create();
        ConversationMessage::factory()->count(2)->for($conversation)->system()->create();

        $this->assertSame(4, $conversation->messages()->count());
    }

    public function test_database_string_columns_do_not_enforce_application_enum_membership(): void
    {
        $customer = Customer::factory()->create();
        $conversationId = DB::table('refund_conversations')->insertGetId([
            'customer_id' => $customer->id,
            'state' => 'future_state',
            'reason' => 'future_reason',
            'status' => 'future_status',
        ]);

        DB::table('conversation_messages')->insert([
            'refund_conversation_id' => $conversationId,
            'sender' => 'future_sender',
            'content' => 'Database enum membership is intentionally application-managed.',
        ]);

        $this->assertDatabaseHas('refund_conversations', [
            'id' => $conversationId,
            'state' => 'future_state',
            'reason' => 'future_reason',
            'status' => 'future_status',
        ]);
        $this->assertDatabaseHas('conversation_messages', [
            'refund_conversation_id' => $conversationId,
            'sender' => 'future_sender',
        ]);
    }
}
