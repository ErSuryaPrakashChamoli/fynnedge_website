<?php

namespace App\Modules\Leads\Enums;

use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\ContactEnquiries\ContactEnquiryResource;
use App\Filament\Resources\CreditScoreChecks\CreditScoreCheckResource;
use App\Filament\Resources\JourneySessions\JourneySessionResource;
use App\Filament\Resources\MobileOtpChallenges\MobileOtpChallengeResource;
use App\Models\ContactEnquiry;
use App\Modules\Applications\Models\Application;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\Journey\Models\JourneySession;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use Filament\Resources\Resource;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Model;

/**
 * Every kind of record a visitor creates on the website. Each keeps living in
 * its own table and admin screen; this enum is what lets the Lead Inbox, Lead
 * Analytics and the FynnOn LMS sync treat them as one stream of leads.
 *
 * Newsletter sign-ups are deliberately NOT a lead source: they were collected
 * as mailing-list consent, not as a request to be contacted about a loan (see
 * .ai/rules/http-controllers.md).
 */
enum LeadSource: string implements HasColor, HasLabel
{
    case Enquiry = 'enquiry';
    case CreditScoreCheck = 'credit_score_check';
    case OnlineApplication = 'online_application';
    case LoanApplication = 'loan_application';
    case OtpVerification = 'otp_verification';

    public function getLabel(): string
    {
        return match ($this) {
            self::Enquiry => 'Enquiry',
            self::CreditScoreCheck => 'Credit score check',
            self::OnlineApplication => 'Online application',
            self::LoanApplication => 'Loan application',
            self::OtpVerification => 'OTP verification',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Enquiry => 'primary',
            self::CreditScoreCheck => 'info',
            self::OnlineApplication => 'warning',
            self::LoanApplication => 'success',
            self::OtpVerification => 'gray',
        };
    }

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Enquiry => ContactEnquiry::class,
            self::CreditScoreCheck => CreditScoreCheck::class,
            self::OnlineApplication => JourneySession::class,
            self::LoanApplication => Application::class,
            self::OtpVerification => MobileOtpChallenge::class,
        };
    }

    /**
     * @return class-string<resource>
     */
    public function resourceClass(): string
    {
        return match ($this) {
            self::Enquiry => ContactEnquiryResource::class,
            self::CreditScoreCheck => CreditScoreCheckResource::class,
            self::OnlineApplication => JourneySessionResource::class,
            self::LoanApplication => ApplicationResource::class,
            self::OtpVerification => MobileOtpChallengeResource::class,
        };
    }

    /**
     * The record screen a row opens: the editable one where staff work the
     * lead, otherwise the read-only view.
     */
    public function recordPage(): string
    {
        return match ($this) {
            self::Enquiry, self::LoanApplication => 'edit',
            default => 'view',
        };
    }

    public function morphClass(): string
    {
        $model = $this->modelClass();

        return (new $model)->getMorphClass();
    }

    public static function fromModel(Model $model): ?self
    {
        foreach (self::cases() as $source) {
            if ($model instanceof ($source->modelClass())) {
                return $source;
            }
        }

        return null;
    }

    /**
     * Whether the signed-in user may see this source at all — the same check
     * as opening its own admin screen, so the unified views never widen access.
     */
    public function isAccessible(): bool
    {
        return $this->resourceClass()::canAccess();
    }

    /**
     * @return array<int, self>
     */
    public static function accessible(): array
    {
        return array_values(array_filter(self::cases(), fn (self $source): bool => $source->isAccessible()));
    }
}
