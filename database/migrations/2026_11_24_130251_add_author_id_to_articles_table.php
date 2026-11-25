<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which author wrote an article. Optional (an article without one keeps the
 * FynnEdge-as-author byline and JSON-LD), and set to null — never cascaded —
 * if the author row is deleted, so deleting an author can never delete or
 * hide an article.
 *
 * No ->after(): column position is cosmetic, and the column it once named
 * (city_id) exists only in some local databases, not in any migration, so it
 * failed on production MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('author_id')->nullable()->constrained('authors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('author_id');
        });
    }
};
