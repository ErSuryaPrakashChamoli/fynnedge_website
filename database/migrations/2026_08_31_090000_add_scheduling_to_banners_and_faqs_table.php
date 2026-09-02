<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 7 content-team audit: Banner (seasonal/campaign carousel slides)
     * and Faq (time-bound Q&A) are both content the team reasonably needs to
     * schedule ahead of time and let expire automatically — unlike e.g.
     * HowItWorksStep, which is deliberately left as a simple draft/publish
     * toggle. Adopts the same published_at/expires_at pair already used by
     * LoanProduct/Article/Page/Testimonial/LoanLandingPage/MarketingSection
     * via the existing Publishable trait — no new scheduling mechanism.
     */
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('status');
            $table->timestamp('expires_at')->nullable()->after('published_at');
        });

        Schema::table('faqs', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('status');
            $table->timestamp('expires_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn(['published_at', 'expires_at']);
        });

        Schema::table('faqs', function (Blueprint $table) {
            $table->dropColumn(['published_at', 'expires_at']);
        });
    }
};
