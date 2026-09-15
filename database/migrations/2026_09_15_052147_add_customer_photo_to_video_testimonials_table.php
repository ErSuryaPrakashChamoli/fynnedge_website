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
        Schema::table('video_testimonials', function (Blueprint $table) {
            $table->string('customer_photo_path')->nullable()->after('role_location');
            $table->string('customer_photo_alt')->nullable()->after('customer_photo_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('video_testimonials', function (Blueprint $table) {
            $table->dropColumn(['customer_photo_path', 'customer_photo_alt']);
        });
    }
};
