<?php

namespace App\Support\Rates;

use App\Enums\RateVerificationStatus;
use App\Models\LenderProductRate;

/**
 * What a rate must satisfy before it may be submitted for approval or
 * published. Enforced by RateWorkflow on every transition towards the public
 * site — the admin form validates the same limits, but the form is not the
 * guard: a bulk action, an import or a restored version all pass through here.
 */
final class RatePublicationRules
{
    /**
     * @return list<string> Empty when the rate may go public.
     */
    public static function problems(LenderProductRate $rate): array
    {
        $problems = [];
        $today = LenderProductRate::today();
        $unit = $rate->rate_unit;
        $maximum = $unit->maximum();
        $rateMin = (float) $rate->rate_min;
        $rateMax = $rate->rate_max !== null ? (float) $rate->rate_max : null;
        $category = $rate->lenderProduct?->loanProduct?->category;

        if ($rate->verification_status !== RateVerificationStatus::Verified) {
            $problems[] = 'Mark the rate as verified once you have checked it against the lender\'s own published rates.';
        }

        if (! self::isOfficialSourceUrl($rate->source_url)) {
            $problems[] = 'Add the https:// link to the lender\'s own page or rate schedule that states this rate.';
        }

        if ($rate->last_verified_at === null) {
            $problems[] = 'Enter the date you last checked the rate on the lender\'s website.';
        } elseif ($rate->last_verified_at->gt($today)) {
            $problems[] = 'The verification date cannot be in the future.';
        } elseif ($rate->last_verified_at->lt($today->subDays(LenderProductRate::REVERIFY_AFTER_DAYS))) {
            $problems[] = 'The rate was last verified more than '.LenderProductRate::REVERIFY_AFTER_DAYS.' days ago — check it again on the lender\'s website and update the date.';
        }

        if ($rateMin <= 0 || $rateMin > $maximum) {
            $problems[] = 'The starting rate must be above 0 and at most '.RateFormat::percent($maximum)." {$unit->suffix()}.";
        }

        if ($rateMax !== null && ($rateMax < $rateMin || $rateMax > $maximum)) {
            $problems[] = 'The maximum rate must be at least the starting rate and at most '.RateFormat::percent($maximum)." {$unit->suffix()}.";
        }

        if ($category !== null && $unit !== $category->rateUnit()) {
            $problems[] = "{$category->getLabel()} rates are published {$category->rateUnit()->suffix()} — change the rate unit.";
        }

        if ($rate->promo_rate !== null) {
            if ((float) $rate->promo_rate <= 0 || (float) $rate->promo_rate > $maximum) {
                $problems[] = 'The promotional rate must be above 0 and at most '.RateFormat::percent($maximum)." {$unit->suffix()}.";
            }

            if ($rate->promo_ends_at === null) {
                $problems[] = 'A promotional rate needs an end date, so it cannot stay on the site after the offer closes.';
            } elseif ($rate->promo_ends_at->isPast()) {
                $problems[] = 'The promotion has already ended — remove the promotional rate or correct its end date.';
            }

            if ($rate->promo_starts_at !== null && $rate->promo_ends_at !== null && $rate->promo_starts_at->gte($rate->promo_ends_at)) {
                $problems[] = 'The promotion must start before it ends.';
            }

            if (blank($rate->promo_label)) {
                $problems[] = 'Name the promotion (e.g. "Festive offer") so customers can tell it from the lender\'s standard rate.';
            }
        }

        if ($rate->effective_until !== null && $rate->effective_until->lt($today)) {
            $problems[] = 'The rate\'s "valid until" date has passed.';
        }

        if ($rate->effective_from !== null && $rate->effective_until !== null && $rate->effective_from->gt($rate->effective_until)) {
            $problems[] = 'The "valid from" date must be on or before the "valid until" date.';
        }

        return $problems;
    }

    public static function isOfficialSourceUrl(?string $url): bool
    {
        return filled($url)
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && str_starts_with(strtolower($url), 'https://');
    }
}
