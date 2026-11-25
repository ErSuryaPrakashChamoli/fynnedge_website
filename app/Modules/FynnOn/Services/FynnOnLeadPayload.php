<?php

namespace App\Modules\FynnOn\Services;

use App\Models\ContactEnquiry;
use App\Modules\Applications\Models\Application;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\Journey\Models\JourneySession;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\Models\LeadInboxEntry;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The JSON body FynnOn LMS receives for one lead (contract: docs in the
 * FynnOn Sync screen and the implementation report).
 *
 * `reference` is stable per source record ("fynnedge:enquiry:{public_id}") and
 * is also sent as the Idempotency-Key header, so the LMS can update rather than
 * duplicate a lead that is resent or reopened.
 *
 * Deliberately never included: PAN, date of birth, credit score, bureau report
 * contents, OTP codes/hashes, IP addresses. The LMS gets who to call, about
 * what, and where they came from — nothing it would need a bureau licence or
 * extra consent to hold.
 */
final class FynnOnLeadPayload
{
    public const VERSION = '1';

    public static function reference(LeadSource $source, Model $lead): string
    {
        return 'fynnedge:'.$source->value.':'.($lead->getAttribute('public_id') ?? $lead->getKey());
    }

    /**
     * @return array<string, mixed>
     */
    public static function for(LeadSource $source, Model $lead, string $event = 'lead.created'): array
    {
        return [
            'schema_version' => self::VERSION,
            'event' => $event,
            'reference' => self::reference($source, $lead),
            'source' => $source->value,
            'source_label' => $source->getLabel(),
            'submitted_at' => self::time($lead->getAttribute('created_at')),
            'sent_at' => now()->toIso8601String(),
            ...match ($source) {
                LeadSource::Enquiry => self::enquiry($lead),
                LeadSource::CreditScoreCheck => self::creditScoreCheck($lead),
                LeadSource::OnlineApplication => self::onlineApplication($lead),
                LeadSource::LoanApplication => self::loanApplication($lead),
                LeadSource::OtpVerification => self::otpVerification($lead),
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function enquiry(ContactEnquiry $enquiry): array
    {
        return [
            'contact' => self::contact($enquiry->name, $enquiry->phone, $enquiry->email, $enquiry->phone_verified_at),
            'loan' => [
                'product' => $enquiry->loanProduct?->name,
                'product_slug' => $enquiry->loanProduct?->slug,
                'amount' => $enquiry->loan_amount !== null ? (float) $enquiry->loan_amount : null,
            ],
            'form' => [
                'type' => self::value($enquiry->enquiry_type),
                'placement' => $enquiry->enquiry_source,
                'channel' => $enquiry->source,
                'message' => $enquiry->message,
            ],
            'attribution' => self::attribution($enquiry->source_url),
            'status' => self::value($enquiry->status),
            'repeat_submissions' => (int) $enquiry->enquiry_count,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function creditScoreCheck(CreditScoreCheck $check): array
    {
        return [
            'contact' => self::contact($check->full_name, $check->mobile_number, null, $check->mobile_verified_at),
            'loan' => ['product' => null, 'product_slug' => null, 'amount' => null],
            'form' => ['type' => 'credit_score_check', 'placement' => $check->bureau?->getLabel().' credit score check', 'channel' => 'website', 'message' => null],
            // credit_score_checks records no page, so none is claimed.
            'attribution' => self::attribution(null),
            'status' => self::value($check->status),
            'bureau' => self::value($check->bureau),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function onlineApplication(JourneySession $session): array
    {
        return [
            'contact' => self::contact(null, $session->phone_number, null, $session->phone_verified_at),
            'loan' => [
                'product' => $session->loanProduct?->name,
                'product_slug' => $session->loanProduct?->slug,
                'amount' => null,
            ],
            'form' => ['type' => 'online_application', 'placement' => 'Online application', 'channel' => 'website', 'message' => null],
            'attribution' => self::attribution($session->landing_page, $session->referrer, [
                'utm_source' => $session->utm_source,
                'utm_medium' => $session->utm_medium,
                'utm_campaign' => $session->utm_campaign,
                'utm_term' => $session->utm_term,
                'utm_content' => $session->utm_content,
            ]),
            'status' => self::value($session->status),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function loanApplication(Application $application): array
    {
        $session = $application->journeySession;
        $customer = $application->customer;
        $loanProduct = $session?->loanProduct ?? $application->lenderProduct?->loanProduct;

        return [
            'contact' => self::contact($customer?->full_name, $session?->phone_number ?? $customer?->phone, $customer?->email, $session?->phone_verified_at),
            'loan' => [
                'product' => $loanProduct?->name,
                'product_slug' => $loanProduct?->slug,
                'amount' => null,
                'lender' => $application->lenderProduct?->lender?->name,
            ],
            'form' => ['type' => 'loan_application', 'placement' => 'Loan application', 'channel' => 'website', 'message' => null],
            'attribution' => self::attribution($session?->landing_page, $session?->referrer, [
                'utm_source' => $session?->utm_source,
                'utm_medium' => $session?->utm_medium,
                'utm_campaign' => $session?->utm_campaign,
                'utm_term' => $session?->utm_term,
                'utm_content' => $session?->utm_content,
            ]),
            'status' => self::value($application->status),
            'submitted_at_application' => self::time($application->submitted_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function otpVerification(MobileOtpChallenge $challenge): array
    {
        return [
            'contact' => self::contact(null, $challenge->mobile_number, null, $challenge->verified_at),
            'loan' => ['product' => null, 'product_slug' => null, 'amount' => null],
            'form' => ['type' => 'otp_verification', 'placement' => $challenge->purpose?->getLabel(), 'channel' => 'website', 'message' => null],
            'attribution' => self::attribution(null),
            'status' => self::value($challenge->status),
            'otp_purpose' => self::value($challenge->purpose),
        ];
    }

    /**
     * @return array{name: ?string, mobile: ?string, email: ?string, mobile_verified: bool, mobile_verified_at: ?string}
     */
    private static function contact(?string $name, ?string $mobile, ?string $email, mixed $verifiedAt): array
    {
        return [
            'name' => $name,
            'mobile' => $mobile,
            'email' => $email,
            'mobile_verified' => $verifiedAt !== null,
            'mobile_verified_at' => self::time($verifiedAt),
        ];
    }

    /**
     * @param  array<string, ?string>  $utm
     * @return array<string, ?string>
     */
    private static function attribution(?string $pageUrl, ?string $referrer = null, array $utm = []): array
    {
        return [
            'page_url' => $pageUrl,
            'page_path' => LeadInboxEntry::pathOf($pageUrl),
            'referrer' => $referrer,
            'utm_source' => $utm['utm_source'] ?? null,
            'utm_medium' => $utm['utm_medium'] ?? null,
            'utm_campaign' => $utm['utm_campaign'] ?? null,
            'utm_term' => $utm['utm_term'] ?? null,
            'utm_content' => $utm['utm_content'] ?? null,
        ];
    }

    private static function value(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    private static function time(mixed $value): ?string
    {
        return $value instanceof Carbon ? $value->toIso8601String() : null;
    }
}
