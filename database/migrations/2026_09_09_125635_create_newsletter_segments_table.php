<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A saved audience filter, stored as criteria rather than a frozen list of
 * subscriber ids: a segment has to mean the same thing next month, when new
 * people have joined and others have unsubscribed.
 *
 * Criteria shape (all optional, ANDed):
 *   {"sources": ["blog"], "categories": ["credit"], "subscribed_after": "2026-01-01", "subscribed_before": "2026-06-30"}
 *
 * Status is deliberately NOT a criterion — every campaign send is restricted to
 * active subscribers regardless of segment, so a segment can never be the thing
 * that mails an unsubscribed address.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_segments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->json('criteria')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_segments');
    }
};
