<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per paid CRIF report attempt through Surepass — successful, failed
 * or uncertain — so every billable call stays visible and auditable.
 *
 * Duplicate protection lives here, not in the UI: `in_flight_key` holds the
 * PAN hash while a request is pending, processing or awaiting reconciliation
 * and is NULL otherwise, so its unique index lets only ONE unresolved request
 * per PAN exist at a time, however many employees click at once.
 *
 * PAN, names, mobile and the provider response are stored encrypted (model
 * casts); `pan_hash` is an HMAC of the normalised PAN for lookups and the
 * masked copies are what screens show.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crif_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('journey_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('credit_consent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('previous_report_id')->nullable()->constrained('crif_reports')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('request_type', 20)->default('standard');
            $table->text('fresh_reason')->nullable();
            $table->string('status', 30)->default('pending');

            $table->char('pan_hash', 64);
            $table->char('in_flight_key', 64)->nullable()->unique('crif_reports_in_flight_unique');
            $table->char('request_fingerprint', 64);
            $table->string('pan_masked', 10);
            $table->string('mobile_masked', 20)->nullable();
            $table->text('pan_encrypted')->nullable();
            $table->text('first_name')->nullable();
            $table->text('last_name')->nullable();
            $table->text('mobile')->nullable();

            $table->unsignedSmallInteger('score')->nullable();
            $table->string('provider', 30)->default('surepass');
            $table->string('provider_client_id')->nullable();
            $table->string('provider_report_id')->nullable()->index('crif_reports_provider_report_idx');
            $table->string('provider_inquiry_id')->nullable();
            $table->string('provider_message_code', 100)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->date('report_requested_on')->nullable();
            $table->date('report_issued_on')->nullable();
            $table->longText('raw_response')->nullable();

            $table->string('error_code', 50)->nullable();
            $table->string('error_message')->nullable();
            $table->boolean('retryable')->default(false);
            $table->unsignedInteger('times_reused')->default(0);
            $table->unsignedInteger('duplicates_blocked')->default(0);

            $table->string('reconciliation_resolution', 30)->nullable();
            $table->text('reconciliation_note')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();

            $table->string('pdf_path')->nullable();
            $table->timestamp('pdf_fetched_at')->nullable();
            $table->string('pdf_error_code', 50)->nullable();

            $table->timestamp('requested_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('purged_at')->nullable();
            $table->timestamps();

            $table->index(['pan_hash', 'status', 'completed_at'], 'crif_reports_pan_status_idx');
            $table->index(['status', 'created_at'], 'crif_reports_status_created_idx');
            $table->index('requested_at', 'crif_reports_requested_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crif_reports');
    }
};
