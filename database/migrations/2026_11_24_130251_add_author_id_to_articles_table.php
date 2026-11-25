<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which author wrote an article. Optional (an article without one keeps the
 * FynnEdge-as-author byline and JSON-LD), and set to null — never cascaded —
 * if the author row is deleted, so deleting an author can never delete or
 * hide an article.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('author_id')->nullable()->after('city_id')->constrained('authors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('author_id');
        });
    }
};
