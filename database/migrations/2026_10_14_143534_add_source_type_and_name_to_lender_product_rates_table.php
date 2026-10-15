<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records where a rate's figures were read: the lender's own source
     * (every existing rate) or a third-party comparison site, plus the
     * source's name for the public label ("As listed on Paisabazaar …").
     */
    public function up(): void
    {
        Schema::table('lender_product_rates', function (Blueprint $table) {
            $table->string('source_type', 20)->default('official')->after('source_url');
            $table->string('source_name')->nullable()->after('source_type');
        });
    }

    public function down(): void
    {
        Schema::table('lender_product_rates', function (Blueprint $table) {
            $table->dropColumn(['source_type', 'source_name']);
        });
    }
};
