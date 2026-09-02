<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lender_products', function (Blueprint $table) {
            $table->dropColumn('processing_fee_percent');
            $table->decimal('processing_fee_percent_min', 5, 2)->nullable()->after('processing_fee_note');
            $table->decimal('processing_fee_percent_max', 5, 2)->nullable()->after('processing_fee_percent_min');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lender_products', function (Blueprint $table) {
            $table->dropColumn(['processing_fee_percent_min', 'processing_fee_percent_max']);
            $table->decimal('processing_fee_percent', 5, 2)->nullable()->after('processing_fee_note');
        });
    }
};
