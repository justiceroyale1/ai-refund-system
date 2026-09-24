<?php

namespace Tests\Feature\Services\Refunds;

use App\Contracts\Payments\PaymentProcessor;
use App\Data\Payments\PaymentRefundResult;
use App\Enums\AuditActorType;
use App\Enums\AuditEvent;
use App\Enums\RefundStatus;
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

    public function test_claims_an_eligible_refund_before_executing_authoritative_payment_inputs(): void
    {
        $refund = $this->pendingRefund();
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
            'status' => RefundStatus::Processing->value,
            'attempts' => 0,
            'processor_reference' => null,
            'processed_at' => null,
        ]);
        $audit = $refund->auditLogs()->sole();
        $this->assertSame(AuditActorType::System, $audit->actor_type);
        $this->assertSame(AuditEvent::RefundProcessing, $audit->event);
        $this->assertSame($refund->refund_request_id, $audit->metadata['refund_request_id'] ?? null);
        $this->assertSame(RefundStatus::Processing->value, $audit->metadata['status'] ?? null);
        $this->assertSame('simulated', $audit->metadata['processor'] ?? null);
    }

    public function test_duplicate_processing_skips_the_payment_processor(): void
    {
        $refund = $this->pendingRefund([
            'status' => RefundStatus::Processing,
        ]);
        $this->mock(PaymentProcessor::class)
            ->shouldNotReceive('refund');

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

        $this->assertSame(RefundStatus::Processing, $refund->refresh()->status);
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
