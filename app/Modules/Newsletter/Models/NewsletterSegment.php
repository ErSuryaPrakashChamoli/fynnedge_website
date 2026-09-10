<?php

namespace App\Modules\Newsletter\Models;

use App\Modules\Newsletter\Enums\NewsletterCategory;
use Database\Factories\NewsletterSegmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A saved audience filter, stored as criteria rather than a frozen id list so
 * it still means the same thing next month.
 *
 * Status is deliberately not a criterion: subscribers() starts from
 * NewsletterSubscriber::scopeMailable() and narrows from there, so a segment
 * can never widen an audience to include someone who unsubscribed. The worst a
 * malformed segment can do is match nobody.
 */
#[Fillable(['name', 'description', 'criteria', 'is_active'])]
class NewsletterSegment extends Model
{
    /** @use HasFactory<NewsletterSegmentFactory> */
    use HasFactory;

    protected static function newFactory(): NewsletterSegmentFactory
    {
        return NewsletterSegmentFactory::new();
    }

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Mailable subscribers matching this segment.
     *
     * @return Builder<NewsletterSubscriber>
     */
    public function subscribers(): Builder
    {
        return self::applyCriteria(NewsletterSubscriber::query()->mailable(), $this->criteria ?? []);
    }

    /**
     * @param  Builder<NewsletterSubscriber>  $query
     * @param  array<string, mixed>  $criteria
     * @return Builder<NewsletterSubscriber>
     */
    public static function applyCriteria(Builder $query, array $criteria): Builder
    {
        $sources = array_filter((array) ($criteria['sources'] ?? []));
        $categories = array_filter((array) ($criteria['categories'] ?? []));

        if ($sources !== []) {
            $query->whereIn('source', $sources);
        }

        if (filled($criteria['subscribed_after'] ?? null)) {
            $query->where('subscribed_at', '>=', $criteria['subscribed_after']);
        }

        if (filled($criteria['subscribed_before'] ?? null)) {
            $query->where('subscribed_at', '<=', $criteria['subscribed_before']);
        }

        /*
         * "Interested in this category" includes subscribers who have never
         * opened the preferences page — they are opted into everything by
         * default, and excluding them would quietly shrink every topic segment
         * to the handful of people who edited their preferences.
         */
        foreach ($categories as $category) {
            if (! NewsletterCategory::tryFrom((string) $category)) {
                continue;
            }

            $query->where(function (Builder $query) use ($category): void {
                $query->whereDoesntHave('preferences')
                    ->orWhereHas('preferences', fn (Builder $preferences) => $preferences
                        ->where('category', $category)
                        ->where('is_subscribed', true));
            });
        }

        return $query;
    }
}
