<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kept separate from the existing `body` column: `body` is the general
     * product-page copy, this is calculator-specific copy ("How your Home
     * Loan EMI is calculated") that marketing can edit independently of the
     * main product page.
     */
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->longText('calculator_explanation')->nullable()->after('interest_rate_note');
        });
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropColumn('calculator_explanation');
        });
    }
};
