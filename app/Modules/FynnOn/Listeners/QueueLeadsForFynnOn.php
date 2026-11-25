<?php

namespace App\Modules\FynnOn\Listeners;

use App\Enums\EnquiryStatus;
use App\Models\ContactEnquiry;
use App\Modules\Applications\Models\Application;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\FynnOn\Services\FynnOnSync;
use App\Modules\Journey\Models\JourneySession;
use App\Modules\MobileOtp\Enums\MobileOtpStatus;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;

/**
 * The moments a website record becomes (or becomes again) a lead for FynnOn
 * LMS, hooked onto each model's own Eloquent events — so every form, current
 * or future, that writes through the model is covered without touching its
 * controller. Each hook only dispatches a queued job (after commit); nothing
 * here can slow down or fail a visitor's submission. With the LMS not
 * configured, FynnOnSync::queue() returns before dispatching anything.
 */
final class QueueLeadsForFynnOn
{
    public static function register(): void
    {
        ContactEnquiry::created(fn (ContactEnquiry $enquiry): bool => FynnOnSync::queue($enquiry));

        // RecordEnquiry reopens a settled enquiry when the same number asks again.
        ContactEnquiry::updated(function (ContactEnquiry $enquiry): void {
            $previous = EnquiryStatus::tryFrom((string) $enquiry->getRawOriginal('status'));

            if ($enquiry->wasChanged('status') && $enquiry->status === EnquiryStatus::New && $previous?->isSettled()) {
                FynnOnSync::queue($enquiry, FynnOnSync::EVENT_REOPENED);
            }
        });

        CreditScoreCheck::created(fn (CreditScoreCheck $check): bool => FynnOnSync::queue($check));

        JourneySession::saved(function (JourneySession $session): void {
            if ($session->phone_verified_at !== null && ($session->wasRecentlyCreated || $session->wasChanged('phone_verified_at'))) {
                FynnOnSync::queue($session);
            }
        });

        Application::saved(function (Application $application): void {
            if ($application->submitted_at !== null && ($application->wasRecentlyCreated || $application->wasChanged('submitted_at'))) {
                FynnOnSync::queue($application);
            }
        });

        MobileOtpChallenge::updated(function (MobileOtpChallenge $challenge): void {
            if ($challenge->wasChanged('status') && $challenge->status === MobileOtpStatus::Verified) {
                FynnOnSync::queue($challenge);
            }
        });
    }
}
