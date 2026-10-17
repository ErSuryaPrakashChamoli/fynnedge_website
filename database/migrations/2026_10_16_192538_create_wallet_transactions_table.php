<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The customer wallet's ledger. Every movement is a row and rows are never
 * edited for their amount: credits (UPI top-ups, staff adjustments) are
 * positive, debits (paid refreshes, plans) negative, all in paise.
 *
 * A paid refresh is first HELD (counts against the balance so two tabs cannot
 * spend it twice), then COMPLETED when the bureau answered, or RELEASED when
 * it did not — the customer is never charged for a check without a result.
 * Balance = sum of `amount_paise` over held + completed rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('status', 20);
            $table->bigInteger('amount_paise');
            $table->unsignedBigInteger('taxable_paise')->nullable();
            $table->unsignedBigInteger('gst_paise')->nullable();
            $table->string('description');
            $table->nullableMorphs('reference');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status'], 'wallet_transactions_customer_status_idx');
            $table->index(['customer_id', 'type', 'status'], 'wallet_transactions_customer_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
