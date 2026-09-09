<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Achievements are a text-only stat row: the homepage strip renders
 * prefix + value + suffix and a caption, and never had any markup for an
 * icon. These two columns were write-only from day one — an admin could
 * upload an image that could not appear anywhere — so they go rather than
 * staying as a control that quietly does nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->dropColumn(['icon_path', 'icon_alt']);
        });
    }

    public function down(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->string('icon_path')->nullable();
            $table->string('icon_alt')->nullable();
        });
    }
};
