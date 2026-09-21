<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('refund_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('state', 32)->default('started');
            $table->string('reason', 32)->nullable();
            $table->text('reason_details')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status', 'updated_at']);
            $table->index('order_id');
            $table->index('order_item_id');
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX refund_conversations_active_customer_item_unique
            ON refund_conversations (customer_id, order_item_id)
            WHERE status = 'active' AND order_item_id IS NOT NULL
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refund_conversations');
    }
};
