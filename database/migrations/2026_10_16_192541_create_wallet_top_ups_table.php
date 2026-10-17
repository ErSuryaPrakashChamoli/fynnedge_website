<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A UPI top-up the customer started. It credits the wallet only when the
 * payment gateway has confirmed the payment server-side (CompleteWalletTopUp,
 * idempotent on this row); a browser redirect alone never does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_top_ups', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_paise');
            $table->string('gateway', 30);
            $table->string('gateway_order_id')->nullable()->unique('wallet_top_ups_gateway_order_unique');
            $table->string('gateway_payment_id')->nullable();
            $table->string('status', 20)->default('created');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'status'], 'wallet_top_ups_customer_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_top_ups');
    }
};
