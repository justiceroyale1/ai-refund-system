<?php

namespace Tests\Feature\Configuration;

use App\Jobs\ProcessRefund;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class RefundQueueConfigurationTest extends TestCase
{
    public function test_scheduler_registers_the_dispatch_command_with_overlap_guards(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $event): bool => str_contains($event->command ?? '', 'refunds:dispatch'));

        $this->assertInstanceOf(Event::class, $event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);

        $this->artisan('schedule:list')
            ->expectsOutputToContain('refunds:dispatch')
            ->assertSuccessful();
    }

    public function test_horizon_has_a_dedicated_refund_supervisor_with_safe_timeouts(): void
    {
        $defaultSupervisor = config('horizon.defaults.supervisor-default');
        $refundSupervisor = config('horizon.defaults.supervisor-refunds');
        $redisRetryAfter = config('queue.connections.redis.retry_after');

        $this->assertIsArray($defaultSupervisor);
        $this->assertSame(['default'], $defaultSupervisor['queue'] ?? null);
        $this->assertIsArray($refundSupervisor);
        $this->assertSame([ProcessRefund::QUEUE], $refundSupervisor['queue'] ?? null);
        $this->assertSame('redis', $refundSupervisor['connection'] ?? null);
        $this->assertSame(1, $refundSupervisor['tries'] ?? null);
        $this->assertIsInt($refundSupervisor['timeout'] ?? null);
        $this->assertGreaterThan(ProcessRefund::TIMEOUT_SECONDS, $refundSupervisor['timeout']);
        $this->assertIsInt($redisRetryAfter);
        $this->assertGreaterThan($refundSupervisor['timeout'], $redisRetryAfter);
    }
}
