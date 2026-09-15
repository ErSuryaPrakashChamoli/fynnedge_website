<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('video_testimonials', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('customer_name');
            $table->string('role_location')->nullable();
            $table->string('loan_category')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('headline')->nullable();
            $table->text('quote')->nullable();
            $table->string('video_source')->default('upload');
            $table->string('video_path')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('poster_path')->nullable();
            $table->string('poster_alt')->nullable();
            $table->json('placements')->nullable();
            $table->boolean('show_as_floating')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_testimonials');
    }
};
