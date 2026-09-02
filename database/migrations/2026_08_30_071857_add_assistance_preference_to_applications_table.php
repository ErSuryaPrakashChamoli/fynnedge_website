<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('assistance_preference')->nullable()->after('status');
            $table->timestamp('assistance_requested_at')->nullable()->after('assistance_preference');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['assistance_preference', 'assistance_requested_at']);
        });
    }
};
