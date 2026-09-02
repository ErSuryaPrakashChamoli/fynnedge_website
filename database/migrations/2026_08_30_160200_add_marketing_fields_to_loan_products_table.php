<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marketing-only fields, additive alongside the existing calculator/eligibility
     * columns — nothing here feeds LoanCalculatorPreset, EligibilityEngine, or any
     * lender rule. See .ai/rules: CMS content vs business logic must stay separate.
     */
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->string('marketing_headline')->nullable()->after('name');
            $table->json('benefits')->nullable()->after('features');
            $table->string('image_path')->nullable()->after('body');
            $table->string('image_alt')->nullable()->after('image_path');
            $table->string('cta_label')->nullable()->after('image_alt');
        });
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropColumn(['marketing_headline', 'benefits', 'image_path', 'image_alt', 'cta_label']);
        });
    }
};
