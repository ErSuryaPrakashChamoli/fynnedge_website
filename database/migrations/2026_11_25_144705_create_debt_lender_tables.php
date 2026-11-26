<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Debt consolidation criteria on existing lender offers (lender_products),
 * the matching results per case and every application facilitated to a
 * lender. Lender/product names are copied onto lender applications so the
 * history survives if an offer is later removed from the catalogue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debt_lender_policies', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('lender_product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('status', 20)->default('draft');
            $table->string('financing_route', 40);
            $table->json('applicant_types');
            $table->json('accepted_liability_types');
            $table->json('excluded_existing_lender_ids')->nullable();
            $table->boolean('accepts_own_loans')->default(true);
            $table->decimal('min_loan_amount', 16, 2)->nullable();
            $table->decimal('max_loan_amount', 16, 2)->nullable();
            $table->decimal('min_monthly_income', 14, 2)->nullable();
            $table->unsignedSmallInteger('min_business_vintage_months')->nullable();
            $table->decimal('min_annual_turnover', 16, 2)->nullable();
            $table->unsignedSmallInteger('min_credit_score')->nullable();
            $table->decimal('max_foir_percent', 5, 2)->nullable();
            $table->decimal('min_dscr', 5, 2)->nullable();
            $table->unsignedSmallInteger('max_days_past_due')->nullable();
            $table->boolean('requires_collateral')->default(false);
            $table->json('accepted_collateral_types')->nullable();
            $table->decimal('max_ltv_percent', 5, 2)->nullable();
            $table->decimal('interest_rate_min', 7, 4)->nullable();
            $table->decimal('interest_rate_max', 7, 4)->nullable();
            $table->unsignedSmallInteger('min_tenure_months')->nullable();
            $table->unsignedSmallInteger('max_tenure_months')->nullable();
            $table->decimal('processing_fee_percent', 7, 4)->nullable();
            $table->text('foreclosure_restrictions')->nullable();
            $table->json('required_document_type_ids')->nullable();
            $table->string('source_name', 150);
            $table->string('source_url', 255)->nullable();
            $table->string('source_reference', 150)->nullable();
            $table->date('source_received_on')->nullable();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['lender_product_id', 'version'], 'debt_lender_policy_version_unique');
        });

        Schema::create('debt_lender_matches', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('debt_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('debt_lender_policy_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lender_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('debt_calculation_snapshot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('match_status', 30);
            $table->decimal('indicative_loan_amount', 16, 2)->nullable();
            $table->decimal('indicative_rate_min', 7, 4)->nullable();
            $table->decimal('indicative_rate_max', 7, 4)->nullable();
            $table->json('reasons');
            $table->json('missing_information')->nullable();
            $table->string('eligibility_status', 30)->nullable();
            $table->string('advisor_status', 20)->default('pending');
            $table->text('advisor_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('evaluated_at');
            $table->timestamps();

            $table->unique(['debt_application_id', 'lender_product_id'], 'debt_matches_app_product_unique');
        });

        Schema::create('debt_lender_applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('debt_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lender_product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lender_name', 150);
            $table->string('product_name', 150);
            $table->foreignId('debt_lender_match_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('debt_consent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 40)->default('not_submitted');
            $table->string('lender_reference', 100)->nullable();
            $table->decimal('applied_amount', 16, 2)->nullable();
            $table->decimal('sanctioned_amount', 16, 2)->nullable();
            $table->decimal('sanctioned_rate', 7, 4)->nullable();
            $table->unsignedSmallInteger('sanctioned_tenure_months')->nullable();
            $table->decimal('processing_fee_amount', 14, 2)->nullable();
            $table->decimal('disbursed_amount', 16, 2)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decision_at')->nullable();
            $table->timestamp('sanctioned_at')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_lender_applications');
        Schema::dropIfExists('debt_lender_matches');
        Schema::dropIfExists('debt_lender_policies');
    }
};
