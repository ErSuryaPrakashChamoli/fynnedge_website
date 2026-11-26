<?php

namespace App\Modules\DebtConsolidation\Enums;

use App\Enums\LoanCategory;
use Filament\Support\Contracts\HasLabel;

/**
 * The kind of new financing that would take over existing debts. Each route
 * maps onto an existing loan product category, so lender offers, rates and
 * landing pages already on the site are reused rather than duplicated.
 */
enum FinancingRoute: string implements HasLabel
{
    case PersonalLoan = 'personal_loan';
    case UnsecuredBusinessLoan = 'unsecured_business_loan';
    case LoanAgainstProperty = 'loan_against_property';
    case SecuredBusinessLoan = 'secured_business_loan';
    case GoldLoan = 'gold_loan';
    case HomeLoanBalanceTransfer = 'home_loan_balance_transfer';
    case VehicleRefinance = 'vehicle_refinance';

    public function getLabel(): string
    {
        return match ($this) {
            self::PersonalLoan => __('Personal loan'),
            self::UnsecuredBusinessLoan => __('Unsecured business loan'),
            self::LoanAgainstProperty => __('Loan against property'),
            self::SecuredBusinessLoan => __('Secured business / term loan'),
            self::GoldLoan => __('Gold loan'),
            self::HomeLoanBalanceTransfer => __('Home loan balance transfer'),
            self::VehicleRefinance => __('Vehicle loan refinance'),
        };
    }

    public function isSecured(): bool
    {
        return ! in_array($this, [self::PersonalLoan, self::UnsecuredBusinessLoan], true);
    }

    /**
     * @return list<ApplicantType>
     */
    public function applicantTypes(): array
    {
        return match ($this) {
            self::PersonalLoan, self::HomeLoanBalanceTransfer => [ApplicantType::Individual],
            self::UnsecuredBusinessLoan, self::SecuredBusinessLoan => [ApplicantType::Business],
            default => [ApplicantType::Individual, ApplicantType::Business],
        };
    }

    /**
     * The existing loan product categories that serve this route.
     *
     * @return list<LoanCategory>
     */
    public function loanCategories(): array
    {
        return match ($this) {
            self::PersonalLoan => [LoanCategory::PersonalLoan],
            self::UnsecuredBusinessLoan => [LoanCategory::BusinessLoan, LoanCategory::MudraLoan],
            self::LoanAgainstProperty => [LoanCategory::LoanAgainstProperty],
            self::SecuredBusinessLoan => [LoanCategory::TermLoan, LoanCategory::BusinessLoan, LoanCategory::FlexiHybridTermLoan],
            self::GoldLoan => [LoanCategory::GoldLoan],
            self::HomeLoanBalanceTransfer => [LoanCategory::HomeLoan],
            self::VehicleRefinance => [LoanCategory::CarLoan, LoanCategory::TwoWheelerLoan],
        };
    }
}
