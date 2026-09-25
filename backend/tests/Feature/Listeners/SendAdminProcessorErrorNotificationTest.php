<?php

namespace Tests\Feature\Listeners;

use App\Enums\AdminNotificationType;
use App\Enums\RefundStatus;
use App\Events\RefundProcessingFailed;
use App\Listeners\SendRefundProcessingFailedNotification;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\Admin\ProcessorErrorNotification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SendAdminProcessorErrorNotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_processor_error_persists_and_broadcasts_one_safe_case_notification_per_admin(): void
    {
        $refund = Refund::factory()->create([
            'status' => RefundStatus::Processing,
            'attempts' => 1,
            'last_error' => "Internal diagnostic SHOULD_NOT_APPEAR\n#0 /srv/app/Processor.php(42): fail()",
        ]);
        $admins = User::factory()->count(2)->create(['is_admin' => true]);
        $nonAdmin = User::factory()->create(['is_admin' => false]);
        Event::fake([BroadcastNotificationCreated::class]);

        app(SendRefundProcessingFailedNotification::class)->handle(
            new RefundProcessingFailed($refund->id),
        );

        $expectedData = [
            'type' => AdminNotificationType::ProcessorError->value,
            'title' => 'Refund processing error',
            'message' => 'A refund could not be processed. Review the case for details.',
            'refund_request_id' => $refund->refund_request_id,
            'error_summary' => ProcessorErrorNotification::ERROR_SUMMARY,
        ];

        foreach ($admins as $admin) {
            /** @var DatabaseNotification $storedNotification */
            $storedNotification = $admin->notifications()->sole();

            $this->assertSame(AdminNotificationType::ProcessorError->value, $storedNotification->type);
            $this->assertSamePayload($expectedData, $storedNotification->data);
            $this->assertNull($storedNotification->read_at);
        }

        $this->assertSame(0, $nonAdmin->notifications()->count());
        $this->assertDatabaseCount('notifications', 2);
        $this->assertStringNotContainsString('SHOULD_NOT_APPEAR', json_encode($expectedData, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('Processor.php', json_encode($expectedData, JSON_THROW_ON_ERROR));

        Event::assertDispatchedTimes(BroadcastNotificationCreated::class, 2);
        foreach ($admins as $admin) {
            Event::assertDispatched(
                BroadcastNotificationCreated::class,
                function (BroadcastNotificationCreated $event) use ($admin, $expectedData): bool {
                    if (! $event->notifiable->is($admin)) {
                        return false;
                    }

                    $channels = $event->broadcastOn();
                    $payload = $event->broadcastWith();

                    $this->assertInstanceOf(ShouldBroadcast::class, $event);
                    $this->assertSame($expectedData, $event->data);
                    $this->assertSame(AdminNotificationType::ProcessorError->value, $payload['type']);
                    $this->assertCount(1, $channels);
                    $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
                    $this->assertSame('private-admins.'.$admin->id, $channels[0]->name);

                    return true;
                },
            );
        }
    }

    /**
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $actual
     */
    private function assertSamePayload(array $expected, array $actual): void
    {
        ksort($expected);
        ksort($actual);

        $this->assertSame($expected, $actual);
    }
}
