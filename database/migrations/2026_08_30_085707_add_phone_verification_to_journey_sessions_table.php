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
        Schema::table('journey_sessions', function (Blueprint $table) {
            $table->string('phone_number')->nullable()->after('current_step_id');
            $table->timestamp('phone_verified_at')->nullable()->after('phone_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journey_sessions', function (Blueprint $table) {
            $table->dropColumn(['phone_number', 'phone_verified_at']);
        });
    }
};
