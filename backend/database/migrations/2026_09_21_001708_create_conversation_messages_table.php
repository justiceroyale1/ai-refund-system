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
        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_conversation_id')->constrained()->restrictOnDelete();
            $table->uuid('client_message_id')->nullable();
            $table->string('sender', 16);
            $table->text('content');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['refund_conversation_id', 'created_at']);
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX conversation_messages_customer_client_unique
            ON conversation_messages (refund_conversation_id, client_message_id)
            WHERE sender = 'customer' AND client_message_id IS NOT NULL
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
    }
};
