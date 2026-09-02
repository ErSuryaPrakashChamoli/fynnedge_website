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
        Schema::table('credit_consents', function (Blueprint $table) {
            $table->string('pan_number')->nullable()->after('journey_session_id');
            $table->date('date_of_birth')->nullable()->after('pan_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_consents', function (Blueprint $table) {
            $table->dropColumn(['pan_number', 'date_of_birth']);
        });
    }
};
