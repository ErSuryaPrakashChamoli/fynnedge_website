<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ties an enquiry to the loan product it was made about, so "how many Personal
 * Loan enquiries came from the website" is a group-by rather than a guess.
 *
 * Three separate facts, deliberately not collapsed into one column:
 *   loan_product_id — WHAT they asked about, as a real foreign key so a
 *                     renamed product renames everywhere and reporting joins.
 *   source          — the CHANNEL the lead came from ("website"). Lead Source.
 *   enquiry_source  — WHERE on the channel ("Personal Loan Page"), stored as a
 *                     label rather than derived, so it still reads correctly
 *                     years later even if the page or product is renamed.
 * The landing page itself is the pre-existing `source_url` column; it already
 * holds a sanitised path and needed no twin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_enquiries', function (Blueprint $table) {
            $table->foreignId('loan_product_id')
                ->nullable()
                ->after('enquiry_type')
                ->constrained()
                ->nullOnDelete();

            $table->string('enquiry_source', 80)->nullable()->after('source');
            $table->decimal('loan_amount', 14, 2)->nullable()->after('enquiry_source');
        });

        /*
         * `source` is the lead source and is now always the channel. The Quick
         * Enquiry form briefly wrote its placement there instead, which is what
         * enquiry_source is for — move those across rather than leaving two
         * columns that disagree about what "source" means.
         */
        DB::table('contact_enquiries')
            ->where('source', 'homepage')
            ->update(['enquiry_source' => 'Homepage Quick Enquiry', 'source' => 'website']);

        DB::table('contact_enquiries')
            ->whereNull('enquiry_source')
            ->where('enquiry_type', 'quick_enquiry')
            ->update(['enquiry_source' => 'Website Quick Enquiry']);

        DB::table('contact_enquiries')
            ->whereNull('enquiry_source')
            ->where('enquiry_type', 'contact')
            ->update(['enquiry_source' => 'Contact Page']);
    }

    public function down(): void
    {
        Schema::table('contact_enquiries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loan_product_id');
            $table->dropColumn(['enquiry_source', 'loan_amount']);
        });
    }
};
