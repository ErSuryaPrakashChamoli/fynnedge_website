<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The customer's middle name as on the PAN card, kept separately from the
 * first name so the request record shows the name as the employee entered it.
 * Surepass's CRIF API has only first_name / last_name, so it is sent as part
 * of first_name. Text, because the model stores it encrypted like the other
 * name columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crif_reports', function (Blueprint $table) {
            $table->text('middle_name')->nullable()->after('first_name');
        });
    }

    public function down(): void
    {
        Schema::table('crif_reports', function (Blueprint $table) {
            $table->dropColumn('middle_name');
        });
    }
};
