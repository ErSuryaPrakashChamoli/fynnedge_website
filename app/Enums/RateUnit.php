<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The period a stored percentage applies to. Loans are quoted per annum;
 * credit-card finance charges are quoted per month by the issuers, and are
 * stored that way rather than converted, so the figure on the site is the
 * one on the issuer's own schedule.
 */
enum RateUnit: string implements HasLabel
{
    case PerAnnum = 'per_annum';
    case PerMonth = 'per_month';

    public function getLabel(): string
    {
        return match ($this) {
            self::PerAnnum => '% per annum',
            self::PerMonth => '% per month',
        };
    }

    public function suffix(): string
    {
        return match ($this) {
            self::PerAnnum => __('p.a.'),
            self::PerMonth => __('per month'),
        };
    }

    /**
     * The highest figure the admin form accepts for this unit — well above any
     * real Indian retail rate (RBI-regulated lenders top out near 36% p.a.;
     * card finance charges near 4% a month), so it only catches typing errors
     * such as 105 for 10.5.
     */
    public function maximum(): float
    {
        return match ($this) {
            self::PerAnnum => 60.0,
            self::PerMonth => 5.0,
        };
    }
}
