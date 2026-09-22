<?php

namespace Tests\Feature\Actions\Conversations;

use App\Actions\Conversations\SubmitConversationMessage;
use App\Data\Conversations\ConversationSelection;
use App\Enums\ConversationState;
use App\Enums\MessageSender;
use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RefundConversation;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class ConcurrentConversationMessageTest extends TestCase
{
    use DatabaseMigrations;

    public function test_concurrent_duplicate_submissions_persist_and_apply_the_message_once(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL is required to verify row-lock concurrency.');
        }

        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('The pcntl extension is required to verify process concurrency.');
        }

        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create();
        OrderItem::factory()->for($order)->create();
        $conversation = RefundConversation::factory()->for($customer)->create([
            'state' => ConversationState::IdentifyingOrder,
        ]);
        $selection = ConversationSelection::fromUntrusted('order', $order->id);
        $clientMessageId = '8f5b5ff1-1e52-45e7-b910-d7f3bfac00e4';
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if ($sockets === false) {
            throw new RuntimeException('Unable to create the concurrency coordination socket.');
        }

        DB::disconnect();
        $processId = pcntl_fork();

        if ($processId === -1) {
            throw new RuntimeException('Unable to fork the concurrent message process.');
        }

        if ($processId === 0) {
            fclose($sockets[0]);
            DB::purge();
            fread($sockets[1], 1);

            try {
                $result = app(SubmitConversationMessage::class)->handle(
                    RefundConversation::query()->findOrFail($conversation->id),
                    $clientMessageId,
                    $order->reference,
                    $selection,
                );
                fwrite($sockets[1], $result->state->value);
                fclose($sockets[1]);
                exit(0);
            } catch (Throwable $exception) {
                fwrite($sockets[1], 'error:'.$exception->getMessage());
                fclose($sockets[1]);
                exit(1);
            }
        }

        fclose($sockets[1]);
        DB::purge();
        fwrite($sockets[0], '1');
        $parentResult = app(SubmitConversationMessage::class)->handle(
            RefundConversation::query()->findOrFail($conversation->id),
            $clientMessageId,
            $order->reference,
            $selection,
        );
        pcntl_waitpid($processId, $processStatus);
        $childState = stream_get_contents($sockets[0]);
        fclose($sockets[0]);
        DB::purge();

        $this->assertTrue(pcntl_wifexited($processStatus));
        $this->assertSame(0, pcntl_wexitstatus($processStatus), $childState);
        $this->assertSame(ConversationState::IdentifyingItem, $parentResult->state);
        $this->assertSame(ConversationState::IdentifyingItem->value, $childState);
        $this->assertSame(1, ConversationMessage::query()
            ->where('refund_conversation_id', $conversation->id)
            ->where('sender', MessageSender::Customer->value)
            ->count());
        $this->assertSame(1, ConversationMessage::query()
            ->where('refund_conversation_id', $conversation->id)
            ->where('sender', MessageSender::Assistant->value)
            ->count());
        $this->assertSame(1, $conversation->auditLogs()
            ->where('event', 'conversation.order_identified')
            ->count());
        $this->assertDatabaseHas('refund_conversations', [
            'id' => $conversation->id,
            'order_id' => $order->id,
            'state' => ConversationState::IdentifyingItem->value,
        ]);
    }
}
