<?php

namespace App\Support\Calculators;

/**
 * A Flexi Hybrid Term Loan's repayment structure(s) — "initial + subsequent"
 * — derived from a lender offer's own tenure fields rather than hardcoded
 * per lender:
 *
 * - min_tenure_months: the shortest total tenure offered
 * - initial_tenure_months: the interest-only initial tenure at that minimum
 * - max_tenure_months: the longest total tenure offered
 *
 * The subsequent (principal + interest) tenure stays fixed at
 * min − initial; every extra year above the minimum lengthens the initial
 * tenure. So Kotak's "1 + 5" is 72/72/12, Piramal's and Tata Capital's
 * "2 + 5" is 84/84/24, and Bajaj's "2 + 6 or 3 + 6" is 96/108/24.
 */
class FlexiHybridTenure
{
    /**
     * @return list<array{total: int, initial: int, subsequent: int}>
     */
    public static function options(?int $minTotalMonths, ?int $maxTotalMonths, ?int $initialMonths): array
    {
        if ($minTotalMonths === null || $initialMonths === null || $initialMonths <= 0 || $minTotalMonths <= $initialMonths) {
            return [];
        }

        $subsequentMonths = $minTotalMonths - $initialMonths;
        $maxTotalMonths = max($minTotalMonths, $maxTotalMonths ?? $minTotalMonths);
        $options = [];

        for ($total = $minTotalMonths; $total <= $maxTotalMonths; $total += 12) {
            $options[] = ['total' => $total, 'initial' => $total - $subsequentMonths, 'subsequent' => $subsequentMonths];
        }

        return $options;
    }

    /**
     * The option with this exact total tenure, or the closest one (the
     * shorter one on a tie). Null only when there are no options.
     *
     * @param  list<array{total: int, initial: int, subsequent: int}>  $options
     * @return array{total: int, initial: int, subsequent: int}|null
     */
    public static function nearest(array $options, int $totalTenureMonths): ?array
    {
        $closest = null;

        foreach ($options as $option) {
            if ($closest === null || abs($option['total'] - $totalTenureMonths) < abs($closest['total'] - $totalTenureMonths)) {
                $closest = $option;
            }
        }

        return $closest;
    }

    /**
     * @param  array{total: int, initial: int, subsequent: int}  $option
     */
    public static function label(array $option): string
    {
        return self::years($option['initial']).' + '.self::years($option['subsequent']).' yrs';
    }

    public static function years(int $months): string
    {
        return $months % 12 === 0 ? (string) ($months / 12) : rtrim(rtrim(number_format($months / 12, 1), '0'), '.');
    }
}
