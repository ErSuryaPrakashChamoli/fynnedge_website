<?php

namespace App\Modules\DebtConsolidation\Services;

use App\Modules\DebtConsolidation\Enums\LiabilityAccountStatus;
use App\Modules\DebtConsolidation\Enums\LiabilityType;
use App\Modules\DebtConsolidation\Enums\RiskFlag;
use App\Support\Formatting\IndianNumberFormatter;

/**
 * Shared display helpers for Debt Consolidation figures and flags, used by
 * the public calculators, the journey, the portal, the admin and the PDFs —
 * so a figure or a warning reads the same everywhere.
 */
final class DebtPresenter
{
    /**
     * ₹ with Indian digit grouping. Whole rupees unless $paise.
     */
    public static function inr(float|int|string|null $amount, bool $paise = false): string
    {
        if ($amount === null || $amount === '') {
            return '—';
        }

        $amount = (float) $amount;
        $sign = $amount < 0 ? '−' : '';
        $absolute = abs($amount);

        if (! $paise) {
            return $sign.'₹'.IndianNumberFormatter::format($absolute);
        }

        $rupees = (int) floor(round($absolute, 2));
        $fraction = (int) round((round($absolute, 2) - $rupees) * 100);

        return $sign.'₹'.IndianNumberFormatter::format($rupees).'.'.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }

    public static function percent(float|int|string|null $value, int $decimals = 2): string
    {
        return $value === null || $value === '' ? '—' : rtrim(rtrim(number_format((float) $value, $decimals), '0'), '.').'%';
    }

    public static function months(?int $months): string
    {
        if ($months === null) {
            return '—';
        }

        $years = intdiv($months, 12);
        $rest = $months % 12;

        return match (true) {
            $years === 0 => trans_choice(':count month|:count months', $rest, ['count' => $rest]),
            $rest === 0 => trans_choice(':count year|:count years', $years, ['count' => $years]),
            default => __(':years y :months m', ['years' => $years, 'months' => $rest]),
        };
    }

    /**
     * A flag as one customer-readable sentence.
     *
     * @param  array{flag: string, liability_key?: ?string, context?: array<string, mixed>}  $flag
     * @param  array<string, string>  $liabilityNames  liability key => "HDFC Bank personal loan"
     */
    public static function flagSentence(array $flag, array $liabilityNames = []): string
    {
        $type = RiskFlag::tryFrom((string) $flag['flag']);
        $context = $flag['context'] ?? [];
        $loan = $liabilityNames[$flag['liability_key'] ?? ''] ?? __('one of your loans');

        return match ($type) {
            RiskFlag::NegativeSavings => __('Consolidating on these terms would cost :amount more in total than keeping your current loans.', ['amount' => self::inr(abs((float) ($context['net_benefit'] ?? 0)))]),
            RiskFlag::LowerEmiHigherCost => __('Your monthly outgo would fall by :monthly, but you would repay :amount more overall. A lower EMI is not a saving on its own.', ['monthly' => self::inr($context['monthly_difference'] ?? 0), 'amount' => self::inr(abs((float) ($context['net_benefit'] ?? 0)))]),
            RiskFlag::TenureExtended => __('You would be in debt :months longer than today.', ['months' => self::months((int) ($context['months'] ?? 0))]),
            RiskFlag::ForeclosureUnverified => __('The closure amount for :loan is an estimate. Ask the lender for a foreclosure letter to confirm it.', ['loan' => $loan]),
            RiskFlag::QuotationExpired => __('The foreclosure quotation for :loan expired on :date. A fresh quotation is needed.', ['loan' => $loan, 'date' => (string) ($context['valid_until'] ?? '')]),
            RiskFlag::OverdueLiabilities => __('Some loans have overdue payments. Lenders look closely at recent overdues.'),
            RiskFlag::NotConsolidatable => __(':loan cannot be consolidated now (:reason). It is left out of the comparison.', ['loan' => $loan, 'reason' => self::blockerReason($context)]),
            RiskFlag::ForeclosureLockIn => __(':loan cannot be foreclosed before :date.', ['loan' => $loan, 'date' => (string) ($context['until'] ?? '')]),
            RiskFlag::NoEligibleRoute => __('No standard financing route takes over :loan. An advisor will check lender-specific options.', ['loan' => $loan]),
            RiskFlag::MissingInformation => __('Some information is missing, so part of the result is incomplete.'),
            RiskFlag::AssumedRepayment => __('We assumed a repayment plan for :loan because its EMI or remaining months were not given.', ['loan' => $loan]),
            RiskFlag::PaymentBelowInterest => __('Your current payment on :loan does not cover its interest, so that debt is growing.', ['loan' => $loan]),
            RiskFlag::InconsistentLoanDetails => __('The EMI and months entered for :loan do not repay its outstanding. Please check them.', ['loan' => $loan]),
            RiskFlag::RateMismatch => __('The EMI on :loan implies about :implied% a year, not :stated% — the stated rate may be a flat rate.', ['loan' => $loan, 'implied' => $context['implied_rate'] ?? '', 'stated' => $context['stated_rate'] ?? '']),
            RiskFlag::HighFoir => __(':foir% of your income would go to loan repayments, which lenders may consider high.', ['foir' => $context['foir'] ?? '']),
            RiskFlag::LowDebtServiceCover => __('Your business cash flow would cover repayments only :dscr times, below what lenders usually expect.', ['dscr' => $context['dscr'] ?? '']),
            RiskFlag::SecuringUnsecuredDebt => __('This route secures unsecured debts (like credit cards) against your asset. The asset is at risk if repayments are missed.'),
            RiskFlag::LoanShortfall => __('The loan amount falls :amount short of the closure amount; you would need to pay this yourself.', ['amount' => self::inr($context['amount'] ?? 0)]),
            RiskFlag::ExtraBorrowing => __('The loan is :amount more than needed to close your debts — that is new borrowing.', ['amount' => self::inr($context['amount'] ?? 0)]),
            RiskFlag::TaxBenefitLoss => __('Closing a home or education loan can end the tax deduction on its interest. Check with your tax adviser.'),
            RiskFlag::ProposedRateNotLower => __('The proposed interest rate is not lower than what you pay today on average.'),
            null => (string) $flag['flag'],
        };
    }

    /**
     * Liability key → "Lender — type" for flag sentences.
     *
     * @param  iterable<array{key: string, lender_name?: ?string, type?: ?string}>  $liabilities
     * @return array<string, string>
     */
    public static function liabilityNames(iterable $liabilities): array
    {
        $names = [];

        foreach ($liabilities as $liability) {
            $type = LiabilityType::tryFrom((string) ($liability['type'] ?? ''))?->getLabel() ?? __('loan');
            $lender = trim((string) ($liability['lender_name'] ?? ''));
            $names[$liability['key']] = $lender === '' ? $type : $lender.' — '.mb_strtolower($type);
        }

        return $names;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function blockerReason(array $context): string
    {
        if (($context['reason'] ?? null) === 'days_past_due') {
            return __(':days days overdue', ['days' => $context['days'] ?? '']);
        }

        return LiabilityAccountStatus::tryFrom((string) ($context['reason'] ?? ''))?->getLabel() ?? __('its status');
    }
}
