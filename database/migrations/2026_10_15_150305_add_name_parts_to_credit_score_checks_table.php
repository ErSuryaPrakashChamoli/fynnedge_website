<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The visitor's first, middle and last name as on the PAN card, entered in
 * separate boxes. Surepass's CRIF API needs first_name and last_name apart, so
 * guessing them by splitting full_name broke two-word surnames ("Sen Gupta").
 * full_name stays as the three joined, for display and search. Nullable: rows
 * from before this change only have full_name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_score_checks', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('full_name');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('middle_name');
        });
    }

    public function down(): void
    {
        Schema::table('credit_score_checks', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'middle_name', 'last_name']);
        });
    }
};
