<?php

namespace Tests\Feature\Http;

use App\Models\Customer;
use App\Models\RefundConversation;
use App\Notifications\Customer\CustomerNotification;
use App\Notifications\Customer\RefundApprovedNotification;
use App\Notifications\Customer\RefundDeniedNotification;
use App\Notifications\Customer\RefundProcessedNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerNotificationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lists_only_owned_notifications_in_stable_recent_order_with_unread_count(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $olderConversation = RefundConversation::factory()->for($customer)->create();
        $newerConversation = RefundConversation::factory()->for($customer)->create();
        $otherConversation = RefundConversation::factory()->for($otherCustomer)->create();
        $older = $this->storeNotification(
            $customer,
            new RefundApprovedNotification($olderConversation->id),
            '2026-09-24 10:00:00',
        );
        $newer = $this->storeNotification(
            $customer,
            new RefundProcessedNotification($newerConversation->id),
            '2026-09-24 11:00:00',
        );
        $this->storeNotification(
            $otherCustomer,
            new RefundDeniedNotification($otherConversation->id),
            '2026-09-24 12:00:00',
        );
        $older->markAsRead();

        $response = $this->withDemoCustomer($customer)
            ->getJson('/api/customer/notifications');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.0.type', 'refund_processed')
            ->assertJsonPath('data.0.title', 'Refund processed')
            ->assertJsonPath('data.0.message', 'Your refund has been processed successfully.')
            ->assertJsonPath('data.0.conversation_id', $newerConversation->id)
            ->assertJsonPath('data.0.read_at', null)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonMissingPath('data.0.notifiable_id')
            ->assertJsonMissingPath('data.0.notifiable_type');
    }

    public function test_paginates_owned_notification_history(): void
    {
        $customer = Customer::factory()->create();
        $conversation = RefundConversation::factory()->for($customer)->create();

        for ($notificationNumber = 0; $notificationNumber < 16; $notificationNumber++) {
            $this->storeNotification(
                $customer,
                new RefundProcessedNotification($conversation->id),
                now()->addSeconds($notificationNumber)->toDateTimeString(),
            );
        }

        $response = $this->withDemoCustomer($customer)
            ->getJson('/api/customer/notifications');

        $response
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 16)
            ->assertJsonPath('meta.unread_count', 16)
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total', 'unread_count'],
            ]);
    }

    public function test_marks_one_owned_notification_read_idempotently_and_returns_the_remaining_count(): void
    {
        $this->travelTo('2026-09-24 10:00:00');
        $customer = Customer::factory()->create();
        $conversation = RefundConversation::factory()->for($customer)->create();
        $notification = $this->storeNotification(
            $customer,
            new RefundApprovedNotification($conversation->id),
            '2026-09-24 09:00:00',
        );
        $this->storeNotification(
            $customer,
            new RefundProcessedNotification($conversation->id),
            '2026-09-24 09:30:00',
        );

        $firstResponse = $this->withDemoCustomer($customer)
            ->patchJson("/api/customer/notifications/{$notification->id}/read");

        $firstResponse
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id)
            ->assertJsonPath('data.read_at', '2026-09-24T10:00:00.000000Z')
            ->assertJsonPath('meta.unread_count', 1);
        $firstReadAt = $notification->refresh()->read_at?->toISOString();

        $this->travelTo('2026-09-24 11:00:00');
        $secondResponse = $this->withDemoCustomer($customer)
            ->patchJson("/api/customer/notifications/{$notification->id}/read");

        $secondResponse
            ->assertOk()
            ->assertJsonPath('data.read_at', '2026-09-24T10:00:00.000000Z')
            ->assertJsonPath('meta.unread_count', 1);
        $this->assertSame($firstReadAt, $notification->refresh()->read_at?->toISOString());
    }

    public function test_returns_404_without_marking_another_customers_notification(): void
    {
        $requestingCustomer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $otherConversation = RefundConversation::factory()->for($otherCustomer)->create();
        $notification = $this->storeNotification(
            $otherCustomer,
            new RefundDeniedNotification($otherConversation->id),
            now()->toDateTimeString(),
        );

        $response = $this->withDemoCustomer($requestingCustomer)
            ->patchJson("/api/customer/notifications/{$notification->id}/read");

        $response
            ->assertNotFound()
            ->assertExactJson([
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'The requested resource was not found.',
                    'details' => [],
                ],
            ]);
        $this->assertNull($notification->refresh()->read_at);
    }

    public function test_marks_all_owned_notifications_read_idempotently_without_touching_other_customers(): void
    {
        $this->travelTo('2026-09-24 10:00:00');
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $conversation = RefundConversation::factory()->for($customer)->create();
        $otherConversation = RefundConversation::factory()->for($otherCustomer)->create();
        $first = $this->storeNotification(
            $customer,
            new RefundApprovedNotification($conversation->id),
            '2026-09-24 08:00:00',
        );
        $second = $this->storeNotification(
            $customer,
            new RefundProcessedNotification($conversation->id),
            '2026-09-24 09:00:00',
        );
        $other = $this->storeNotification(
            $otherCustomer,
            new RefundDeniedNotification($otherConversation->id),
            '2026-09-24 09:30:00',
        );

        $firstResponse = $this->withDemoCustomer($customer)
            ->patchJson('/api/customer/notifications/read-all');

        $firstResponse
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'unread_count' => 0,
                ],
            ]);
        $this->assertSame('2026-09-24T10:00:00.000000Z', $first->refresh()->read_at?->toISOString());
        $this->assertSame('2026-09-24T10:00:00.000000Z', $second->refresh()->read_at?->toISOString());
        $this->assertNull($other->refresh()->read_at);

        $this->travelTo('2026-09-24 11:00:00');
        $secondResponse = $this->withDemoCustomer($customer)
            ->patchJson('/api/customer/notifications/read-all');

        $secondResponse
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);
        $this->assertSame('2026-09-24T10:00:00.000000Z', $first->refresh()->read_at?->toISOString());
        $this->assertSame('2026-09-24T10:00:00.000000Z', $second->refresh()->read_at?->toISOString());
        $this->assertNull($other->refresh()->read_at);
    }

    public function test_requires_demo_customer_identity_for_notification_routes(): void
    {
        $this->getJson('/api/customer/notifications')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'DEMO_CUSTOMER_ID_REQUIRED');
    }

    private function storeNotification(
        Customer $customer,
        CustomerNotification $notification,
        string $createdAt,
    ): DatabaseNotification {
        $notification->id = (string) Str::uuid();
        $customer->notifyNow($notification, ['database']);

        /** @var DatabaseNotification $storedNotification */
        $storedNotification = $customer->notifications()->findOrFail($notification->id);
        $storedNotification->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();

        return $storedNotification;
    }

    private function withDemoCustomer(Customer $customer): static
    {
        return $this->withHeader('X-Demo-Customer-Id', (string) $customer->id);
    }
}
