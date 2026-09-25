<?php

namespace Tests\Feature\Http;

use App\Models\RefundRequest;
use App\Models\User;
use App\Notifications\Admin\ProcessorErrorNotification;
use App\Notifications\Admin\RefundReviewRequiredNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminNotificationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lists_only_owned_notifications_in_stable_recent_order_with_safe_payload_and_unread_count(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $otherAdmin = User::factory()->create(['is_admin' => true]);
        $olderRequest = RefundRequest::factory()->create();
        $newerRequest = RefundRequest::factory()->create();
        $otherRequest = RefundRequest::factory()->create();
        $older = $this->storeNotification($admin, $olderRequest, '2026-09-24 10:00:00');
        $newer = $this->storeNotification($admin, $newerRequest, '2026-09-24 11:00:00');
        $this->storeNotification($otherAdmin, $otherRequest, '2026-09-24 12:00:00');
        $older->markAsRead();

        $response = $this->actingAs($admin, 'web')->getJson('/api/admin/notifications');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.0.type', 'processor_error')
            ->assertJsonPath('data.0.title', 'Refund processing error')
            ->assertJsonPath('data.0.message', 'A refund could not be processed. Review the case for details.')
            ->assertJsonPath('data.0.refund_request_id', $newerRequest->id)
            ->assertJsonPath('data.0.error_summary', ProcessorErrorNotification::ERROR_SUMMARY)
            ->assertJsonPath('data.0.read_at', null)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonMissingPath('data.0.notifiable_id')
            ->assertJsonMissingPath('data.0.notifiable_type')
            ->assertJsonMissingPath('data.0.last_error')
            ->assertJsonMissingPath('data.0.processor_reference');
    }

    public function test_paginates_owned_notification_history(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $refundRequest = RefundRequest::factory()->create();

        for ($notificationNumber = 0; $notificationNumber < 16; $notificationNumber++) {
            $this->storeNotification(
                $admin,
                $refundRequest,
                now()->addSeconds($notificationNumber)->toDateTimeString(),
            );
        }

        $response = $this->actingAs($admin, 'web')->getJson('/api/admin/notifications');

        $response
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 16)
            ->assertJsonPath('meta.unread_count', 16);
    }

    public function test_serializes_review_required_notification_without_processor_diagnostics(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $refundRequest = RefundRequest::factory()->escalated()->create();
        $notification = new RefundReviewRequiredNotification($refundRequest->id);
        $notification->id = (string) Str::uuid();
        $admin->notifyNow($notification, ['database']);

        $this->actingAs($admin, 'web')
            ->getJson('/api/admin/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'refund_review_required')
            ->assertJsonPath('data.0.title', 'Refund request needs review')
            ->assertJsonPath('data.0.refund_request_id', $refundRequest->id)
            ->assertJsonPath('data.0.error_summary', null)
            ->assertJsonPath('meta.unread_count', 1);
    }

    public function test_marks_one_owned_notification_read_idempotently_and_returns_the_remaining_count(): void
    {
        $this->travelTo('2026-09-24 10:00:00');
        $admin = User::factory()->create(['is_admin' => true]);
        $refundRequest = RefundRequest::factory()->create();
        $notification = $this->storeNotification($admin, $refundRequest, '2026-09-24 09:00:00');
        $this->storeNotification($admin, $refundRequest, '2026-09-24 09:30:00');

        $firstResponse = $this->actingAs($admin, 'web')
            ->patchJson("/api/admin/notifications/{$notification->id}/read");

        $firstResponse
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id)
            ->assertJsonPath('data.read_at', '2026-09-24T10:00:00.000000Z')
            ->assertJsonPath('meta.unread_count', 1);
        $firstReadAt = $notification->refresh()->read_at?->toISOString();

        $this->travelTo('2026-09-24 11:00:00');
        $secondResponse = $this->actingAs($admin, 'web')
            ->patchJson("/api/admin/notifications/{$notification->id}/read");

        $secondResponse
            ->assertOk()
            ->assertJsonPath('data.read_at', '2026-09-24T10:00:00.000000Z')
            ->assertJsonPath('meta.unread_count', 1);
        $this->assertSame($firstReadAt, $notification->refresh()->read_at?->toISOString());
    }

    public function test_returns_404_without_marking_another_admins_notification(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $otherAdmin = User::factory()->create(['is_admin' => true]);
        $notification = $this->storeNotification(
            $otherAdmin,
            RefundRequest::factory()->create(),
            now()->toDateTimeString(),
        );

        $response = $this->actingAs($admin, 'web')
            ->patchJson("/api/admin/notifications/{$notification->id}/read");

        $response
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
        $this->assertNull($notification->refresh()->read_at);
    }

    public function test_marks_all_owned_notifications_read_idempotently_without_touching_other_admins(): void
    {
        $this->travelTo('2026-09-24 10:00:00');
        $admin = User::factory()->create(['is_admin' => true]);
        $otherAdmin = User::factory()->create(['is_admin' => true]);
        $refundRequest = RefundRequest::factory()->create();
        $first = $this->storeNotification($admin, $refundRequest, '2026-09-24 08:00:00');
        $second = $this->storeNotification($admin, $refundRequest, '2026-09-24 09:00:00');
        $other = $this->storeNotification($otherAdmin, $refundRequest, '2026-09-24 09:30:00');

        $firstResponse = $this->actingAs($admin, 'web')
            ->patchJson('/api/admin/notifications/read-all');

        $firstResponse
            ->assertOk()
            ->assertExactJson(['data' => ['unread_count' => 0]]);
        $this->assertSame('2026-09-24T10:00:00.000000Z', $first->refresh()->read_at?->toISOString());
        $this->assertSame('2026-09-24T10:00:00.000000Z', $second->refresh()->read_at?->toISOString());
        $this->assertNull($other->refresh()->read_at);

        $this->travelTo('2026-09-24 11:00:00');
        $this->actingAs($admin, 'web')
            ->patchJson('/api/admin/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);
        $this->assertSame('2026-09-24T10:00:00.000000Z', $first->refresh()->read_at?->toISOString());
    }

    #[DataProvider('notificationRoutes')]
    public function test_returns_401_for_unauthenticated_notification_requests(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized();
    }

    #[DataProvider('notificationRoutes')]
    public function test_returns_403_for_non_admin_notification_requests(string $method, string $uri): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user, 'web')->json($method, $uri)->assertForbidden();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function notificationRoutes(): array
    {
        return [
            'list' => ['GET', '/api/admin/notifications'],
            'mark one read' => ['PATCH', '/api/admin/notifications/00000000-0000-4000-8000-000000000000/read'],
            'mark all read' => ['PATCH', '/api/admin/notifications/read-all'],
        ];
    }

    private function storeNotification(
        User $admin,
        RefundRequest $refundRequest,
        string $createdAt,
    ): DatabaseNotification {
        $notification = new ProcessorErrorNotification($refundRequest->id);
        $notification->id = (string) Str::uuid();
        $admin->notifyNow($notification, ['database']);

        /** @var DatabaseNotification $storedNotification */
        $storedNotification = $admin->notifications()->findOrFail($notification->id);
        $storedNotification->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();

        return $storedNotification;
    }
}
