<?php

namespace Tests\Feature\Jobs;

use App\Contracts\Payments\PaymentProcessor;
use App\Data\Payments\PaymentRefundResult;
use App\Enums\RefundStatus;
use App\Jobs\ProcessRefund;
use App\Models\Refund;
use App\Services\Refunds\RefundProcessor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProcessRefundTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_runs_the_refund_processor_with_unique_queue_configuration(): void
    {
        $refund = Refund::factory()->create();
        $paymentProcessor = $this->mock(PaymentProcessor::class);
        $paymentProcessor->shouldReceive('refund')
            ->once()
            ->andReturn(PaymentRefundResult::successful('processor-refund-42'));
        $job = new ProcessRefund($refund->id);

        $job->handle(app(RefundProcessor::class));

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame(ProcessRefund::QUEUE, $job->queue);
        $this->assertSame((string) $refund->id, $job->uniqueId());
        $this->assertSame(1, $job->tries);
        $this->assertSame(ProcessRefund::TIMEOUT_SECONDS, $job->timeout);
        $this->assertSame(300, $job->uniqueFor);
        $this->assertSame(RefundStatus::Processed, $refund->refresh()->status);
        $this->assertSame(1, $refund->attempts);
    }
}
