<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_conversation_id')->constrained()->restrictOnDelete();
            $table->foreignId('conversation_message_id')->constrained()->restrictOnDelete();
            $table->string('provider', 64);
            $table->string('model', 128);
            $table->string('prompt_version', 64);
            $table->unsignedTinyInteger('confidence');
            $table->boolean('prompt_injection_detected')->default(false);
            $table->boolean('conflicting_information')->default(false);
            $table->jsonb('extracted_data');
            $table->text('raw_response')->nullable();
            $table->timestamp('created_at');

            $table->index(['refund_conversation_id', 'created_at']);
            $table->index('conversation_message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_analyses');
    }
};
