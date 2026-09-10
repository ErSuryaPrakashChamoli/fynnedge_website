<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The per-subscriber delivery log for a campaign — and the send ledger itself.
 *
 * Rows are created BEFORE any mail is queued, with a unique (campaign,
 * subscriber) index, so a re-dispatched or retried campaign job cannot mail the
 * same person twice: the insert is what claims the recipient. No subscriber
 * data is copied here; the address is always read through the relation, so an
 * unsubscribe is respected right up to the moment of sending.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('newsletter_subscriber_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('tracking_token', 64)->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['newsletter_campaign_id', 'newsletter_subscriber_id'], 'newsletter_recipients_unique');
            $table->index(['newsletter_campaign_id', 'status'], 'newsletter_recipients_campaign_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaign_recipients');
    }
};
