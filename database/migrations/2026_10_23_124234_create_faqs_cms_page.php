<?php

use App\Enums\PublishStatus;
use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * /faqs now takes its heading, intro and SEO from a `pages` row (slug
     * `faqs`, under Content → Pages) and 404s without one, like /about and
     * /careers — so every install needs that row before FaqController serves a
     * request. The copy is what the page showed while it was hardcoded, in both
     * languages. An existing row, even a deleted one, is left alone.
     */
    public function up(): void
    {
        if (Page::withTrashed()->where('slug', 'faqs')->exists()) {
            return;
        }

        Page::query()->create([
            'slug' => 'faqs',
            'title' => 'Frequently asked questions',
            'title_hi' => 'अक्सर पूछे जाने वाले सवाल',
            'excerpt' => 'Answers to common questions about how FynnEdge works, eligibility checks, and applying for a loan.',
            'excerpt_hi' => 'FynnEdge कैसे काम करता है, पात्रता जांच और लोन के लिए आवेदन से जुड़े आम सवालों के जवाब।',
            'body' => '<p>Have a question about a specific loan product? Check that product\'s page, or <a href="/contact" class="text-accent underline">get in touch</a>.</p>',
            'body_hi' => '<p>किसी खास लोन प्रोडक्ट के बारे में सवाल है? उस प्रोडक्ट का पेज देखें, या <a href="/contact" class="text-accent underline">हमसे संपर्क करें</a>।</p>',
            'status' => PublishStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function down(): void
    {
        //
    }
};
