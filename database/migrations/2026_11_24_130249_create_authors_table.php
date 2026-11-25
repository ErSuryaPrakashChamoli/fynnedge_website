<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The people who write FynnEdge articles (Admin → Blog & Content → Authors),
 * shown as a byline, an "About the author" box and a public author page at
 * /resources/authors/{slug}. Replaces the interim `blog_authors` setting;
 * 2026_11_24_130252 copies that setting's rows in.
 *
 * Name, designation and bio are visitor text, so they have `_hi` siblings
 * (varchar(500) / text) like every other translatable column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('name_hi', 500)->nullable();
            $table->string('slug')->unique()->comment('Public author page: /resources/authors/{slug}');
            $table->string('designation')->nullable();
            $table->string('designation_hi', 500)->nullable();
            $table->text('bio')->nullable();
            $table->text('bio_hi')->nullable();
            $table->string('photo_path')->nullable()->comment('public disk, authors/');
            $table->string('profile_url')->nullable()->comment('https only');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authors');
    }
};
