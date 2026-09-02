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
            $table->decimal('processing_fee_flat_amount_min', 10, 2)->nullable()->after('processing_fee_percent_max');
            $table->decimal('processing_fee_flat_amount_max', 10, 2)->nullable()->after('processing_fee_flat_amount_min');
            $table->boolean('processing_fee_gst_extra')->default(true)->after('processing_fee_flat_amount_max');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lender_products', function (Blueprint $table) {
            $table->dropColumn(['processing_fee_flat_amount_min', 'processing_fee_flat_amount_max', 'processing_fee_gst_extra']);
        });
    }
};
