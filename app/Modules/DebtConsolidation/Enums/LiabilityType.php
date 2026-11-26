<?php

namespace App\Modules\DebtConsolidation\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Every kind of debt a customer can record. Recording a liability never means
 * it can be consolidated: which financing route may take it over is decided
 * by config('debt_consolidation.route_liability_types') and, per lender, by
 * the lender's debt consolidation policy.
 */
enum LiabilityType: string implements HasLabel
{
    // Personal
    case PersonalLoan = 'personal_loan';
    case CreditCardOutstanding = 'credit_card_outstanding';
    case CreditCardEmi = 'credit_card_emi';
    case ConsumerDurableLoan = 'consumer_durable_loan';
    case BuyNowPayLater = 'bnpl';
    case EducationLoan = 'education_loan';
    case VehicleLoan = 'vehicle_loan';
    case GoldLoan = 'gold_loan';
    case HomeLoan = 'home_loan';
    case LoanAgainstProperty = 'loan_against_property';
    case OtherPersonal = 'other_personal';

    // Business
    case UnsecuredBusinessLoan = 'unsecured_business_loan';
    case SecuredBusinessLoan = 'secured_business_loan';
    case MsmeLoan = 'msme_loan';
    case NbfcBusinessLoan = 'nbfc_business_loan';
    case BusinessTermLoan = 'business_term_loan';
    case MachineryLoan = 'machinery_loan';
    case CommercialVehicleLoan = 'commercial_vehicle_loan';
    case CashCredit = 'cash_credit';
    case Overdraft = 'overdraft';
    case InvoiceFinancing = 'invoice_financing';
    case MerchantFinancing = 'merchant_financing';
    case WorkingCapital = 'working_capital';
    case OtherBusiness = 'other_business';

    public function getLabel(): string
    {
        return match ($this) {
            self::PersonalLoan => __('Personal loan'),
            self::CreditCardOutstanding => __('Credit card outstanding'),
            self::CreditCardEmi => __('Credit card EMI'),
            self::ConsumerDurableLoan => __('Consumer durable loan'),
            self::BuyNowPayLater => __('Buy now, pay later (BNPL)'),
            self::EducationLoan => __('Education loan'),
            self::VehicleLoan => __('Vehicle loan'),
            self::GoldLoan => __('Gold loan'),
            self::HomeLoan => __('Home loan'),
            self::LoanAgainstProperty => __('Loan against property'),
            self::OtherPersonal => __('Other personal liability'),
            self::UnsecuredBusinessLoan => __('Unsecured business loan'),
            self::SecuredBusinessLoan => __('Secured business loan'),
            self::MsmeLoan => __('MSME loan'),
            self::NbfcBusinessLoan => __('NBFC business loan'),
            self::BusinessTermLoan => __('Business term loan'),
            self::MachineryLoan => __('Machinery loan'),
            self::CommercialVehicleLoan => __('Commercial vehicle loan'),
            self::CashCredit => __('Cash credit'),
            self::Overdraft => __('Overdraft'),
            self::InvoiceFinancing => __('Invoice financing'),
            self::MerchantFinancing => __('Merchant financing'),
            self::WorkingCapital => __('Working capital facility'),
            self::OtherBusiness => __('Other business liability'),
        };
    }

    /**
     * @return list<ApplicantType>
     */
    public function applicantTypes(): array
    {
        return match ($this) {
            self::LoanAgainstProperty => [ApplicantType::Individual, ApplicantType::Business],
            self::UnsecuredBusinessLoan, self::SecuredBusinessLoan, self::MsmeLoan, self::NbfcBusinessLoan,
            self::BusinessTermLoan, self::MachineryLoan, self::CommercialVehicleLoan, self::CashCredit,
            self::Overdraft, self::InvoiceFinancing, self::MerchantFinancing, self::WorkingCapital,
            self::OtherBusiness => [ApplicantType::Business],
            default => [ApplicantType::Individual],
        };
    }

    /**
     * @return list<self>
     */
    public static function forApplicant(ApplicantType $applicantType): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $type): bool => in_array($applicantType, $type->applicantTypes(), true),
        ));
    }

    public function isSecuredByDefault(): bool
    {
        return in_array($this, [
            self::VehicleLoan, self::GoldLoan, self::HomeLoan, self::LoanAgainstProperty,
            self::SecuredBusinessLoan, self::MachineryLoan, self::CommercialVehicleLoan, self::CashCredit,
        ], true);
    }

    public function defaultRepaymentStructure(): RepaymentStructure
    {
        return match ($this) {
            self::CreditCardOutstanding => RepaymentStructure::Revolving,
            self::GoldLoan, self::CashCredit, self::Overdraft, self::InvoiceFinancing, self::WorkingCapital => RepaymentStructure::InterestOnly,
            default => RepaymentStructure::Amortising,
        };
    }
}
