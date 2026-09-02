<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('image_alt')->nullable()->after('image_path');
        });

        Schema::table('company_photos', function (Blueprint $table) {
            $table->string('photo_alt')->nullable()->after('photo_path');
        });

        Schema::table('testimonials', function (Blueprint $table) {
            $table->string('avatar_alt')->nullable()->after('avatar_path');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn('image_alt');
        });

        Schema::table('company_photos', function (Blueprint $table) {
            $table->dropColumn('photo_alt');
        });

        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropColumn('avatar_alt');
        });
    }
};
