<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\DispatchPendingRefunds;
use App\Enums\RefundStatus;
use App\Jobs\ProcessRefund;
use App\Models\Refund;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatchPendingRefundsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dispatches_only_eligible_pending_refunds_to_the_refunds_queue(): void
    {
        $this->travelTo('2026-09-24 12:00:00');
        $pending = Refund::factory()->create();
        Refund::factory()->create([
            'next_retry_at' => now()->subMinute(),
        ]);
        Refund::factory()->create([
            'next_retry_at' => now()->addMinute(),
        ]);
        Refund::factory()->create([
            'status' => RefundStatus::Processing,
        ]);
        Refund::factory()->create([
            'status' => RefundStatus::Processed,
        ]);
        Refund::factory()->create([
            'status' => RefundStatus::Failed,
        ]);
        Queue::fake([ProcessRefund::class]);

        $this->artisan(DispatchPendingRefunds::class)
            ->expectsOutputToContain('Considered 1 eligible refund(s)')
            ->assertSuccessful();

        Queue::assertPushedOn(ProcessRefund::QUEUE, ProcessRefund::class);
        Queue::assertPushed(ProcessRefund::class, 1);
        Queue::assertPushed(ProcessRefund::class, fn (ProcessRefund $job): bool => $job->refundId === $pending->id);
    }

    public function test_duplicate_dispatch_runs_queue_one_unique_job_per_refund(): void
    {
        $refund = Refund::factory()->create();
        Queue::fake([ProcessRefund::class]);

        $this->artisan(DispatchPendingRefunds::class)->assertSuccessful();
        $this->artisan(DispatchPendingRefunds::class)->assertSuccessful();

        Queue::assertPushed(ProcessRefund::class, 1);
        Queue::assertPushed(ProcessRefund::class, fn (ProcessRefund $job): bool => $job->refundId === $refund->id);
    }

    public function test_limit_bounds_each_dispatch_run(): void
    {
        Refund::factory()->count(3)->create();
        Queue::fake([ProcessRefund::class]);

        $this->artisan(DispatchPendingRefunds::class, ['--limit' => 2])
            ->expectsOutputToContain('Considered 2 eligible refund(s)')
            ->assertSuccessful();

        Queue::assertPushed(ProcessRefund::class, 2);
    }

    public function test_rejects_an_invalid_limit(): void
    {
        Queue::fake([ProcessRefund::class]);

        $this->artisan(DispatchPendingRefunds::class, ['--limit' => 0])
            ->expectsOutputToContain('The --limit option must be an integer between 1 and 1000.')
            ->assertExitCode(2);

        Queue::assertNothingPushed();
    }
}
