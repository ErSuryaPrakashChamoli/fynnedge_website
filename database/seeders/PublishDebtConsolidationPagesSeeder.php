<?php

namespace Database\Seeders;

use App\Enums\PublishStatus;
use App\Models\Faq;
use App\Models\Page;
use App\Support\Localization\AutoTranslation;
use App\Support\Pages\DebtConsolidationPages;
use Illuminate\Database\Seeder;

/**
 * Publishes the seven Debt Consolidation pages and their FAQs, effective now,
 * instead of opening each page in the admin:
 *
 *   php artisan db:seed --class=PublishDebtConsolidationPagesSeeder --force
 *
 * Each page and FAQ becomes Published with no expiry. A missing or future
 * publish date becomes now: the admin's date box is read in UTC, so a time
 * typed in Indian time lands 5½ hours ahead and keeps the page hidden.
 * Nothing else changes (no wording, no other page, no machine translation),
 * and running it again changes nothing.
 *
 * It does not switch the service on: while Debt Consolidation Settings has
 * the service off, the pages still answer 404.
 */
class PublishDebtConsolidationPagesSeeder extends Seeder
{
    public function run(): void
    {
        AutoTranslation::paused(function (): void {
            $pages = Page::query()->whereIn('slug', DebtConsolidationPages::slugs())->get();
            $missing = array_diff(DebtConsolidationPages::slugs(), $pages->pluck('slug')->all());
            $faqs = 0;

            foreach ($pages as $page) {
                $this->publish($page);

                foreach ($page->faqs()->get() as $faq) {
                    $this->publish($faq);
                    $faqs++;
                }
            }

            $this->command?->info("Published {$pages->count()} Debt Consolidation page(s) and {$faqs} FAQ(s).");

            if ($missing !== []) {
                $this->command?->warn('Not found (run the migrations first): '.implode(', ', $missing));
            }
        });
    }

    private function publish(Page|Faq $record): void
    {
        $record->status = PublishStatus::Published;

        if ($record->published_at === null || $record->published_at->isFuture()) {
            $record->published_at = now();
        }

        $record->expires_at = null;

        if ($record->isDirty()) {
            $record->save();
        }
    }
}
