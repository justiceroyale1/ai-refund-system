<?php

namespace Tests\Feature\Services\Refunds;

use App\Enums\AuditEvent;
use App\Enums\RefundStatus;
use App\Models\Refund;
use App\Services\Refunds\RefundProcessor;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class ConcurrentRefundProcessorTest extends TestCase
{
    use DatabaseMigrations;

    public function test_concurrent_workers_execute_one_refund_at_most_once(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL is required to verify row-lock concurrency.');
        }

        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('The pcntl extension is required to verify process concurrency.');
        }

        $refund = Refund::factory()->create();
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if ($sockets === false) {
            throw new RuntimeException('Unable to create the concurrency coordination socket.');
        }

        DB::disconnect();
        $processId = pcntl_fork();

        if ($processId === -1) {
            throw new RuntimeException('Unable to fork the concurrent refund process.');
        }

        if ($processId === 0) {
            fclose($sockets[0]);
            DB::purge();
            fread($sockets[1], 1);

            try {
                $result = app(RefundProcessor::class)->process($refund->id);
                fwrite($sockets[1], $result === null ? 'skipped' : 'processed');
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
        $parentResult = app(RefundProcessor::class)->process($refund->id);
        pcntl_waitpid($processId, $processStatus);
        $childResult = stream_get_contents($sockets[0]);
        fclose($sockets[0]);
        DB::purge();

        $outcomes = [$parentResult === null ? 'skipped' : 'processed', $childResult];
        sort($outcomes);

        $this->assertTrue(pcntl_wifexited($processStatus));
        $this->assertSame(0, pcntl_wexitstatus($processStatus), $childResult);
        $this->assertSame(['processed', 'skipped'], $outcomes);
        $refund->refresh();
        $this->assertSame(RefundStatus::Processed, $refund->status);
        $this->assertSame(1, $refund->attempts);
        $this->assertSame(1, $refund->auditLogs()->where('event', AuditEvent::RefundProcessing->value)->count());
        $this->assertSame(1, $refund->auditLogs()->where('event', AuditEvent::RefundProcessed->value)->count());
        $this->assertSame(1, $refund->refundRequest->refundConversation->messages()->count());
    }
}
