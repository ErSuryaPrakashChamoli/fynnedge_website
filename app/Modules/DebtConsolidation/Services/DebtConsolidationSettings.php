<?php

namespace App\Modules\DebtConsolidation\Services;

use App\Models\Setting;
use App\Modules\DebtConsolidation\DataTransferObjects\Assumptions;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Enums\ConsentPurpose;
use App\Modules\DebtConsolidation\Enums\FinancingRoute;
use App\Support\Localization\Phrasebook;
use App\Support\Localization\SiteLocale;
use Illuminate\Validation\ValidationException;

/**
 * Website-level Debt Consolidation configuration, stored under one key in the
 * same Setting store the rest of the site uses and edited in Website Settings
 * → Debt Consolidation Settings. Every value has a default here, so an
 * install that never opens the screen behaves predictably.
 *
 * The service starts SWITCHED OFF: nothing is public until an admin switches
 * it on and publishes the pages (a controlled launch).
 */
class DebtConsolidationSettings
{
    public const KEY = 'debt_consolidation';

    public const CALCULATORS = ['consolidation', 'foreclosure', 'comparison', 'affordability', 'break_even', 'portfolio'];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $config = config('debt_consolidation.defaults');

        return [
            'enabled' => false,
            'individual_enabled' => true,
            'business_enabled' => true,
            'calculators' => self::CALCULATORS,
            'gst_rate_percent' => $config['gst_rate_percent'],
            'revolving_minimum_payment_percent' => $config['revolving_minimum_payment_percent'],
            'default_tenure_months' => $config['default_tenure_months'],
            'rate_mismatch_tolerance_points' => $config['rate_mismatch_tolerance_points'],
            'quotation_validity_days' => 15,
            'standard_scenarios' => [
                ['route' => FinancingRoute::PersonalLoan->value, 'label' => 'Personal loan', 'annual_interest_rate' => 14.0, 'tenure_months' => 60, 'processing_fee_percent' => 2.0],
                ['route' => FinancingRoute::LoanAgainstProperty->value, 'label' => 'Loan against property', 'annual_interest_rate' => 10.5, 'tenure_months' => 120, 'processing_fee_percent' => 1.0],
                ['route' => FinancingRoute::UnsecuredBusinessLoan->value, 'label' => 'Unsecured business loan', 'annual_interest_rate' => 17.0, 'tenure_months' => 48, 'processing_fee_percent' => 2.0],
                ['route' => FinancingRoute::SecuredBusinessLoan->value, 'label' => 'Secured business loan', 'annual_interest_rate' => 11.5, 'tenure_months' => 84, 'processing_fee_percent' => 1.0],
            ],
            'staff_notification_email' => null,
            'customer_email_notifications' => true,
            'general_disclaimer' => 'FynnEdge Advisory (OPC) Pvt Ltd is a loan advisory and facilitation company, not a bank or NBFC. We do not sanction, approve or disburse loans, set interest rates or issue NOCs. Every credit decision, sanction, disbursement and closure confirmation is made by the lender concerned.',
            'calculator_disclaimer' => 'These figures are estimates based on the details you enter and the assumptions shown. Actual rates, fees, foreclosure amounts and eligibility are decided by lenders and may differ. A lower EMI does not by itself mean you save money — compare the total cost.',
            'suitability_disclaimer' => 'The suitability score shows how financially sensible consolidation looks on the figures provided. It is not a loan approval, an offer or a guarantee of eligibility.',
            'consent_version' => '1',
            'consent_assessment' => 'I authorise FynnEdge Advisory (OPC) Pvt Ltd to store and assess the information I have provided about my income and existing loans in order to advise me on debt consolidation. I confirm the information is true to the best of my knowledge.',
            'consent_contact' => 'I agree that FynnEdge may contact me by call, SMS, WhatsApp or email about this request, even if my number is registered on the DND/NCPR list.',
            'consent_lender_sharing' => 'I authorise FynnEdge Advisory (OPC) Pvt Ltd to share my application details and documents with :lender for the purpose of applying for a loan to consolidate my existing debts. I understand that :lender alone decides whether to approve the loan.',
            'consent_external_analysis' => 'I agree that FynnEdge may let its analysis service read my validated case data to explain my assessment. It cannot change my figures or decide on any loan.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        $saved = Setting::get(self::KEY, []);

        return array_replace(self::defaults(), is_array($saved) ? array_filter($saved, fn (mixed $value): bool => $value !== null && $value !== '') : []);
    }

