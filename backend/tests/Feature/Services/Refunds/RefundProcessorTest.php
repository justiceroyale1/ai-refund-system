<?php

namespace Tests\Feature\Services\Refunds;

use App\Contracts\Payments\PaymentProcessor;
use App\Data\Payments\PaymentRefundResult;
use App\Enums\AuditActorType;
use App\Enums\AuditEvent;
use App\Enums\ConversationMessageTemplate;
use App\Enums\MessageSender;
use App\Enums\RefundStatus;
use App\Events\RefundProcessed;
use App\Events\RefundProcessingFailed;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\RefundRequest;
use App\Services\Refunds\RefundProcessor;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class RefundProcessorTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_persists_successful_outcome_once_with_authoritative_inputs_message_audit_and_notification_intent(): void
    {
        $this->travelTo('2026-09-24 12:00:00');
        $refund = $this->pendingRefund();
        Event::fake([RefundProcessed::class, RefundProcessingFailed::class]);
        $result = PaymentRefundResult::successful('processor-refund-42');
        $paymentProcessor = $this->mock(PaymentProcessor::class);
        $paymentProcessor->shouldReceive('refund')
            ->once()
            ->with('PAY-REFUND-42', 12999, $refund->idempotency_key)
            ->andReturn($result);

        $actual = app(RefundProcessor::class)->process($refund->id);

        $this->assertSame($result, $actual);
        $this->assertDatabaseHas('refunds', [
            'id' => $refund->id,
            'status' => RefundStatus::Processed->value,
            'attempts' => 1,
            'processor_reference' => 'processor-refund-42',
            'last_error' => null,
            'next_retry_at' => null,
            'processed_at' => '2026-09-24 12:00:00',
        ]);

        $processingAudit = $refund->auditLogs()
            ->where('event', AuditEvent::RefundProcessing->value)
            ->sole();
        $this->assertSame(AuditActorType::System, $processingAudit->actor_type);
        $this->assertSame($refund->refund_request_id, $processingAudit->metadata['refund_request_id'] ?? null);
        $this->assertSame(RefundStatus::Processing->value, $processingAudit->metadata['status'] ?? null);
        $this->assertSame('simulated', $processingAudit->metadata['processor'] ?? null);

        $processedAudit = $refund->auditLogs()
            ->where('event', AuditEvent::RefundProcessed->value)
            ->sole();
        $this->assertSame(AuditActorType::System, $processedAudit->actor_type);
        $this->assertSame($refund->refund_request_id, $processedAudit->metadata['refund_request_id'] ?? null);
        $this->assertSame(RefundStatus::Processed->value, $processedAudit->metadata['status'] ?? null);
        $this->assertSame('simulated', $processedAudit->metadata['processor'] ?? null);
        $this->assertSame('processor-refund-42', $processedAudit->metadata['processor_reference'] ?? null);
        $this->assertSame(1, $processedAudit->metadata['attempts'] ?? null);
        $this->assertSame('2026-09-24T12:00:00.000000Z', $processedAudit->metadata['processed_at'] ?? null);

        $message = $refund->refundRequest->refundConversation->messages()->sole();
        $this->assertSame(MessageSender::System, $message->sender);
        $this->assertSame(ConversationMessageTemplate::RefundProcessed->value, $message->content);
        $this->assertSame(
            RefundStatus::Processed->value,
            $message->metadata['refund_status'] ?? null,
        );
        Event::assertDispatched(
            RefundProcessed::class,
            fn (RefundProcessed $event): bool => $event->refundId === $refund->id,
        );
        Event::assertNotDispatched(RefundProcessingFailed::class);
    }

    public function test_duplicate_job_does_not_repeat_successful_processor_or_outcome_side_effects(): void
    {
        $refund = $this->pendingRefund();
        Event::fake([RefundProcessed::class]);
        $result = PaymentRefundResult::successful('processor-refund-42');
        $this->mock(PaymentProcessor::class)
            ->shouldReceive('refund')
            ->once()
            ->andReturn($result);
        $processor = app(RefundProcessor::class);

        $first = $processor->process($refund->id);
        $second = $processor->process($refund->id);

        $this->assertSame($result, $first);
        $this->assertNull($second);
        $this->assertSame(RefundStatus::Processed, $refund->refresh()->status);
        $this->assertSame(1, $refund->attempts);
        $this->assertSame(2, $refund->auditLogs()->count());
        $this->assertSame(1, $refund->refundRequest->refundConversation->messages()->count());
        Event::assertDispatchedTimes(RefundProcessed::class, 1);
    }

    public function test_stale_processing_refund_skips_the_payment_processor(): void
    {
        $refund = $this->pendingRefund(['status' => RefundStatus::Processing]);
        $this->mock(PaymentProcessor::class)->shouldNotReceive('refund');

        $result = app(RefundProcessor::class)->process($refund->id);

        $this->assertNull($result);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame(RefundStatus::Processing, $refund->refresh()->status);
    }

    public function test_retry_scheduled_pending_refund_skips_the_payment_processor(): void
    {
        $this->travelTo('2026-09-24 12:00:00');
        $refund = $this->pendingRefund([
            'next_retry_at' => now()->subMinute(),
        ]);
        $this->mock(PaymentProcessor::class)
            ->shouldNotReceive('refund');

        $result = app(RefundProcessor::class)->process($refund->id);

        $this->assertNull($result);
        $this->assertSame(RefundStatus::Pending, $refund->refresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_rolls_back_the_claim_when_its_audit_cannot_be_persisted(): void
    {
        $refund = $this->pendingRefund();
        $this->mock(PaymentProcessor::class)
            ->shouldNotReceive('refund');
        Event::listen(QueryExecuted::class, static function (QueryExecuted $query): void {
            if (str_contains($query->sql, 'insert into "audit_logs"')) {
                throw new RuntimeException('Simulated audit persistence failure.');
            }
        });

        try {
            app(RefundProcessor::class)->process($refund->id);
            $this->fail('The audit persistence failure was not raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated audit persistence failure.', $exception->getMessage());
        }

        $this->assertSame(RefundStatus::Pending, $refund->refresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_rolls_back_every_success_outcome_write_when_the_message_cannot_be_persisted(): void
    {
        $refund = $this->pendingRefund();
        Event::fake([RefundProcessed::class]);
        $this->mock(PaymentProcessor::class)
            ->shouldReceive('refund')
            ->once()
            ->andReturn(PaymentRefundResult::successful('processor-refund-42'));
        Event::listen(QueryExecuted::class, static function (QueryExecuted $query): void {
            if (str_contains($query->sql, 'insert into "conversation_messages"')) {
                throw new RuntimeException('Simulated message persistence failure.');
            }
        });

        try {
            app(RefundProcessor::class)->process($refund->id);
            $this->fail('The message persistence failure was not raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated message persistence failure.', $exception->getMessage());
        }

        $refund->refresh();
        $this->assertSame(RefundStatus::Processing, $refund->status);
        $this->assertSame(0, $refund->attempts);
        $this->assertNull($refund->processor_reference);
        $this->assertNull($refund->processed_at);
        $this->assertSame(1, $refund->auditLogs()->count());
        $this->assertSame(0, $refund->refundRequest->refundConversation->messages()->count());
        Event::assertNotDispatched(RefundProcessed::class);
    }

    public function test_forced_processor_error_persists_safe_diagnostics_without_customer_message_or_retry(): void
    {
        $refund = $this->pendingRefund();
        Event::fake([RefundProcessed::class, RefundProcessingFailed::class]);
        $result = PaymentRefundResult::failed('The simulated processor could not complete the refund.');
        $this->mock(PaymentProcessor::class)
            ->shouldReceive('refund')
            ->once()
            ->andReturn($result);
        $processor = app(RefundProcessor::class);

        $first = $processor->process($refund->id);
        $second = $processor->process($refund->id);

        $this->assertSame($result, $first);
        $this->assertNull($second);
        $this->assertDatabaseHas('refunds', [
            'id' => $refund->id,
            'status' => RefundStatus::Processing->value,
            'attempts' => 1,
            'processor_reference' => null,
            'last_error' => 'The simulated processor could not complete the refund.',
            'next_retry_at' => null,
            'processed_at' => null,
        ]);
        $this->assertSame(1, $refund->auditLogs()
            ->where('event', AuditEvent::RefundProcessing->value)
            ->count());

        $failedAudit = $refund->auditLogs()
            ->where('event', AuditEvent::RefundFailed->value)
            ->sole();
        $this->assertSame(AuditActorType::System, $failedAudit->actor_type);
        $this->assertSame($refund->refund_request_id, $failedAudit->metadata['refund_request_id'] ?? null);
        $this->assertSame(RefundStatus::Processing->value, $failedAudit->metadata['status'] ?? null);
        $this->assertSame('simulated', $failedAudit->metadata['processor'] ?? null);
        $this->assertSame(1, $failedAudit->metadata['attempts'] ?? null);
        $this->assertSame(
            'The simulated processor could not complete the refund.',
            $failedAudit->metadata['error'] ?? null,
        );
        $this->assertSame(0, $refund->refundRequest->refundConversation->messages()->count());
        Event::assertDispatched(
            RefundProcessingFailed::class,
            fn (RefundProcessingFailed $event): bool => $event->refundId === $refund->id,
        );
        Event::assertNotDispatched(RefundProcessed::class);
    }

    public function test_customer_conversation_api_does_not_expose_processor_error_diagnostics(): void
    {
        $refund = $this->pendingRefund();
        $error = 'Internal processor host payment-gateway.internal:8443 was unavailable.';
        $this->mock(PaymentProcessor::class)
            ->shouldReceive('refund')
            ->once()
            ->andReturn(PaymentRefundResult::failed($error));

        app(RefundProcessor::class)->process($refund->id);

        $conversation = $refund->refundRequest->refundConversation;
        $response = $this->getJson(
            "/api/customer/conversations/{$conversation->id}",
            ['X-Demo-Customer-Id' => (string) $conversation->customer_id],
        );
        $response->assertOk();
        $this->assertStringNotContainsString($error, $response->getContent());
        $this->assertStringNotContainsString('last_error', $response->getContent());
        $this->assertStringNotContainsString('processor_reference', $response->getContent());
    }

    public function test_processor_exception_occurs_after_the_processing_claim_is_committed(): void
    {
        $refund = $this->pendingRefund();
        $paymentProcessor = $this->mock(PaymentProcessor::class);
        $paymentProcessor->shouldReceive('refund')
            ->once()
            ->andThrow(new RuntimeException('Processor unavailable.'));

        try {
            app(RefundProcessor::class)->process($refund->id);
            $this->fail('The processor exception was not raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Processor unavailable.', $exception->getMessage());
        }

        $refund->refresh();
        $this->assertSame(RefundStatus::Processing, $refund->status);
        $this->assertSame(0, $refund->attempts);
        $this->assertNull($refund->last_error);
        $this->assertSame(1, $refund->auditLogs()->where('event', AuditEvent::RefundProcessing->value)->count());
    }

    /**
     * @param  array<string, mixed>  $refundAttributes
     */
    private function pendingRefund(array $refundAttributes = []): Refund
    {
        $order = Order::factory()->create([
            'payment_reference' => 'PAY-REFUND-42',
        ]);
        $item = OrderItem::factory()->for($order)->create([
            'unit_price_cents' => 12999,
        ]);
        $refundRequest = RefundRequest::factory()->for($item)->create([
            'amount_cents' => 12999,
        ]);

        return Refund::factory()->for($refundRequest)->create(array_replace([
            'amount_cents' => 12999,
            'status' => RefundStatus::Pending,
        ], $refundAttributes));
    }
}
