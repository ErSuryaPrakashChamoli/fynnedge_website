<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The short line beside the platform icon on a social video card, with
     * its Hindi sibling (see HasTranslations). Blank keeps showing the
     * platform name, so existing videos render as before.
     */
    public function up(): void
    {
        Schema::table('social_videos', function (Blueprint $table) {
            $table->string('tagline')->nullable()->after('title_hi');
            $table->string('tagline_hi')->nullable()->after('tagline');
        });
    }

    public function down(): void
    {
        Schema::table('social_videos', function (Blueprint $table) {
            $table->dropColumn(['tagline', 'tagline_hi']);
        });
    }
};
