<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Equifax (Surepass credit-report-v2) requires the person's gender, so the
 * public Free Equifax Score page asks for it when it shows real scores.
 * Nullable: other bureaus' checks, and every row before this, have none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_score_checks', function (Blueprint $table) {
            $table->string('gender', 10)->nullable()->after('date_of_birth');
        });
    }

    public function down(): void
    {
        Schema::table('credit_score_checks', function (Blueprint $table) {
            $table->dropColumn('gender');
        });
    }
};
