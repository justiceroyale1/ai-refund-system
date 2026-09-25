<?php

namespace Tests\Feature\Listeners;

use App\Enums\CustomerNotificationType;
use App\Enums\DecisionSource;
use App\Enums\RefundDecision;
use App\Enums\RefundStatus;
use App\Events\RefundProcessed;
use App\Events\RefundRequestReviewed;
use App\Listeners\SendRefundProcessedNotification;
use App\Listeners\SendRefundRequestReviewedNotification;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\RefundConversation;
use App\Models\RefundRequest;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SendCustomerRefundNotificationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_human_approval_persists_and_broadcasts_the_owned_conversation_once(): void
    {
        $refundRequest = $this->reviewedRequest(RefundDecision::Approved);
        Event::fake([BroadcastNotificationCreated::class]);

        app(SendRefundRequestReviewedNotification::class)->handle(
            new RefundRequestReviewed($refundRequest->id),
        );

        $this->assertCustomerNotification(
            $refundRequest->customer,
            CustomerNotificationType::RefundApproved,
            'Refund approved',
            'Your refund request was approved after review.',
            $refundRequest->refund_conversation_id,
        );
    }

    public function test_human_denial_persists_and_broadcasts_the_owned_conversation_once(): void
    {
        $refundRequest = $this->reviewedRequest(RefundDecision::Denied);
        Event::fake([BroadcastNotificationCreated::class]);

        app(SendRefundRequestReviewedNotification::class)->handle(
            new RefundRequestReviewed($refundRequest->id),
        );

        $this->assertCustomerNotification(
            $refundRequest->customer,
            CustomerNotificationType::RefundDenied,
            'Refund denied',
            'Your refund request was denied after review.',
            $refundRequest->refund_conversation_id,
        );
    }

    public function test_successful_processing_persists_and_broadcasts_the_owned_conversation_once(): void
    {
        $refundRequest = RefundRequest::factory()->create();
        $refund = Refund::factory()->for($refundRequest)->create([
            'status' => RefundStatus::Processed,
            'attempts' => 1,
            'processed_at' => now(),
        ]);
        Event::fake([BroadcastNotificationCreated::class]);

        app(SendRefundProcessedNotification::class)->handle(
            new RefundProcessed($refund->id),
        );

        $this->assertCustomerNotification(
            $refundRequest->customer,
            CustomerNotificationType::RefundProcessed,
            'Refund processed',
            'Your refund has been processed successfully.',
            $refundRequest->refund_conversation_id,
        );
    }

    private function reviewedRequest(RefundDecision $decision): RefundRequest
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create();
        $orderItem = OrderItem::factory()->for($order)->create();
        $conversation = RefundConversation::factory()
            ->forOrderItem($orderItem)
            ->damagedItem()
            ->resolved()
            ->create();

        return RefundRequest::factory()->create([
            'refund_conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'initial_decision' => RefundDecision::Escalated,
            'decision' => $decision,
            'decision_source' => DecisionSource::Human,
        ]);
    }

    private function assertCustomerNotification(
        Customer $customer,
        CustomerNotificationType $type,
        string $title,
        string $message,
        int $conversationId,
    ): void {
        /** @var DatabaseNotification $storedNotification */
        $storedNotification = $customer->notifications()->sole();
        $expectedData = [
            'type' => $type->value,
            'title' => $title,
            'message' => $message,
            'conversation_id' => $conversationId,
        ];

        $this->assertSame($type->value, $storedNotification->type);
        $this->assertSame($expectedData, $storedNotification->data);
        $this->assertNull($storedNotification->read_at);
        $this->assertDatabaseCount('notifications', 1);

        Event::assertDispatchedTimes(BroadcastNotificationCreated::class, 1);
        Event::assertDispatched(
            BroadcastNotificationCreated::class,
            function (BroadcastNotificationCreated $event) use (
                $customer,
                $storedNotification,
                $type,
                $expectedData,
            ): bool {
                $channels = $event->broadcastOn();
                $payload = $event->broadcastWith();

                $this->assertInstanceOf(ShouldBroadcast::class, $event);
                $this->assertTrue($event->notifiable->is($customer));
                $this->assertSame($expectedData, $event->data);
                $this->assertSame($storedNotification->id, $payload['id']);
                $this->assertSame($type->value, $payload['type']);
                $this->assertCount(1, $channels);
                $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
                $this->assertSame('private-customers.'.$customer->id, $channels[0]->name);

                return true;
            },
        );
    }
}
