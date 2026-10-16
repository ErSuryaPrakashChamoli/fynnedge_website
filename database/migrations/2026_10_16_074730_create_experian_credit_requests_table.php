<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per paid Experian request through Surepass (credit-report-experian) —
 * score, full report or PDF; successful, failed or uncertain — kept apart from
 * CRIF's crif_reports and Equifax's equifax_credit_requests so the bureaus'
 * history, reuse and billing never mix. Each row is its own record: a fresh
 * pull adds a row, never overwrites one (no uniqueness on PAN or customer).
 *
 * Duplicate protection lives here, not in the UI: `in_flight_key` holds an
 * HMAC of product + PAN while a request is pending, processing or awaiting
 * reconciliation and is NULL otherwise, so its unique index allows only ONE
 * unresolved request per PAN per product, however many callers race.
 *
 * PAN, name, mobile, the provider response and the derived summary are stored
 * encrypted (model casts); `pan_hash` is an HMAC of the normalised PAN for
 * lookups and the masked copies are what screens and APIs show.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experian_credit_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('journey_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('credit_consent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('previous_request_id')->nullable()->constrained('experian_credit_requests')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('product', 10);
            $table->string('request_type', 20)->default('standard');
            $table->string('channel', 20)->default('admin');
            $table->text('fresh_reason')->nullable();
            $table->string('status', 30)->default('pending');

            $table->char('pan_hash', 64);
            $table->char('in_flight_key', 64)->nullable()->unique('experian_requests_in_flight_unique');
            $table->char('request_fingerprint', 64);
            $table->string('pan_masked', 10);
            $table->string('mobile_masked', 20)->nullable();
            $table->text('pan_encrypted')->nullable();
            $table->text('full_name')->nullable();
            $table->text('mobile')->nullable();

            $table->unsignedSmallInteger('score')->nullable();
            $table->string('provider', 30)->default('surepass');
            $table->string('provider_client_id')->nullable()->index('experian_requests_provider_client_idx');
            $table->string('provider_report_id')->nullable()->index('experian_requests_provider_report_idx');
            $table->string('provider_message_code', 100)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->date('report_date')->nullable();
            $table->longText('raw_response')->nullable();
            $table->text('summary')->nullable();

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

            $table->index(['pan_hash', 'product', 'status', 'completed_at'], 'experian_requests_pan_product_idx');
            $table->index(['status', 'created_at'], 'experian_requests_status_created_idx');
            $table->index('requested_at', 'experian_requests_requested_at_idx');
            $table->index(['customer_id', 'product', 'completed_at'], 'experian_requests_customer_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experian_credit_requests');
    }
};
