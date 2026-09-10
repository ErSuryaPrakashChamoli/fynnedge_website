<?php

namespace Database\Seeders;

use App\Modules\Newsletter\Enums\NewsletterCategory;
use App\Modules\Newsletter\Enums\SubscriptionSource;
use App\Modules\Newsletter\Models\NewsletterSegment;
use App\Modules\Newsletter\Models\NewsletterTemplate;
use Illuminate\Database\Seeder;

/**
 * Starter segments and one template — a working example an admin can rename or
 * delete, not a fixed taxonomy. Deliberately no subscribers: an address may
 * only enter the list through its owner's own consented signup, so seeding
 * subscribers would create records with no real consent behind them.
 *
 * Idempotent (updateOrCreate on name), so re-running the seeder on an existing
 * install cannot produce duplicate segments.
 */
class NewsletterSeeder extends Seeder
{
    public function run(): void
    {
        $segments = [
            [
                'name' => 'All active subscribers',
                'description' => 'Everyone who has confirmed their email and not unsubscribed.',
                'criteria' => [],
            ],
            [
                'name' => 'Blog subscribers',
                'description' => 'People who signed up from an article or the blog listing.',
                'criteria' => ['sources' => [SubscriptionSource::Blog->value, SubscriptionSource::BlogIndex->value]],
            ],
            [
                'name' => 'Homepage subscribers',
                'description' => 'People who signed up from the homepage.',
                'criteria' => ['sources' => [SubscriptionSource::Homepage->value]],
            ],
            [
                'name' => 'Credit & CIBIL',
                'description' => 'Subscribers interested in credit scores and reports.',
                'criteria' => ['categories' => [NewsletterCategory::CreditAndCibil->value]],
            ],
            [
                'name' => 'Loans',
                'description' => 'Subscribers interested in loan products and eligibility.',
                'criteria' => ['categories' => [NewsletterCategory::Loans->value]],
            ],
        ];

        foreach ($segments as $segment) {
            NewsletterSegment::query()->updateOrCreate(
                ['name' => $segment['name']],
                [...$segment, 'is_active' => true],
            );
        }

        NewsletterTemplate::query()->updateOrCreate(
            ['name' => 'Monthly insights'],
            [
                'description' => 'A short intro, one featured article and a closing line.',
                'is_active' => true,
                'content' => implode("\n", [
                    '<p>Hi there,</p>',
                    '<p>Here is what we have been writing about this month.</p>',
                    '<h2>Featured article</h2>',
                    '<p>Replace this with the article summary, or pick a blog article on the campaign form to fill it in automatically.</p>',
                    '<p>Until next month,<br>The FynnEdge team</p>',
                ]),
            ],
        );
    }
}
