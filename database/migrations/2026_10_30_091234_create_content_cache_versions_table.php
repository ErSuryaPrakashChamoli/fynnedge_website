<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * One row per App\Enums\ContentCacheSection: the version the content cache
 * keys its entries by (App\Support\ContentCache\ContentCache). Kept in the
 * database rather than in Redis so that no cache eviction, restart or outage
 * can lose an invalidation. Rows appear on a section's first change; a
 * missing row means version 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_cache_versions', function (Blueprint $table) {
            $table->string('section', 64)->primary();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_cache_versions');
    }
};
