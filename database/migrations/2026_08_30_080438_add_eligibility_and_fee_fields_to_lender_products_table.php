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
            $table->decimal('processing_fee_percent', 5, 2)->nullable()->after('processing_fee_note');
            $table->unsignedTinyInteger('min_age')->nullable()->after('processing_fee_percent');
            $table->unsignedTinyInteger('max_age')->nullable()->after('min_age');
            $table->unsignedSmallInteger('min_credit_score')->nullable()->after('max_age');
            $table->decimal('min_monthly_income', 12, 2)->nullable()->after('min_credit_score');
            $table->unsignedSmallInteger('min_employment_vintage_months')->nullable()->after('min_monthly_income');
            $table->json('employment_types')->nullable()->after('min_employment_vintage_months');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lender_products', function (Blueprint $table) {
            $table->dropColumn([
                'processing_fee_percent',
                'min_age',
                'max_age',
                'min_credit_score',
                'min_monthly_income',
                'min_employment_vintage_months',
                'employment_types',
            ]);
        });
    }
};
