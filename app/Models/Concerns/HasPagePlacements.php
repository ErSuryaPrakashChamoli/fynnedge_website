<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * For content pinned to pages through a `placements` JSON column holding the
 * FAQ placement vocabulary (see App\Support\Faqs\FaqPlacements), plus each
 * model's own site-wide token.
 */
trait HasPagePlacements
{
    /**
     * Matches a record pinned to ANY of the given placement tokens.
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
}
