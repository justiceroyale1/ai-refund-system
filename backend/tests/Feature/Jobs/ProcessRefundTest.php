<?php

namespace Tests\Feature\Jobs;

use App\Contracts\Payments\PaymentProcessor;
use App\Data\Payments\PaymentRefundResult;
use App\Enums\RefundStatus;
use App\Jobs\ProcessRefund;
use App\Models\Refund;
use App\Models\User;
use App\Services\Refunds\RefundProcessor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ProcessRefundTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_runs_the_refund_processor_with_unique_queue_configuration(): void
    {
        $refund = Refund::factory()->create();
        Event::fake([BroadcastNotificationCreated::class]);
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
        $this->assertSame(1, $refund->refundRequest->customer->notifications()->count());
        Event::assertDispatchedTimes(BroadcastNotificationCreated::class, 1);
    }

    public function test_forced_processor_error_notifies_each_admin_once_when_the_job_is_repeated(): void
    {
        $refund = Refund::factory()->create();
        $admins = User::factory()->count(2)->create(['is_admin' => true]);
        $nonAdmin = User::factory()->create(['is_admin' => false]);
        Event::fake([BroadcastNotificationCreated::class]);
        $this->mock(PaymentProcessor::class)
            ->shouldReceive('refund')
            ->once()
            ->andReturn(PaymentRefundResult::failed('The simulated payment processor was forced to fail.'));
        $job = new ProcessRefund($refund->id);

        $job->handle(app(RefundProcessor::class));
        $job->handle(app(RefundProcessor::class));

        foreach ($admins as $admin) {
            $this->assertSame(1, $admin->notifications()->count());
        }
        $this->assertSame(0, $nonAdmin->notifications()->count());
        $this->assertDatabaseCount('notifications', 2);
        Event::assertDispatchedTimes(BroadcastNotificationCreated::class, 2);
    }
}
