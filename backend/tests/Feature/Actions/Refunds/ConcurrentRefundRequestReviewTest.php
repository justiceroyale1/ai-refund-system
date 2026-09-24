<?php

namespace Tests\Feature\Actions\Refunds;

use App\Actions\Refunds\ReviewRefundRequest;
use App\Enums\AuditEvent;
use App\Enums\DecisionSource;
use App\Enums\RefundDecision;
use App\Enums\RefundStatus;
use App\Exceptions\Refunds\RefundReviewException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\RefundConversation;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class ConcurrentRefundRequestReviewTest extends TestCase
{
    use DatabaseMigrations;

    public function test_concurrent_approval_accepts_one_reviewer_and_creates_one_outcome_set(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL is required to verify row-lock concurrency.');
        }

        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('The pcntl extension is required to verify process concurrency.');
        }

        $firstAdmin = User::factory()->admin()->create();
        $secondAdmin = User::factory()->admin()->create();
        $order = Order::factory()->create();
        $orderItem = OrderItem::factory()->for($order)->create();
        $conversation = RefundConversation::factory()
            ->forOrderItem($orderItem)
            ->damagedItem()
            ->resolved()
            ->create();
        $refundRequest = RefundRequest::factory()->escalated()->create([
            'refund_conversation_id' => $conversation->id,
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'amount_cents' => $orderItem->unit_price_cents,
        ]);
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if ($sockets === false) {
            throw new RuntimeException('Unable to create the review concurrency coordination socket.');
        }

        DB::disconnect();
        $processId = pcntl_fork();

        if ($processId === -1) {
            throw new RuntimeException('Unable to fork the concurrent review process.');
        }

        if ($processId === 0) {
            fclose($sockets[0]);
            DB::purge();
            fread($sockets[1], 1);

            try {
                app(ReviewRefundRequest::class)->handle(
                    RefundRequest::query()->findOrFail($refundRequest->id),
                    User::query()->findOrFail($secondAdmin->id),
                    RefundDecision::Approved,
                    'Second reviewer.',
                );
                fwrite($sockets[1], 'reviewed');
                fclose($sockets[1]);
                exit(0);
            } catch (RefundReviewException) {
                fwrite($sockets[1], 'conflict');
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

        try {
            app(ReviewRefundRequest::class)->handle(
                RefundRequest::query()->findOrFail($refundRequest->id),
                User::query()->findOrFail($firstAdmin->id),
                RefundDecision::Approved,
                'First reviewer.',
            );
            $parentOutcome = 'reviewed';
        } catch (RefundReviewException) {
            $parentOutcome = 'conflict';
        }

        pcntl_waitpid($processId, $processStatus);
        $childOutcome = stream_get_contents($sockets[0]);
        fclose($sockets[0]);
        DB::purge();
        $outcomes = [$parentOutcome, $childOutcome];
        sort($outcomes);

        $this->assertTrue(pcntl_wifexited($processStatus));
        $this->assertSame(0, pcntl_wexitstatus($processStatus), $childOutcome);
        $this->assertSame(['conflict', 'reviewed'], $outcomes);

        $refundRequest->refresh();
        $this->assertSame(RefundDecision::Approved, $refundRequest->decision);
        $this->assertSame(DecisionSource::Human, $refundRequest->decision_source);
        $this->assertContains($refundRequest->reviewed_by, [$firstAdmin->id, $secondAdmin->id], true);
        $this->assertContains($refundRequest->review_note, ['First reviewer.', 'Second reviewer.'], true);
        $refund = Refund::query()->sole();
        $this->assertSame(RefundStatus::Pending, $refund->status);
        $this->assertSame(1, $refundRequest->auditLogs()
            ->where('event', AuditEvent::RefundRequestReviewed->value)
            ->count());
        $this->assertSame(1, $refund->auditLogs()
            ->where('event', AuditEvent::RefundCreated->value)
            ->count());
        $this->assertSame(1, $conversation->messages()->count());
    }
}
