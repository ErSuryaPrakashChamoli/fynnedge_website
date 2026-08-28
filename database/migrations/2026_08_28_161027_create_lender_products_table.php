<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lender_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('lender_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_product_id')->constrained()->cascadeOnDelete();
            $table->decimal('min_amount', 12, 2)->nullable();
            $table->decimal('max_amount', 12, 2)->nullable();
            $table->unsignedSmallInteger('min_tenure_months')->nullable();
            $table->unsignedSmallInteger('max_tenure_months')->nullable();
            $table->decimal('interest_rate_from', 5, 2)->nullable();
            $table->decimal('interest_rate_to', 5, 2)->nullable();
            $table->string('processing_fee_note')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['lender_id', 'loan_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lender_products');
    }
};
