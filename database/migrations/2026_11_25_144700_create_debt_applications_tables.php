<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Debt Consolidation cases: the application, the applicant's finances, their
 * existing liabilities and any collateral offered. Additive only — foreign
 * keys into existing tables are nullable and null on delete, so nothing here
 * can block or change an existing customer, enquiry, lender or user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debt_applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('reference', 20)->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_enquiry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('applicant_type', 20);
            $table->string('status', 40)->default('draft');
            $table->string('journey_step', 40)->nullable();
            $table->string('full_name', 150)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('mobile', 10);
            $table->timestamp('mobile_verified_at')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('pincode', 6)->nullable();
            $table->decimal('declared_total_outstanding', 16, 2)->nullable();
            $table->decimal('declared_monthly_emi', 14, 2)->nullable();
            $table->string('source_url', 255)->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('follow_up_at')->nullable();
            $table->timestamp('enquiry_submitted_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('last_customer_activity_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('closure_reason', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at'], 'debt_apps_status_created_idx');
            $table->index('mobile', 'debt_apps_mobile_idx');
        });

        Schema::create('debt_financial_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debt_application_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('employment_type', 30)->nullable();
            $table->string('employer_name', 191)->nullable();
            $table->string('occupation', 120)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->decimal('net_monthly_income', 14, 2)->nullable();
            $table->decimal('other_monthly_income', 14, 2)->default(0);
            $table->decimal('monthly_living_expenses', 14, 2)->nullable();
            $table->decimal('other_monthly_obligations', 14, 2)->default(0);
            $table->string('residence_type', 20)->nullable();
            $table->unsignedSmallInteger('declared_credit_score')->nullable();
            $table->string('business_name', 191)->nullable();
            $table->string('business_constitution', 30)->nullable();
            $table->string('industry', 120)->nullable();
            $table->unsignedSmallInteger('business_vintage_months')->nullable();
            $table->decimal('annual_turnover', 16, 2)->nullable();
            $table->decimal('average_monthly_revenue', 14, 2)->nullable();
            $table->decimal('monthly_operating_expenses', 14, 2)->nullable();
            $table->decimal('annual_net_profit', 16, 2)->nullable();
            $table->boolean('gst_registered')->nullable();
            $table->string('gstin', 15)->nullable();
            $table->boolean('udyam_registered')->nullable();
            $table->timestamps();
        });

        Schema::create('debt_liabilities', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('debt_application_id')->constrained()->cascadeOnDelete();
            $table->string('liability_type', 40);
            $table->foreignId('lender_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lender_name', 150);
            $table->text('account_reference')->nullable();
            $table->string('account_reference_masked', 24)->nullable();
            $table->boolean('is_secured')->default(false);
            $table->string('repayment_structure', 20);
            $table->decimal('outstanding_principal', 14, 2);
            $table->decimal('interest_rate', 7, 4)->default(0);
            $table->string('rate_basis', 10)->default('reducing');
            $table->decimal('monthly_payment', 14, 2)->nullable();
            $table->unsignedSmallInteger('remaining_months')->nullable();
            $table->string('account_status', 30)->default('regular');
            $table->unsignedSmallInteger('days_past_due')->default(0);
            $table->decimal('overdue_amount', 14, 2)->default(0);
            $table->decimal('pending_instalment', 14, 2)->default(0);
            $table->decimal('accrued_interest', 14, 2)->default(0);
            $table->decimal('foreclosure_charge_percent', 7, 4)->default(0);
            $table->decimal('foreclosure_charge_fixed', 14, 2)->default(0);
            $table->decimal('other_charges', 14, 2)->default(0);
            $table->date('lock_in_ends_on')->nullable();
            $table->string('consolidation_mode', 10)->default('full');
            $table->decimal('partial_amount', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('verification_status', 20)->default('unverified');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['debt_application_id', 'sort_order'], 'debt_liabilities_app_sort_idx');
        });

        Schema::create('debt_collaterals', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('debt_application_id')->constrained()->cascadeOnDelete();
            $table->string('collateral_type', 30);
            $table->string('description', 255)->nullable();
            $table->string('owner_name', 150)->nullable();
            $table->string('ownership', 20)->nullable();
            $table->decimal('estimated_value', 16, 2)->nullable();
            $table->decimal('existing_charge_amount', 16, 2)->default(0);
            $table->string('city', 100)->nullable();
            $table->string('pincode', 6)->nullable();
            $table->string('verification_status', 20)->default('unverified');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_collaterals');
        Schema::dropIfExists('debt_liabilities');
        Schema::dropIfExists('debt_financial_profiles');
        Schema::dropIfExists('debt_applications');
    }
};
