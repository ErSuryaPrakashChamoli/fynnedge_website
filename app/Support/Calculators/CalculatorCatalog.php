<?php

namespace App\Support\Calculators;

/**
 * Single source of truth for every calculator link shown in the header mega
 * menu and the /calculators directory page — so the two don't drift apart.
 */
class CalculatorCatalog
{
    /**
     * @return array<string, array<int, array{label: string, route: string, params: array<string, string>}>>
     */
    public static function groups(): array
    {
        return [
            'Investment Calculators' => [
                ['label' => 'Fixed Deposit Calculator', 'route' => 'calculators.fixed-deposit', 'params' => []],
                ['label' => 'GST Calculator', 'route' => 'calculators.gst', 'params' => []],
                ['label' => 'SIP Calculator', 'route' => 'calculators.sip', 'params' => []],
                ['label' => 'Daily SIP Calculator', 'route' => 'calculators.daily-sip', 'params' => []],
            ],
            'Loan EMI Calculators' => [
                ['label' => 'Personal Loan EMI Calculator', 'route' => 'calculators.emi', 'params' => ['category' => 'personal-loan']],
                ['label' => 'Flexi Hybrid Term Loan EMI Calculator', 'route' => 'calculators.emi', 'params' => ['category' => 'flexi-hybrid-term-loan']],
                ['label' => 'Home Loan EMI Calculator', 'route' => 'calculators.emi', 'params' => ['category' => 'home-loan']],
                ['label' => 'Business Loan EMI Calculator', 'route' => 'calculators.emi', 'params' => ['category' => 'business-loan']],
                ['label' => 'Gold Loan EMI Calculator', 'route' => 'calculators.emi', 'params' => ['category' => 'gold-loan']],
                ['label' => 'Two Wheeler Loan EMI Calculator', 'route' => 'calculators.emi', 'params' => ['category' => 'two-wheeler-loan']],
                ['label' => 'Loan Against Property EMI Calculator', 'route' => 'calculators.emi', 'params' => ['category' => 'loan-against-property']],
                ['label' => 'Term Loan EMI Calculator', 'route' => 'calculators.emi', 'params' => ['category' => 'term-loan']],
                ['label' => 'Tractor Loan EMI Calculator', 'route' => 'calculators.emi', 'params' => ['category' => 'tractor-loan']],
                ['label' => 'Mudra Loan EMI Calculator', 'route' => 'calculators.emi', 'params' => ['category' => 'mudra-loan']],
            ],
            'Loan Eligibility Calculators' => [
                ['label' => 'Personal Loan Eligibility Calculator', 'route' => 'calculators.eligibility', 'params' => ['category' => 'personal-loan']],
                ['label' => 'Home Loan Eligibility Calculator', 'route' => 'calculators.eligibility', 'params' => ['category' => 'home-loan']],
                ['label' => 'Home Loan Prepayment Calculator', 'route' => 'calculators.prepayment', 'params' => ['category' => 'home-loan']],
                ['label' => 'Personal Loan Prepayment Calculator', 'route' => 'calculators.prepayment', 'params' => ['category' => 'personal-loan']],
            ],
        ];
    }
}
