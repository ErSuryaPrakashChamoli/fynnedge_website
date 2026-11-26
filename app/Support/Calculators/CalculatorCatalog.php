<?php

namespace App\Support\Calculators;

use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings;

/**
 * Single source of truth for every calculator link shown in the header mega
 * menu and the /calculators directory page — so the two don't drift apart.
 */
class CalculatorCatalog
{
    /**
     * Group label => its calculators, labels in the visitor's language (the
     * admin panel always runs in English).
     *
     * $includeHidden adds calculators that are not public yet (see
     * debtConsolidationCalculators()); only admin screens ask for them.
     *
     * @return array<string, array<int, array{label: string, route: string, params: array<string, string>}>>
     */
    public static function groups(bool $includeHidden = false): array
    {
        return [
            __('Investment Calculators') => [
                ['label' => __('Fixed Deposit Calculator'), 'route' => 'calculators.fixed-deposit', 'params' => []],
                ['label' => __('GST Calculator'), 'route' => 'calculators.gst', 'params' => []],
                ['label' => __('SIP Calculator'), 'route' => 'calculators.sip', 'params' => []],
                ['label' => __('Daily SIP Calculator'), 'route' => 'calculators.daily-sip', 'params' => []],
            ],
            __('Loan EMI Calculators') => [
                ['label' => __('Personal Loan EMI Calculator'), 'route' => 'calculators.emi', 'params' => ['category' => 'personal-loan']],
                ['label' => __('Flexi Hybrid Term Loan EMI Calculator'), 'route' => 'calculators.emi', 'params' => ['category' => 'flexi-hybrid-term-loan']],
                ['label' => __('Home Loan EMI Calculator'), 'route' => 'calculators.emi', 'params' => ['category' => 'home-loan']],
                ['label' => __('Business Loan EMI Calculator'), 'route' => 'calculators.emi', 'params' => ['category' => 'business-loan']],
                ['label' => __('Gold Loan EMI Calculator'), 'route' => 'calculators.emi', 'params' => ['category' => 'gold-loan']],
                ['label' => __('Two Wheeler Loan EMI Calculator'), 'route' => 'calculators.emi', 'params' => ['category' => 'two-wheeler-loan']],
                ['label' => __('Car Loan EMI Calculator'), 'route' => 'calculators.emi', 'params' => ['category' => 'car-loan']],
                ['label' => __('Loan Against Property EMI Calculator'), 'route' => 'calculators.emi', 'params' => ['category' => 'loan-against-property']],
                ['label' => __('Term Loan EMI Calculator'), 'route' => 'calculators.emi', 'params' => ['category' => 'term-loan']],
                ['label' => __('Tractor Loan EMI Calculator'), 'route' => 'calculators.emi', 'params' => ['category' => 'tractor-loan']],
                ['label' => __('Mudra Loan EMI Calculator'), 'route' => 'calculators.emi', 'params' => ['category' => 'mudra-loan']],
            ],
            __('Loan Eligibility Calculators') => [
                ['label' => __('Personal Loan Eligibility Calculator'), 'route' => 'calculators.eligibility', 'params' => ['category' => 'personal-loan']],
                ['label' => __('Home Loan Eligibility Calculator'), 'route' => 'calculators.eligibility', 'params' => ['category' => 'home-loan']],
                ['label' => __('Home Loan Prepayment Calculator'), 'route' => 'calculators.prepayment', 'params' => ['category' => 'home-loan']],
                ['label' => __('Personal Loan Prepayment Calculator'), 'route' => 'calculators.prepayment', 'params' => ['category' => 'personal-loan']],
                ...self::debtConsolidationCalculators($includeHidden),
            ],
        ];
    }

    /**
     * The Debt Consolidation Savings Calculator is public (menus, /calculators,
     * sitemap, homepage count) only while the Debt Consolidation service is
     * live. Its copy is part of the service's customer wording, so it goes
     * public with the service and never before it. Admin screens still list it
     * so its About text and indexing can be prepared, and so a saved indexing
     * choice for it stays a valid option while the service is off.
     *
     * @return list<array{label: string, route: string, params: array<string, string>}>
     */
    private static function debtConsolidationCalculators(bool $includeHidden): array
    {
        return $includeHidden || DebtConsolidationSettings::enabled()
            ? [['label' => __('Debt Consolidation Savings Calculator'), 'route' => 'calculators.debt-consolidation', 'params' => []]]
            : [];
    }

    /**
     * Every calculator page as key => label, keyed by its path under
     * /calculators (CalculatorIndexing::pageKey(), e.g. `gst` or
     * `emi/home-loan`). The same key identifies the page's CalculatorPage
     * "About" content and its search indexing setting. Includes calculators
     * that are not public yet: this list feeds admin screens only.
     *
     * @return array<string, string>
     */
    public static function pages(): array
    {
        return collect(self::groups(includeHidden: true))
            ->flatten(1)
            ->mapWithKeys(fn (array $calculator): array => [
                CalculatorIndexing::pageKey($calculator['route'], $calculator['params']) => $calculator['label'],
            ])
            ->all();
    }
}
