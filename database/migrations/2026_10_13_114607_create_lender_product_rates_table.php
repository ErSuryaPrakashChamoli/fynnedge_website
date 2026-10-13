<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per VERSION of a lender's rate for one lender offer
     * (lender_products row). Rows sharing a series_id are versions of the same
     * rate line; publishing a version archives the previously published one in
     * its series, so every rate ever shown publicly stays on record.
     *
     * Percentages are DECIMAL(5,2) in the unit named by rate_unit — never
     * formatted text.
     */
    public function up(): void
    {
        Schema::create('lender_product_rates', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('lender_product_id')->constrained()->cascadeOnDelete();
            $table->uuid('series_id');
            $table->unsignedInteger('version')->default(1);

            $table->string('variant')->nullable();
            $table->string('borrower_segment')->nullable();

            $table->decimal('rate_min', 5, 2);
            $table->decimal('rate_max', 5, 2)->nullable();
            $table->string('rate_unit', 20)->default('per_annum');
            $table->string('rate_type', 30)->nullable();
            $table->string('repayment_method', 40)->nullable();
            $table->string('benchmark')->nullable();

            $table->decimal('promo_rate', 5, 2)->nullable();
            $table->string('promo_label')->nullable();
            $table->timestamp('promo_starts_at')->nullable();
            $table->timestamp('promo_ends_at')->nullable();

            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();

            $table->text('eligibility_notes')->nullable();
            $table->text('disclaimer')->nullable();

            $table->string('source_url', 2048)->nullable();
            $table->text('source_notes')->nullable();
            $table->date('last_verified_at')->nullable();
            $table->string('verification_status', 20)->default('unverified');

            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('display_priority')->default(0);

            $table->string('status', 20)->default('draft');
            $table->text('review_note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['lender_product_id', 'status'], 'lpr_offer_status_index');
            $table->index(['status', 'effective_until'], 'lpr_status_until_index');
            $table->unique(['series_id', 'version'], 'lpr_series_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lender_product_rates');
    }
};
