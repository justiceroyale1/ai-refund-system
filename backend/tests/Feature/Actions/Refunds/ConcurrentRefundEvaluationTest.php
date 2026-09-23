<?php

namespace Tests\Feature\Actions\Refunds;

use App\Actions\Refunds\EvaluateRefundConversation;
use App\Enums\ConversationState;
use App\Enums\MessageSender;
use App\Enums\RefundReason;
use App\Models\AiAnalysis;
use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RefundConversation;
use App\Models\RefundRequest;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class ConcurrentRefundEvaluationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_concurrent_evaluation_persists_one_request_refund_and_outcome_set(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL is required to verify row-lock concurrency.');
        }

        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('The pcntl extension is required to verify process concurrency.');
        }

        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create([
            'delivered_at' => now()->subDays(5),
        ]);
        $item = OrderItem::factory()->for($order)->create([
            'unit_price_cents' => 12999,
            'final_sale' => false,
        ]);
        $conversation = RefundConversation::factory()->forOrderItem($item)->create([
            'state' => ConversationState::Evaluating,
            'reason' => RefundReason::DamagedItem,
            'reason_details' => 'The keyboard arrived with broken keys.',
        ]);
        $message = ConversationMessage::factory()->for($conversation)->create();
        AiAnalysis::factory()->for($message, 'conversationMessage')->create([
            'refund_conversation_id' => $conversation->id,
        ]);
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if ($sockets === false) {
            throw new RuntimeException('Unable to create the concurrency coordination socket.');
        }

        DB::disconnect();
        $processId = pcntl_fork();

        if ($processId === -1) {
            throw new RuntimeException('Unable to fork the concurrent evaluation process.');
        }

        if ($processId === 0) {
            fclose($sockets[0]);
            DB::purge();
            fread($sockets[1], 1);

            try {
                $request = app(EvaluateRefundConversation::class)->handle(
                    RefundConversation::query()->findOrFail($conversation->id),
                );
                fwrite($sockets[1], (string) $request->id);
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
        $parentRequest = app(EvaluateRefundConversation::class)->handle(
            RefundConversation::query()->findOrFail($conversation->id),
        );
        pcntl_waitpid($processId, $processStatus);
        $childRequestId = stream_get_contents($sockets[0]);
        fclose($sockets[0]);
        DB::purge();

        $this->assertTrue(pcntl_wifexited($processStatus));
        $this->assertSame(0, pcntl_wexitstatus($processStatus), $childRequestId);
        $this->assertSame((string) $parentRequest->id, $childRequestId);
        $this->assertDatabaseCount('refund_requests', 1);
        $this->assertDatabaseCount('refunds', 1);
        $this->assertSame(1, RefundRequest::query()->sole()->auditLogs()
            ->where('event', 'refund_request.approved')
            ->count());
        $this->assertSame(1, $conversation->messages()
            ->where('sender', MessageSender::Assistant->value)
            ->count());
        $this->assertSame(1, $conversation->auditLogs()
            ->where('event', 'policy.evaluated')
            ->count());
    }
}
