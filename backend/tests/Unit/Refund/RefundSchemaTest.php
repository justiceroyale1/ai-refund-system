<?php

namespace Tests\Unit\Refund;

use App\Models\Customer;
use App\Models\Refund;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class RefundSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_schema_contains_refund_audit_and_notification_columns_with_query_indexes(): void
    {
        $this->assertTrue(Schema::hasColumns('refund_requests', [
            'id',
            'refund_conversation_id',
            'customer_id',
            'order_id',
            'order_item_id',
            'reason',
            'reason_details',
            'amount_cents',
            'initial_decision',
            'decision',
            'decision_source',
            'decision_code',
            'policy_checks',
            'reviewed_by',
            'review_note',
            'decided_at',
            'created_at',
            'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('refunds', [
            'id',
            'refund_request_id',
            'order_item_id',
            'amount_cents',
            'status',
            'processor',
            'idempotency_key',
            'processor_reference',
            'attempts',
            'last_error',
            'next_retry_at',
            'processed_at',
            'created_at',
            'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('audit_logs', [
            'id',
            'actor_type',
            'actor_id',
            'subject_type',
            'subject_id',
            'event',
            'metadata',
            'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('audit_logs', 'updated_at'));
        $this->assertTrue(Schema::hasColumns('notifications', [
            'id',
            'type',
            'notifiable_type',
            'notifiable_id',
            'data',
            'read_at',
            'created_at',
            'updated_at',
        ]));

        $requestIndexes = array_column(Schema::getIndexes('refund_requests'), 'name');
        $refundIndexes = array_column(Schema::getIndexes('refunds'), 'name');
        $auditIndexes = array_column(Schema::getIndexes('audit_logs'), 'name');
        $notificationIndexes = array_column(Schema::getIndexes('notifications'), 'name');

        $this->assertContains('refund_requests_refund_conversation_id_unique', $requestIndexes);
        $this->assertContains('refund_requests_customer_id_created_at_index', $requestIndexes);
        $this->assertContains('refund_requests_decision_created_at_index', $requestIndexes);
        $this->assertContains('refunds_refund_request_id_unique', $refundIndexes);
        $this->assertContains('refunds_order_item_id_unique', $refundIndexes);
        $this->assertContains('refunds_idempotency_key_unique', $refundIndexes);
        $this->assertContains('refunds_status_next_retry_at_created_at_index', $refundIndexes);
        $this->assertContains('audit_logs_actor_type_actor_id_created_at_index', $auditIndexes);
        $this->assertContains('audit_logs_subject_type_subject_id_created_at_index', $auditIndexes);
        $this->assertContains('audit_logs_event_created_at_index', $auditIndexes);
        $this->assertContains('notifications_notifiable_type_notifiable_id_index', $notificationIndexes);
        $this->assertContains('notifications_notifiable_unread_created_index', $notificationIndexes);
    }

    public function test_rejects_a_second_refund_request_for_one_conversation(): void
    {
        $request = RefundRequest::factory()->create();

        $this->expectException(QueryException::class);

        RefundRequest::factory()
            ->for($request->refundConversation, 'refundConversation')
            ->create();
    }

    public function test_rejects_a_second_refund_for_one_request(): void
    {
        $refund = Refund::factory()->create();
        $differentRequest = RefundRequest::factory()->create();

        $this->expectException(QueryException::class);

        Refund::factory()->create([
            'refund_request_id' => $refund->refund_request_id,
            'order_item_id' => $differentRequest->order_item_id,
        ]);
    }

    public function test_rejects_a_second_refund_for_one_order_item(): void
    {
        $refund = Refund::factory()->create();
        $differentRequest = RefundRequest::factory()->create();

        $this->expectException(QueryException::class);

        Refund::factory()->create([
            'refund_request_id' => $differentRequest->id,
            'order_item_id' => $refund->order_item_id,
        ]);
    }

    public function test_rejects_a_duplicate_processor_idempotency_key(): void
    {
        $refund = Refund::factory()->create();
        $differentRequest = RefundRequest::factory()->create();

        $this->expectException(QueryException::class);

        Refund::factory()->create([
            'refund_request_id' => $differentRequest->id,
            'order_item_id' => $differentRequest->order_item_id,
            'idempotency_key' => $refund->idempotency_key,
        ]);
    }

    public function test_database_string_columns_do_not_enforce_application_enum_membership(): void
    {
        $refund = Refund::factory()->create();

        DB::table('refund_requests')->where('id', $refund->refund_request_id)->update([
            'initial_decision' => 'future_initial',
            'decision' => 'future_decision',
            'decision_source' => 'future_source',
            'decision_code' => 'FUTURE_DECISION_CODE',
        ]);
        DB::table('refunds')->where('id', $refund->id)->update([
            'status' => 'future_status',
        ]);

        $this->assertDatabaseHas('refund_requests', [
            'id' => $refund->refund_request_id,
            'initial_decision' => 'future_initial',
            'decision' => 'future_decision',
            'decision_source' => 'future_source',
            'decision_code' => 'FUTURE_DECISION_CODE',
        ]);
        $this->assertDatabaseHas('refunds', [
            'id' => $refund->id,
            'status' => 'future_status',
        ]);
    }

    public function test_notification_storage_accepts_customer_and_user_recipients(): void
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->admin()->create();
        $customerNotificationId = Str::uuid()->toString();
        $userNotificationId = Str::uuid()->toString();

        DB::table('notifications')->insert([
            [
                'id' => $customerNotificationId,
                'type' => 'customer.refund_processed',
                'notifiable_type' => Customer::class,
                'notifiable_id' => $customer->id,
                'data' => json_encode(['conversation_id' => 1], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $userNotificationId,
                'type' => 'admin.refund_processing_error',
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode(['refund_request_id' => 1], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->assertDatabaseHas('notifications', [
            'id' => $customerNotificationId,
            'notifiable_type' => Customer::class,
            'notifiable_id' => $customer->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'id' => $userNotificationId,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
        ]);
    }
}
