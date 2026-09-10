<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Newsletter subscribers are a standalone entity, deliberately unrelated to
 * customers, journey sessions or applications: someone who wants monthly
 * finance tips has not applied for anything, and joining the two would quietly
 * turn a mailing list into a lead database. Nothing here references a lead,
 * customer or external CRM id.
 *
 * `email` is uniquely indexed, so re-subscribing updates the existing row
 * rather than creating a second one — the tokens, consent record and status
 * all live on that single row per address.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('status')->default('pending');
            $table->string('source')->nullable()->comment('homepage, blog, footer, blog_index, …');
            $table->string('source_url')->nullable()->comment('The exact page the visitor subscribed from');

            // Hashed, never stored in the clear — see NewsletterSubscriber::issueToken().
            $table->string('confirmation_token', 64)->nullable()->index();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->string('unsubscribe_token', 64)->nullable()->index();

            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();

            // Proof of what the visitor agreed to, and from where.
            $table->timestamp('consent_at')->nullable();
            $table->string('consent_ip', 45)->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at'], 'newsletter_subs_status_created_index');
            $table->index(['source', 'created_at'], 'newsletter_subs_source_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
