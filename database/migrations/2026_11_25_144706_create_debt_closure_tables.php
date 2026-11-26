<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Closure tracking of each existing liability (full or partial), the
 * payments made towards it, and the closure link on documents (NOCs,
 * payment proofs, closure letters).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debt_closures', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('debt_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('debt_liability_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('debt_lender_application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('closure_type', 10);
            $table->string('status', 30)->default('planned');
            $table->foreignId('debt_foreclosure_quotation_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('final_closure_amount', 14, 2)->nullable();
            $table->timestamp('payment_initiated_at')->nullable();
            $table->timestamp('payment_confirmed_at')->nullable();
            $table->timestamp('closure_requested_at')->nullable();
            $table->timestamp('noc_received_at')->nullable();
            $table->foreignId('noc_document_id')->nullable()->constrained('debt_documents')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('debt_closure_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('debt_closure_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('paid_on');
            $table->string('payment_mode', 20);
            $table->string('paid_by', 20);
            $table->string('utr_reference', 60)->nullable();
            $table->foreignId('debt_document_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('debt_documents', function (Blueprint $table) {
            $table->foreign('debt_closure_id', 'debt_documents_closure_foreign')->references('id')->on('debt_closures')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('debt_documents', function (Blueprint $table) {
            $table->dropForeign('debt_documents_closure_foreign');
        });

        Schema::dropIfExists('debt_closure_payments');
        Schema::dropIfExists('debt_closures');
    }
};
