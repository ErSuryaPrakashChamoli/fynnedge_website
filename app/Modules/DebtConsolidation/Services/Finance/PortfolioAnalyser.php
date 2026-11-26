<?php

namespace App\Modules\DebtConsolidation\Services\Finance;

use App\Modules\DebtConsolidation\DataTransferObjects\LiabilityInput;
use App\Modules\DebtConsolidation\DataTransferObjects\LiabilityProjection;
use App\Modules\DebtConsolidation\DataTransferObjects\PortfolioSummary;

/**
 * Weighted average rate is weighted by outstanding principal, using the rate
 * each projection actually used. Concentration index is the
 * Herfindahl-Hirschman index of lender shares (0–10,000; above 2,500 means
 * the debt is concentrated with few lenders). Maturity buckets use the
 * projected remaining months.
 */
final class PortfolioAnalyser
{
    public const MATURITY_BUCKETS = [
        'up_to_12' => [1, 12],
        '13_to_36' => [13, 36],
        '37_to_60' => [37, 60],
        '61_to_120' => [61, 120],
        'over_120' => [121, PHP_INT_MAX],
    ];

    /**
     * @param  list<LiabilityInput>  $liabilities
     * @param  array<string, LiabilityProjection>  $projections  keyed by liability key
     */
    public function summarise(array $liabilities, array $projections): PortfolioSummary
    {
        $total = 0.0;
        $monthly = 0.0;
        $rateWeight = 0.0;
        $highest = 0.0;
        $secured = 0.0;
        $overdue = 0.0;
        $inArrears = 0;
        $repayment = 0.0;
        $interest = 0.0;
        $byType = [];
        $byLender = [];
        $maturity = array_fill_keys(array_keys(self::MATURITY_BUCKETS), ['count' => 0, 'outstanding' => 0.0]);

        foreach ($liabilities as $liability) {
            $projection = $projections[$liability->key] ?? LiabilityProjection::empty();
            $outstanding = Money::round($liability->outstandingPrincipal);
            $rate = $projection->effectiveAnnualRate;

            $total += $outstanding;
            $monthly += $projection->monthlyObligation;
            $rateWeight += $outstanding * $rate;
            $highest = max($highest, $rate);
            $secured += $liability->isSecured() ? $outstanding : 0.0;
            $overdue += $liability->overdueAmount;
            $inArrears += ($liability->overdueAmount > 0 || $liability->daysPastDue > 0) ? 1 : 0;
            $repayment += $projection->schedule->total();
            $interest += $projection->schedule->totalInterest();

            $byType[$liability->type->value] ??= ['type' => $liability->type->value, 'count' => 0, 'outstanding' => 0.0];
            $byType[$liability->type->value]['count']++;
            $byType[$liability->type->value]['outstanding'] += $outstanding;

            $lender = trim($liability->lenderName) !== '' ? trim($liability->lenderName) : 'Unnamed lender';
            $lenderKey = mb_strtolower($lender);
            $byLender[$lenderKey] ??= ['lender' => $lender, 'count' => 0, 'outstanding' => 0.0];
            $byLender[$lenderKey]['count']++;
            $byLender[$lenderKey]['outstanding'] += $outstanding;

            $months = $projection->schedule->months();

            foreach (self::MATURITY_BUCKETS as $bucket => [$from, $to]) {
                if ($months >= $from && $months <= $to) {
                    $maturity[$bucket]['count']++;
                    $maturity[$bucket]['outstanding'] = Money::round($maturity[$bucket]['outstanding'] + $outstanding);
                }
            }
        }

        $total = Money::round($total);
        $withShare = fn (array $row): array => [...$row, 'outstanding' => Money::round($row['outstanding']), 'share' => Money::percentOf($row['outstanding'], $total)];
        $byType = array_values(array_map($withShare, $byType));
        $byLender = array_values(array_map($withShare, $byLender));
        usort($byType, fn (array $a, array $b): int => $b['outstanding'] <=> $a['outstanding']);
        usort($byLender, fn (array $a, array $b): int => $b['outstanding'] <=> $a['outstanding']);

        return new PortfolioSummary(
            liabilityCount: count($liabilities),
            totalOutstanding: $total,
            totalMonthlyObligation: Money::round($monthly),
            weightedAverageRate: $total > 0 ? round($rateWeight / $total, 2) : 0.0,
            highestRate: round($highest, 2),
            securedOutstanding: Money::round($secured),
            unsecuredOutstanding: Money::round($total - $secured),
            securedShare: Money::percentOf($secured, $total),
            totalOverdue: Money::round($overdue),
            liabilitiesInArrears: $inArrears,
            remainingRepayment: Money::round($repayment),
            remainingInterest: Money::round($interest),
            largestLenderShare: $byLender === [] ? null : $byLender[0]['share'],
            concentrationIndex: $total > 0 ? round(array_sum(array_map(fn (array $row): float => ($row['share'] ?? 0) ** 2, $byLender)), 0) : null,
            byType: $byType,
            byLender: $byLender,
            maturityProfile: $maturity,
        );
    }
}
