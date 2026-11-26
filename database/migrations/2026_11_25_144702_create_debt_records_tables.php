<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documents (encrypted at rest, versioned), consents, the communication log
 * and the status history of Debt Consolidation cases.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debt_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('debt_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('debt_liability_id')->nullable()->constrained()->nullOnDelete();
            // Constrained in create_debt_closure_tables, once debt_closures exists.
            $table->unsignedBigInteger('debt_closure_id')->nullable()->index('debt_documents_closure_idx');
            $table->foreignId('document_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label', 150)->nullable();
            $table->string('disk', 20);
            $table->string('path', 255);
            $table->string('original_filename', 255);
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->char('sha256', 64);
            $table->boolean('is_encrypted')->default(true);
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('supersedes_id')->nullable()->constrained('debt_documents')->nullOnDelete();
            $table->string('status', 20)->default('uploaded');
            $table->string('rejection_reason', 255)->nullable();
            $table->string('uploaded_by_type', 10);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('debt_consents', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('debt_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose', 40);
            $table->foreignId('lender_product_id')->nullable()->constrained()->nullOnDelete();
            $table->text('consent_text');
            $table->string('text_version', 20);
            $table->char('text_hash', 64);
            $table->string('method', 30);
            $table->timestamp('granted_at');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['debt_application_id', 'purpose'], 'debt_consents_app_purpose_idx');
        });

        Schema::create('debt_notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('debt_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->string('direction', 10)->nullable();
            $table->string('subject', 150)->nullable();
            $table->text('body');
            $table->timestamp('follow_up_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('visible_to_customer')->default(false);
            $table->timestamps();

            $table->index(['follow_up_at', 'completed_at'], 'debt_notes_follow_up_idx');
        });

        Schema::create('debt_status_changes', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->text('note')->nullable();
            $table->string('actor_type', 10);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_status_changes');
        Schema::dropIfExists('debt_notes');
        Schema::dropIfExists('debt_consents');
        Schema::dropIfExists('debt_documents');
    }
};
