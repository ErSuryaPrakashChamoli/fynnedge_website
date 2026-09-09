<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The public pages an admin can pin an FAQ to, keyed by Laravel route name.
 *
 * Route names (not URLs or slugs) are the identifier because they're already
 * the stable handle for a page in this app and survive any path change. A
 * parameterised route covers every URL it serves — `LoanProductPages` shows on
 * all 12 loan products, not one — which is the point: per-product FAQs already
 * have their own tab on LoanProductResource, so this layer is for the shared
 * questions that belong on a whole class of page.
 *
 * Deliberately excluded: the journey/application/credit-score funnel. Those are
 * per-visitor transactional screens that already send `noindex, nofollow`, not
 * marketing pages an FAQ belongs on.
 */
enum FaqPlacement: string implements HasLabel
{
    case Home = 'home';
    case About = 'about';
    case Careers = 'careers';
    case Contact = 'contact';
    case FaqsPage = 'faqs.index';
    case Eligibility = 'eligibility.index';

    case LoansDirectory = 'loans.index';
    case LoanProductPages = 'loans.show';
    case LoanLandingPages = 'loans.landing-pages.show';

    case ResourcesIndex = 'resources.index';
    case ArticlePages = 'resources.show';

    case CalculatorsDirectory = 'calculators.index';
    case EmiCalculators = 'calculators.emi';
    case EligibilityCalculators = 'calculators.eligibility';
    case PrepaymentCalculators = 'calculators.prepayment';
    case FixedDepositCalculator = 'calculators.fixed-deposit';
    case SipCalculator = 'calculators.sip';
    case DailySipCalculator = 'calculators.daily-sip';
    case GstCalculator = 'calculators.gst';

    case Grievance = 'grievance';
    case PrivacyPolicy = 'privacy-policy';
    case Terms = 'terms';
    case Disclaimer = 'disclaimer';
    case CreditReportTerms = 'credit-report-terms';

    public function getLabel(): string
    {
        return match ($this) {
            self::Home => 'Homepage',
            self::About => 'About page',
            self::Careers => 'Careers page',
            self::Contact => 'Contact page',
            self::FaqsPage => 'FAQs page',
            self::Eligibility => 'Check eligibility page',

            self::LoansDirectory => 'Loans directory (/loans)',
            self::LoanProductPages => 'Every loan product page',
            self::LoanLandingPages => 'Every loan landing page',

            self::ResourcesIndex => 'Resources index',
            self::ArticlePages => 'Every article page',

            self::CalculatorsDirectory => 'Calculators directory',
            self::EmiCalculators => 'Every EMI calculator page',
            self::EligibilityCalculators => 'Every eligibility calculator page',
            self::PrepaymentCalculators => 'Every prepayment calculator page',
            self::FixedDepositCalculator => 'Fixed Deposit calculator',
            self::SipCalculator => 'SIP calculator',
            self::DailySipCalculator => 'Daily SIP calculator',
            self::GstCalculator => 'GST calculator',

            self::Grievance => 'Grievance redressal',
            self::PrivacyPolicy => 'Privacy policy',
            self::Terms => 'Terms & conditions',
            self::Disclaimer => 'Disclaimer',
            self::CreditReportTerms => 'Credit report terms',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::Home, self::About, self::Careers, self::Contact, self::FaqsPage, self::Eligibility => 'Main pages',
            self::LoansDirectory, self::LoanProductPages, self::LoanLandingPages => 'Loan pages',
            self::ResourcesIndex, self::ArticlePages => 'Resources',
            self::CalculatorsDirectory, self::EmiCalculators, self::EligibilityCalculators,
            self::PrepaymentCalculators, self::FixedDepositCalculator, self::SipCalculator,
            self::DailySipCalculator, self::GstCalculator => 'Calculators',
            self::Grievance, self::PrivacyPolicy, self::Terms, self::Disclaimer, self::CreditReportTerms => 'Legal & policy',
        };
    }

    /**
     * Options grouped for a Filament Select, so 24 pages stay browsable.
     *
     * @return array<string, array<string, string>>
     */
    public static function groupedOptions(): array
    {
        return collect(self::cases())
            ->groupBy(fn (self $placement): string => $placement->group())
            ->map(fn ($placements) => $placements->mapWithKeys(
                fn (self $placement): array => [$placement->value => $placement->getLabel()],
            )->all())
            ->all();
    }
}
