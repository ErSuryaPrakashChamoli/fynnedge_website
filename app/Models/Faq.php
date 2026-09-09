<?php

namespace App\Models;

use App\Enums\FaqPlacement;
use App\Enums\PublishStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\Publishable;
use Database\Factories\FaqFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['question', 'answer', 'sort_order', 'status', 'faqable_type', 'faqable_id', 'placements', 'published_at', 'expires_at'])]
class Faq extends Model
{
    /** @use HasFactory<FaqFactory> */
    use Auditable, HasFactory, HasPublicId, Publishable;

    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
            'placements' => 'array',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * FAQs pinned to a page by route name. `placements` holds route-name
     * strings, so a single FAQ can appear on several pages at once — see
     * App\Enums\FaqPlacement.
     */
    public function scopeForPlacement(Builder $query, FaqPlacement|string $placement): void
    {
        $query->whereJsonContains('placements', $placement instanceof FaqPlacement ? $placement->value : $placement);
    }

    /**
     * Matches an FAQ pinned to ANY of the given tokens.
     *
     * A page resolves to more than one token — the route-wide `loans.show` and
     * the page-specific `loans.show:personal-loan` — so an FAQ pinned to every
     * loan page and one pinned to just this product both have to surface. See
     * App\Support\Faqs\FaqPlacements.
     *
     * @param  array<int, string>  $tokens
     */
    public function scopeForPlacements(Builder $query, array $tokens): void
    {
        if ($tokens === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $query) use ($tokens): void {
            foreach ($tokens as $token) {
                $query->orWhereJsonContains('placements', $token);
            }
        });
    }

    public function faqable(): MorphTo
    {
        return $this->morphTo();
    }
}
