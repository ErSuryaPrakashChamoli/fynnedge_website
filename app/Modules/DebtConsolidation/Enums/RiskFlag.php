<?php

namespace App\Modules\DebtConsolidation\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Conditions the engine raises alongside its figures. Critical flags always
 * send a case to an advisor; none of them is ever hidden by a good score.
 */
enum RiskFlag: string implements HasColor, HasLabel
{
    case NegativeSavings = 'negative_savings';
    case LowerEmiHigherCost = 'lower_emi_higher_cost';
    case TenureExtended = 'tenure_extended';
    case ForeclosureUnverified = 'foreclosure_unverified';
    case QuotationExpired = 'quotation_expired';
    case OverdueLiabilities = 'overdue_liabilities';
    case NotConsolidatable = 'not_consolidatable';
    case ForeclosureLockIn = 'foreclosure_lock_in';
    case NoEligibleRoute = 'no_eligible_route';
    case MissingInformation = 'missing_information';
    case AssumedRepayment = 'assumed_repayment';
    case PaymentBelowInterest = 'payment_below_interest';
    case InconsistentLoanDetails = 'inconsistent_loan_details';
    case RateMismatch = 'rate_mismatch';
    case HighFoir = 'high_foir';
    case LowDebtServiceCover = 'low_debt_service_cover';
    case SecuringUnsecuredDebt = 'securing_unsecured_debt';
    case LoanShortfall = 'loan_shortfall';
    case ExtraBorrowing = 'extra_borrowing';
    case TaxBenefitLoss = 'tax_benefit_loss';
    case ProposedRateNotLower = 'proposed_rate_not_lower';

    public function getLabel(): string
    {
        return match ($this) {
            self::NegativeSavings => __('Consolidating would cost more in total'),
            self::LowerEmiHigherCost => __('Lower EMI but higher total cost'),
            self::TenureExtended => __('Repayment period becomes longer'),
            self::ForeclosureUnverified => __('Foreclosure amounts are estimates'),
            self::QuotationExpired => __('Foreclosure quotation has expired'),
            self::OverdueLiabilities => __('Some loans are overdue'),
            self::NotConsolidatable => __('Some loans cannot be consolidated now'),
            self::ForeclosureLockIn => __('Foreclosure lock-in applies'),
            self::NoEligibleRoute => __('No financing route accepts this loan type'),
            self::MissingInformation => __('Information missing'),
            self::AssumedRepayment => __('Repayment plan assumed'),
            self::PaymentBelowInterest => __('Current payment does not reduce the debt'),
            self::InconsistentLoanDetails => __('Loan details do not add up'),
            self::RateMismatch => __('Interest rate differs from the EMI'),
            self::HighFoir => __('High share of income goes to EMIs'),
            self::LowDebtServiceCover => __('Cash flow does not comfortably cover repayments'),
            self::SecuringUnsecuredDebt => __('Unsecured debt would become secured'),
            self::LoanShortfall => __('New loan does not cover the closure amount'),
            self::ExtraBorrowing => __('New loan is larger than needed'),
            self::TaxBenefitLoss => __('Possible loss of tax benefit'),
            self::ProposedRateNotLower => __('Proposed rate is not lower'),
        };
    }

    public function severity(): string
    {
        return match ($this) {
            self::NegativeSavings, self::LowerEmiHigherCost, self::NotConsolidatable, self::NoEligibleRoute,
            self::PaymentBelowInterest, self::LoanShortfall => 'critical',
            self::AssumedRepayment, self::ForeclosureUnverified, self::RateMismatch, self::TaxBenefitLoss => 'info',
            default => 'warning',
        };
    }

    public function getColor(): string
    {
        return match ($this->severity()) {
            'critical' => 'danger',
            'warning' => 'warning',
            default => 'gray',
        };
    }

    /**
     * Flags that always put the case in front of an advisor before anything
     * is shown to a lender.
     */
    public function requiresAdvisorReview(): bool
    {
        return $this->severity() === 'critical'
            || in_array($this, [self::OverdueLiabilities, self::SecuringUnsecuredDebt, self::QuotationExpired, self::ForeclosureLockIn, self::InconsistentLoanDetails], true);
    }
}
