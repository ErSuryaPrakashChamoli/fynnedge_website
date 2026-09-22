<?php

namespace App\Support\Calculators;

/**
 * A Flexi Hybrid Term Loan's repayment structure(s) — "initial + subsequent".
 *
 * Preferred source: the lender's own published list (fromStructures()), since
 * lenders don't hold the subsequent tenure fixed — Tata Capital is 1 + 4,
 * 1 + 5, 2 + 5 and 2 + 6, and Aditya Birla offers a 1- or 2-year holiday on
 * the same 7-year loan. Fallback for an offer without that list, derived
 * from its tenure fields:
 *
 * - min_tenure_months: the shortest total tenure offered
 * - initial_tenure_months: the interest-only initial tenure at that minimum
 * - max_tenure_months: the longest total tenure offered
 *
 * The subsequent (principal + interest) tenure stays fixed at
 * min − initial; every extra year above the minimum lengthens the initial
 * tenure, e.g. "2 + 6 or 3 + 6" is 96/108/24.
 */
class FlexiHybridTenure
{
    /**
     * A lender's own structures, sorted by total then initial tenure, with
     * incomplete or duplicate rows dropped.
     *
     * @param  array<int, array{initial_months?: mixed, subsequent_months?: mixed}>  $structures
     * @return list<array{total: int, initial: int, subsequent: int}>
     */
    public static function fromStructures(array $structures): array
    {
        return collect($structures)
            ->filter(fn (mixed $structure): bool => is_array($structure)
                && (int) ($structure['initial_months'] ?? 0) > 0
                && (int) ($structure['subsequent_months'] ?? 0) > 0)
            ->map(fn (array $structure): array => [
                'total' => (int) $structure['initial_months'] + (int) $structure['subsequent_months'],
                'initial' => (int) $structure['initial_months'],
                'subsequent' => (int) $structure['subsequent_months'],
            ])
            ->unique(fn (array $option): string => $option['initial'].'+'.$option['subsequent'])
            ->sortBy([['total', 'asc'], ['initial', 'asc']])
            ->values()
            ->all();
    }

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
     * shorter one on a tie). Several options can share a total (a 1- or
     * 2-year holiday on the same loan): $initialMonths picks among them,
     * else the first — the shortest initial tenure. Null only when there are
     * no options.
     *
     * @param  list<array{total: int, initial: int, subsequent: int}>  $options
     * @return array{total: int, initial: int, subsequent: int}|null
     */
    public static function nearest(array $options, int $totalTenureMonths, ?int $initialMonths = null): ?array
    {
        $closest = null;

        foreach ($options as $option) {
            if ($closest === null || abs($option['total'] - $totalTenureMonths) < abs($closest['total'] - $totalTenureMonths)) {
                $closest = $option;
            }
        }

        if ($closest === null || $initialMonths === null) {
            return $closest;
        }

        foreach ($options as $option) {
            if ($option['total'] === $closest['total'] && $option['initial'] === $initialMonths) {
                return $option;
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
