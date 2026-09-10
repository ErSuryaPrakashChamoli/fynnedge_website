<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One newsletter send. `article_id` records which blog post the content was
 * generated from, for reporting — the content itself is copied into `content`
 * at generation time and stays editable, so a later edit to the article never
 * silently rewrites an email that has already gone out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject');
            $table->string('preview_text')->nullable();
            $table->longText('content');
            $table->string('status')->default('draft');
            $table->foreignId('newsletter_segment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('newsletter_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'scheduled_at'], 'newsletter_campaigns_status_scheduled_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaigns');
    }
};
