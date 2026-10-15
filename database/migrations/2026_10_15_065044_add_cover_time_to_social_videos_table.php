<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The second of an uploaded social video whose frame the card shows as
     * its cover, picked in the admin's CoverFramePicker. Null keeps the first
     * frame, so existing videos render as before.
     */
    public function up(): void
    {
        Schema::table('social_videos', function (Blueprint $table) {
            $table->decimal('cover_time', 6, 1)->nullable()->after('poster_path');
        });
    }

    public function down(): void
    {
        Schema::table('social_videos', function (Blueprint $table) {
            $table->dropColumn('cover_time');
        });
    }
};
