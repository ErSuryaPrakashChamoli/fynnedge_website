<?php

namespace App\Modules\Enquiries\Actions;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Models\ContactEnquiry;
use App\Modules\Enquiries\Enums\QuickEnquiryOutcome;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Turns a bare mobile number into a lead, without ever producing a second row
 * for a person already in the table.
 *
 * The number reaching here is already normalised and validated — this action
 * decides what happens to it, not whether it is well-formed.
 *
 * OTP hook: `$phoneVerified` is the seam for a future verify-then-submit flow.
 * Pass true once a challenge has been verified and the lead is stamped as
 * verified; nothing else in the flow has to change.
 */
class SubmitQuickEnquiry
{
    /**
     * Per-number ceiling, on top of the per-IP route throttle. A number that is
     * already being hammered is answered as a duplicate rather than an error:
     * the honest response to "we already have your enquiry" is the same either
     * way, and it tells a scripted submitter nothing.
     */
    private const MAX_SUBMISSIONS_PER_HOUR = 3;

    public function handle(
        string $phone,
        string $source = 'website',
        ?string $sourceUrl = null,
        bool $phoneVerified = false,
    ): QuickEnquiryOutcome {
        if (RateLimiter::tooManyAttempts("quick-enquiry:{$phone}", self::MAX_SUBMISSIONS_PER_HOUR)) {
            return QuickEnquiryOutcome::Duplicate;
        }

        RateLimiter::hit("quick-enquiry:{$phone}", 3600);

        /*
         * A double-click (or a retried request) fires two identical submissions
         * milliseconds apart — both would find no existing row and both would
         * insert one. The lock serialises them so the second sees the first's
         * record; failing to take it means a submission for this number is in
         * flight right now, which is itself a duplicate.
         */
        try {
            return Cache::lock("quick-enquiry-lock:{$phone}", 10)->block(5, function () use ($phone, $source, $sourceUrl, $phoneVerified): QuickEnquiryOutcome {
                return $this->record($phone, $source, $sourceUrl, $phoneVerified);
            });
        } catch (LockTimeoutException) {
            return QuickEnquiryOutcome::Duplicate;
        }
    }

    private function record(string $phone, string $source, ?string $sourceUrl, bool $phoneVerified): QuickEnquiryOutcome
    {
        $existing = ContactEnquiry::query()->forPhone($phone)->first();

        if (! $existing) {
            ContactEnquiry::query()->create([
                'phone' => $phone,
                'enquiry_type' => EnquiryType::QuickEnquiry,
                'source' => $source,
                'status' => EnquiryStatus::New,
                'source_url' => $sourceUrl,
                'phone_verified_at' => $phoneVerified ? now() : null,
            ]);

            return QuickEnquiryOutcome::Created;
        }

        /*
         * `enquiry_type` is never rewritten on an existing row. If this number
         * first arrived through the full contact form, that row carries a name,
         * an email and a written message — far more use to whoever picks it up
         * than relabelling it as a quick enquiry would be. The repeat contact
         * shows as a bumped enquiry_count and a refreshed updated_at.
         */
        $existing->forceFill([
            'enquiry_count' => $existing->enquiry_count + 1,
            'source_url' => $sourceUrl ?? $existing->source_url,
            'phone_verified_at' => $phoneVerified ? now() : $existing->phone_verified_at,
        ]);

        if ($existing->isOpen()) {
            $existing->save();

            return QuickEnquiryOutcome::Duplicate;
        }

        $existing->forceFill([
            'status' => EnquiryStatus::New,
            'handled_at' => null,
        ])->save();

        return QuickEnquiryOutcome::Reopened;
    }
}
