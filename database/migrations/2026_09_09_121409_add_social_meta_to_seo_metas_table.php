<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-record Open Graph and Twitter/X overrides.
 *
 * These stay on the shared seo_metas morph table with the title/description
 * columns that already exist rather than on each content table — the same rule
 * the SEO fields have followed since seo_metas was introduced. All are
 * nullable: blank means "fall back to the page's SEO title/description, then
 * to the sitewide defaults", which is what every record does today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_metas', function (Blueprint $table) {
            $table->string('og_title')->nullable()->after('og_image_path');
            $table->string('og_description')->nullable()->after('og_title');
            $table->string('twitter_title')->nullable()->after('og_description');
            $table->string('twitter_description')->nullable()->after('twitter_title');
            $table->string('twitter_image_path')->nullable()->after('twitter_description');
        });
    }

    public function down(): void
    {
        Schema::table('seo_metas', function (Blueprint $table) {
            $table->dropColumn([
                'og_title', 'og_description', 'twitter_title', 'twitter_description', 'twitter_image_path',
            ]);
        });
    }
};
