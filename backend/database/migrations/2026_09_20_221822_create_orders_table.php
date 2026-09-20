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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('reference', 32)->unique();
            $table->string('payment_reference', 128)->unique();
            $table->string('status', 32);
            $table->timestamp('ordered_at');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'ordered_at']);
            $table->index(['customer_id', 'delivered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
