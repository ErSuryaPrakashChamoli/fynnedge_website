<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-managed 301/302 redirects, applied by App\Http\Middleware\HandleRedirects.
 *
 * `source_path` is stored normalised (leading slash, no query string, no
 * trailing slash) and uniquely indexed, so two rows can never disagree about
 * where one URL goes — the duplicate is rejected at save time rather than
 * resolved arbitrarily at request time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('source_path')->unique();
            $table->string('destination')->comment('Absolute URL, or a site-relative path starting with /');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'source_path'], 'redirects_active_source_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
