<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The slider's starting position on first load. Stored explicitly rather
     * than derived from min/max — a formula like "10% of the max" produces an
     * arbitrary, unrounded figure and doesn't scale sensibly from a personal
     * loan's ₹50L ceiling to a home loan's ₹10Cr one.
     */
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->decimal('default_amount', 12, 2)->nullable()->after('interest_rate_note');
            $table->unsignedSmallInteger('default_tenure_months')->nullable()->after('default_amount');
            $table->decimal('default_interest_rate', 5, 2)->nullable()->after('default_tenure_months');
        });
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropColumn(['default_amount', 'default_tenure_months', 'default_interest_rate']);
        });
    }
};
