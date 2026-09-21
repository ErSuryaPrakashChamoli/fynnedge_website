<?php

namespace App\Support\Calculators;

/**
 * The Flexi Hybrid Term Loan's fixed tenure structure. It is offered only
 * for an 8- or 9-year total tenure, and the interest-only initial tenure is
 * set by that total: 2 years on an 8-year loan ("2 + 6") and 3 years on a
 * 9-year loan ("3 + 6"). The rest of the tenure is the principal + interest
 * subsequent tenure.
 */
class FlexiHybridTenure
{
    /**
     * Total tenure (months) => interest-only initial tenure (months).
     *
     * @var array<int, int>
     */
    public const INITIAL_MONTHS_BY_TOTAL_MONTHS = [
        96 => 24,
        108 => 36,
    ];

    /**
     * @return array<int, int>
     */
    public static function totalTenureOptions(): array
    {
        return array_keys(self::INITIAL_MONTHS_BY_TOTAL_MONTHS);
    }

    public static function minTotalMonths(): int
    {
        return min(self::totalTenureOptions());
    }

    public static function maxTotalMonths(): int
    {
        return max(self::totalTenureOptions());
    }

    public static function isAllowed(int $totalTenureMonths): bool
    {
        return array_key_exists($totalTenureMonths, self::INITIAL_MONTHS_BY_TOTAL_MONTHS);
    }

    /**
     * Snaps any tenure to the nearest allowed total tenure (8 or 9 years).
     */
    public static function nearestAllowed(int $totalTenureMonths): int
    {
        $closest = self::minTotalMonths();

        foreach (self::totalTenureOptions() as $option) {
            if (abs($option - $totalTenureMonths) < abs($closest - $totalTenureMonths)) {
                $closest = $option;
            }
        }

        return $closest;
    }

    public static function initialMonthsFor(int $totalTenureMonths): int
    {
        return self::INITIAL_MONTHS_BY_TOTAL_MONTHS[self::nearestAllowed($totalTenureMonths)];
    }

    public static function subsequentMonthsFor(int $totalTenureMonths): int
    {
        $total = self::nearestAllowed($totalTenureMonths);

        return $total - self::INITIAL_MONTHS_BY_TOTAL_MONTHS[$total];
    }
}
