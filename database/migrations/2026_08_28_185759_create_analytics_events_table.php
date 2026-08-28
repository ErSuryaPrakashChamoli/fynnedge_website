<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_key');
            $table->foreignId('journey_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('loan_product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lender_product_id')->nullable()->constrained()->nullOnDelete();
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['event_key', 'created_at']);
            $table->index(['loan_product_id', 'event_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
