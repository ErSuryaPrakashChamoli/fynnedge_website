<?php

namespace App\Support\Enquiries;

use App\Enums\LenderStatus;
use App\Models\Lender;
use Illuminate\Support\Collection;

/**
 * The lenders shown publicly as partners: the Quick Enquiry page's logo strip
 * and the full list on /partners read from the same set, so the strip's
 * "+N more" count always matches what that page lists.
 */
class PartnerLenders
{
    /**
     * Every active lender, those with an uploaded logo first and A–Z within
     * each group, so a list opens on real logos rather than initials.
     *
     * @return Collection<int, Lender>
     */
    public static function all(): Collection
    {
        return Lender::query()
            ->where('status', LenderStatus::Active)
            ->orderBy('name')
            ->get()
            ->sortByDesc(fn (Lender $lender): bool => filled($lender->logoUrl()))
            ->values();
    }

    /**
     * The admin's hand-picked lenders in their chosen order, or the start of
     * $lenders when none are picked — capped at $limit either way. A picked
     * lender that has since been deactivated or deleted simply drops out.
     *
     * @param  Collection<int, Lender>  $lenders
     * @param  array<int, int>  $featuredIds
     * @return Collection<int, Lender>
     */
    public static function featured(Collection $lenders, array $featuredIds, int $limit): Collection
    {
        $chosen = $featuredIds === []
            ? $lenders
            : collect($featuredIds)->map(fn (int $id): ?Lender => $lenders->firstWhere('id', $id))->filter();

        return $chosen->take($limit)->values();
    }
}
