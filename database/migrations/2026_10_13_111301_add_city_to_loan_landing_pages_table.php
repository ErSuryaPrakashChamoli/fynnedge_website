<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "By Location" landing pages (Personal Loan in Delhi, ...) need the bare
     * city name, the same way "By Amount" pages need `amount`: the header
     * mega menu lists them as just "Delhi" under the product, rather than
     * repeating the product name in every one of the 24 city links.
     */
    public function up(): void
    {
        Schema::table('loan_landing_pages', function (Blueprint $table) {
            $table->string('city')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('loan_landing_pages', function (Blueprint $table) {
            $table->dropColumn('city');
        });
    }
};
