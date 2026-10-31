<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Missing pages, aggregated: ONE row per normalised path that answered 404,
 * with counters, never one row per request. Written by
 * App\Http\Middleware\RecordNotFoundRequests, listed in Admin → Missing pages.
 *
 * Deliberately stores no visitor data: no IP, no user agent, no query string,
 * no cookie. The referrer is kept as scheme://host/path only, and the only
 * thing kept about the client is whether it was a search crawler (a count).
 * The table is bounded: config('seo.not_found.max_tracked_paths') caps the
 * rows and `model:prune` drops paths not seen within the retention period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('not_found_urls', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
            $table->unsignedInteger('hits')->default(0);
            $table->unsignedInteger('internal_referrals')->default(0)->comment('Hits whose referrer was this site: a broken internal link');
            $table->unsignedInteger('crawler_hits')->default(0)->comment('Hits from search engine crawlers');
            $table->string('last_referrer')->nullable()->comment('scheme://host/path only, never a query string');
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('not_found_urls');
    }
};
