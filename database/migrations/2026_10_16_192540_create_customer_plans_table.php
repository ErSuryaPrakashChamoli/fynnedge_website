<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credit-score refresh plans bought from the wallet (6 months / 1 year).
 * While one is active, each bureau can be refreshed once every 30 days with
 * no per-refresh charge. A plan bought while another is active starts when
 * that one ends.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('plan', 20);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedBigInteger('price_paise');
            $table->unsignedBigInteger('gst_paise');
            $table->foreignId('wallet_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'ends_at'], 'customer_plans_customer_ends_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_plans');
    }
};
