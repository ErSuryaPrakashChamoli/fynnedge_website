<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versioned scoring configuration, immutable calculation snapshots, proposed
 * financing scenarios, lender foreclosure quotations and generated reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debt_scoring_configs', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('applicant_type', 20);
            $table->string('version', 20);
            $table->string('status', 20)->default('draft');
            $table->json('weights');
            $table->json('thresholds');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->unique(['applicant_type', 'version'], 'debt_scoring_type_version_unique');
        });

        Schema::create('debt_calculation_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('debt_application_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('kind', 40);
            $table->string('formula_version', 20);
            $table->foreignId('scoring_config_id')->nullable()->constrained('debt_scoring_configs')->nullOnDelete();
            $table->string('scoring_version', 20)->nullable();
            $table->json('inputs');
            $table->char('inputs_hash', 64);
            $table->json('results');
            $table->decimal('total_outstanding', 16, 2)->nullable();
            $table->decimal('net_benefit', 16, 2)->nullable();
            $table->decimal('suitability_score', 5, 2)->nullable();
            $table->string('suitability_band', 30)->nullable();
            $table->string('created_by_type', 10);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['debt_application_id', 'created_at'], 'debt_snapshots_app_created_idx');
        });

        Schema::create('debt_scenarios', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('debt_application_id')->constrained()->cascadeOnDelete();
            $table->string('label', 120);
            $table->string('financing_route', 40)->nullable();
            $table->foreignId('lender_product_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('annual_interest_rate', 7, 4);
            $table->unsignedSmallInteger('tenure_months');
            $table->decimal('processing_fee_percent', 7, 4)->default(0);
            $table->decimal('processing_fee_fixed', 14, 2)->default(0);
            $table->decimal('other_upfront_costs', 14, 2)->default(0);
            $table->boolean('fees_deducted_from_disbursal')->default(true);
            $table->boolean('closure_costs_financed')->default(true);
            $table->decimal('loan_amount_override', 16, 2)->nullable();
            $table->json('liability_overrides')->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->string('created_by_type', 10);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('debt_foreclosure_quotations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('debt_liability_id')->constrained()->cascadeOnDelete();
            $table->date('quoted_on')->nullable();
            $table->date('valid_until')->nullable();
            $table->decimal('principal_outstanding', 14, 2)->nullable();
            $table->decimal('accrued_interest', 14, 2)->nullable();
            $table->decimal('foreclosure_charges', 14, 2)->nullable();
            $table->decimal('tax_amount', 14, 2)->nullable();
            $table->decimal('overdue_amount', 14, 2)->nullable();
            $table->decimal('other_charges', 14, 2)->nullable();
            $table->decimal('total_amount', 14, 2);
            $table->string('lender_reference', 80)->nullable();
            $table->foreignId('debt_document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('verification_status', 20)->default('unverified');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['debt_liability_id', 'valid_until'], 'debt_quotes_liability_valid_idx');
        });

        Schema::create('debt_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('debt_application_id')->constrained()->cascadeOnDelete();
            $table->string('report_type', 40);
            $table->unsignedSmallInteger('version');
            $table->foreignId('debt_calculation_snapshot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('formula_version', 20)->nullable();
            $table->string('disk', 20);
            $table->string('path', 255);
            $table->char('sha256', 64);
            $table->unsignedInteger('size');
            $table->boolean('is_customer_visible')->default(false);
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['debt_application_id', 'report_type', 'version'], 'debt_reports_app_type_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_reports');
        Schema::dropIfExists('debt_foreclosure_quotations');
        Schema::dropIfExists('debt_scenarios');
        Schema::dropIfExists('debt_calculation_snapshots');
        Schema::dropIfExists('debt_scoring_configs');
    }
};
