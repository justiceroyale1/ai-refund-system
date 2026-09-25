<?php

namespace Tests\Feature\Listeners;

use App\Enums\AdminNotificationType;
use App\Events\RefundRequestEscalated;
use App\Listeners\SendRefundRequestEscalatedNotification;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SendAdminRefundReviewRequiredNotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_escalation_persists_and_broadcasts_one_safe_case_notification_per_admin(): void
    {
        $refundRequest = RefundRequest::factory()->escalated()->create();
        $admins = User::factory()->count(2)->create(['is_admin' => true]);
        $nonAdmin = User::factory()->create(['is_admin' => false]);
        Event::fake([BroadcastNotificationCreated::class]);

        app(SendRefundRequestEscalatedNotification::class)->handle(
            new RefundRequestEscalated($refundRequest->id),
        );

        $expectedData = [
            'type' => AdminNotificationType::RefundReviewRequired->value,
            'title' => 'Refund request needs review',
            'message' => 'A refund request requires a human decision. Review the case details.',
            'refund_request_id' => $refundRequest->id,
            'error_summary' => null,
        ];

        foreach ($admins as $admin) {
            /** @var DatabaseNotification $storedNotification */
            $storedNotification = $admin->notifications()->sole();

            $this->assertSame(AdminNotificationType::RefundReviewRequired->value, $storedNotification->type);
            $this->assertSamePayload($expectedData, $storedNotification->data);
            $this->assertNull($storedNotification->read_at);
        }

        $this->assertSame(0, $nonAdmin->notifications()->count());
        $this->assertDatabaseCount('notifications', 2);

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
                    $this->assertSame(AdminNotificationType::RefundReviewRequired->value, $payload['type']);
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
