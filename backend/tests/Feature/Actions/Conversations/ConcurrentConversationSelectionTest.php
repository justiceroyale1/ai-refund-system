<?php

namespace Tests\Feature\Actions\Conversations;

use App\Actions\Conversations\ApplyConversationSelection;
use App\Data\Conversations\ConversationSelection;
use App\Enums\ConversationSelectionOutcome;
use App\Enums\ConversationState;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RefundConversation;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class ConcurrentConversationSelectionTest extends TestCase
{
    use DatabaseMigrations;

    public function test_concurrent_item_binding_produces_one_binding_and_one_duplicate_result(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL is required to verify row-lock concurrency.');
        }

        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('The pcntl extension is required to verify process concurrency.');
        }

        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create();
        $item = OrderItem::factory()->for($order)->create();
        $firstConversation = RefundConversation::factory()->for($customer)->create([
            'order_id' => $order->id,
            'state' => ConversationState::IdentifyingItem,
        ]);
        $secondConversation = RefundConversation::factory()->for($customer)->create([
            'order_id' => $order->id,
            'state' => ConversationState::IdentifyingItem,
        ]);
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if ($sockets === false) {
            throw new RuntimeException('Unable to create the concurrency coordination socket.');
        }

        DB::disconnect();
        $processId = pcntl_fork();

        if ($processId === -1) {
            throw new RuntimeException('Unable to fork the concurrent selection process.');
        }

        if ($processId === 0) {
            fclose($sockets[0]);
            DB::purge();
            fread($sockets[1], 1);

            try {
                $result = app(ApplyConversationSelection::class)->handle(
                    RefundConversation::query()->findOrFail($secondConversation->id),
                    ConversationSelection::fromUntrusted('order_item', $item->id),
                );
                fwrite($sockets[1], $result->outcome->value);
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
        $parentResult = app(ApplyConversationSelection::class)->handle(
            RefundConversation::query()->findOrFail($firstConversation->id),
            ConversationSelection::fromUntrusted('order_item', $item->id),
        );
        pcntl_waitpid($processId, $processStatus);
        $childOutcome = stream_get_contents($sockets[0]);
        fclose($sockets[0]);
        DB::purge();

        $this->assertTrue(pcntl_wifexited($processStatus));
        $this->assertSame(0, pcntl_wexitstatus($processStatus), $childOutcome);
        $this->assertEqualsCanonicalizing([
            ConversationSelectionOutcome::Applied->value,
            ConversationSelectionOutcome::DuplicateDetected->value,
        ], [
            $parentResult->outcome->value,
            $childOutcome,
        ]);
        $this->assertSame(
            1,
            RefundConversation::query()
                ->where('order_item_id', $item->id)
                ->where('status', 'active')
                ->count(),
        );
        $this->assertSame(
            1,
            RefundConversation::query()
                ->whereIn('id', [$firstConversation->id, $secondConversation->id])
                ->whereNull('order_item_id')
                ->count(),
        );
    }
}
