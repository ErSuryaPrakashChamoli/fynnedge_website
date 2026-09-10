<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-category opt-ins, one row per (subscriber, category).
 *
 * `category` is a plain string rather than an enum column so a new topic is a
 * new NewsletterCategory case plus rows, never a migration. A subscriber with
 * no rows at all is treated as subscribed to everything — that is what a
 * visitor who used the one-field signup form asked for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_subscriber_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->boolean('is_subscribed')->default(true);
            $table->timestamps();

            $table->unique(['newsletter_subscriber_id', 'category'], 'newsletter_prefs_subscriber_category_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_preferences');
    }
};
