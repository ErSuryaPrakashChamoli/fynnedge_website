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
        Schema::create('promo_bars', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('eyebrow')->nullable();
            $table->string('headline');
            $table->json('rotating_messages')->nullable();
            $table->string('cta_label');
            $table->string('cta_url');
            $table->boolean('cta_opens_new_tab')->default(false);
            $table->string('image_path')->nullable();
            $table->string('image_alt')->nullable();
            $table->string('background_color', 7)->nullable();
            $table->string('background_color_to', 7)->nullable();
            $table->string('text_color', 7)->nullable();
            $table->string('highlight_color', 7)->nullable();
            $table->string('cta_bg_color', 7)->nullable();
            $table->string('cta_text_color', 7)->nullable();
            $table->boolean('show_countdown')->default(false);
            $table->json('placements')->nullable();
            $table->json('excluded_placements')->nullable();
            $table->string('trigger')->default('scroll');
            $table->unsignedSmallInteger('trigger_value')->default(25);
            $table->string('device')->default('all');
            $table->unsignedSmallInteger('reshow_after_hours')->default(24);
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
        Schema::dropIfExists('promo_bars');
    }
};