    public static function get(string $key): mixed
    {
        return self::all()[$key] ?? null;
    }

    public static function enabled(): bool
    {
        return (bool) self::get('enabled');
    }

    public static function acceptsApplicant(ApplicantType $applicantType): bool
    {
        return self::enabled() && (bool) self::get($applicantType === ApplicantType::Individual ? 'individual_enabled' : 'business_enabled');
    }

    /**
     * Refuses a NEW customer request (enquiry, case, submission) while the
     * service or this applicant type is switched off. Existing cases stay
     * served (portal, API, staff), and staff may still open cases.
     *
     * @throws ValidationException
     */
    public static function ensureAccepting(ApplicantType $applicantType): void
    {
        if (! self::acceptsApplicant($applicantType)) {
            throw ValidationException::withMessages(['application' => self::pausedMessage()]);
        }
    }

    public static function pausedMessage(): string
    {
        return __('We are not accepting new debt consolidation requests right now. Please call us, or try again later.');
    }

    /**
     * @return list<ApplicantType>
     */
    public static function availableApplicantTypes(): array
    {
        return array_values(array_filter(ApplicantType::cases(), fn (ApplicantType $type): bool => self::acceptsApplicant($type)));
    }

    /**
     * @return list<string>
     */
    public static function calculators(): array
    {
        return array_values(array_intersect(self::CALCULATORS, (array) self::get('calculators')));
    }

    /**
     * The engine assumptions in force today (India time).
     */
    public static function assumptions(?string $asOf = null): Assumptions
    {
        $config = config('debt_consolidation.defaults');

        return new Assumptions(
            asOf: $asOf ?? now('Asia/Kolkata')->toDateString(),
            gstRatePercent: (float) self::get('gst_rate_percent'),
            revolvingMinimumPaymentPercent: (float) self::get('revolving_minimum_payment_percent'),
            maxProjectionMonths: (int) $config['max_projection_months'],
            defaultTenureMonths: (int) self::get('default_tenure_months'),
            rateMismatchTolerancePoints: (float) self::get('rate_mismatch_tolerance_points'),
            nonPerformingDaysPastDue: (int) $config['non_performing_days_past_due'],
        );
    }

    /**
     * Standard comparison scenarios for an applicant type: the routes that
     * serve it, with the indicative terms configured in settings.
     *
     * @return list<array{route: FinancingRoute, label: string, annual_interest_rate: float, tenure_months: int, processing_fee_percent: float}>
     */
    public static function standardScenarios(ApplicantType $applicantType): array
    {
        $scenarios = [];

        foreach ((array) self::get('standard_scenarios') as $row) {
            $route = FinancingRoute::tryFrom((string) ($row['route'] ?? ''));

            if ($route === null || ! in_array($applicantType, $route->applicantTypes(), true)) {
                continue;
            }

            $scenarios[] = [
                'route' => $route,
                'label' => (string) ($row['label'] ?? $route->getLabel()),
                'annual_interest_rate' => (float) $row['annual_interest_rate'],
                'tenure_months' => (int) $row['tenure_months'],
                'processing_fee_percent' => (float) ($row['processing_fee_percent'] ?? 0),
            ];
        }

        return $scenarios;
    }

    /**
     * Visitor-facing text in the visitor's language: the admin's Hindi when
     * saved, else the phrasebook / lang/hi.json lookup of the English.
     */
    public static function text(string $key): string
    {
        $english = (string) self::get($key);

        if (SiteLocale::isTranslating()) {
            $hindi = Setting::get(self::KEY.'_hi', []);

            if (is_array($hindi) && filled($hindi[$key] ?? null)) {
                return (string) $hindi[$key];
            }

            return (string) Phrasebook::translate($english);
        }

        return $english;
    }

    /**
     * The exact consent wording for a purpose, in English: what is stored
     * with the consent record (the customer may have seen the Hindi version,
     * whose meaning is the same).
     */
    public static function consentText(ConsentPurpose $purpose, ?string $lenderName = null): string
    {
        $text = (string) self::get('consent_'.$purpose->value);

        return $lenderName === null ? $text : str_replace(':lender', $lenderName, $text);
    }

    public static function consentVersion(): string
    {
        return (string) self::get('consent_version');
    }
}
