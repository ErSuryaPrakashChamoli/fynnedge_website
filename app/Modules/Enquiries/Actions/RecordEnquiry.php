<?php

namespace App\Modules\Enquiries\Actions;

use App\Enums\EnquiryStatus;
use App\Models\ContactEnquiry;
use App\Modules\Enquiries\DataTransferObjects\EnquiryDraft;
use App\Modules\Enquiries\Enums\EnquiryOutcome;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The single write path into the enquiry table, shared by every public form —
 * the Quick Enquiry box, the loan-page enquiry form, and anything added later.
 * One place decides what counts as a duplicate, so the answer cannot drift
 * between forms.
 *
 * The number reaching here is already normalised and validated; this action
 * decides what happens to it, not whether it is well-formed.
 *
 * OTP hook: `EnquiryDraft::$phoneVerified` is the seam for a future
 * verify-then-submit flow. Set it once a challenge has been verified and the
 * lead is stamped as verified; nothing else in the flow has to change.
 */
class RecordEnquiry
{
    /**
     * Per number, per product, on top of the per-IP route throttle. A number
     * already being hammered about one product is answered as a duplicate
     * rather than an error: the honest response to "we already have your
     * enquiry" is the same either way, and it tells a scripted submitter
     * nothing. Scoping it to the product means a genuine customer can still
     * enquire about a second loan immediately.
     */
    private const MAX_SUBMISSIONS_PER_HOUR = 3;

    public function handle(EnquiryDraft $draft): EnquiryOutcome
    {
        $key = $this->identityKey($draft);

        if (RateLimiter::tooManyAttempts("enquiry:{$key}", self::MAX_SUBMISSIONS_PER_HOUR)) {
            return EnquiryOutcome::Duplicate;
        }

        RateLimiter::hit("enquiry:{$key}", 3600);

        /*
         * A double-click (or a retried request) fires two identical submissions
         * milliseconds apart — both would find no existing row and both would
         * insert one. The lock serialises them so the second sees the first's
         * record; failing to take it means a submission for this number is in
         * flight right now, which is itself a duplicate.
         */
        try {
            return Cache::lock("enquiry-lock:{$key}", 10)->block(5, fn (): EnquiryOutcome => $this->record($draft));
        } catch (LockTimeoutException) {
            return EnquiryOutcome::Duplicate;
        }
    }

    private function record(EnquiryDraft $draft): EnquiryOutcome
    {
        $existing = $this->existingFor($draft);

        if (! $existing) {
            ContactEnquiry::query()->create([
                'phone' => $draft->phone,
                'name' => $draft->name,
                'email' => $draft->email,
                'enquiry_type' => $draft->type,
                'loan_product_id' => $draft->loanProductId,
                'loan_amount' => $draft->loanAmount,
                'source' => $draft->source,
                'enquiry_source' => $draft->enquirySource,
                'source_url' => $draft->landingPage,
                'status' => EnquiryStatus::New,
                'phone_verified_at' => $draft->phoneVerified ? now() : null,
            ]);

            return EnquiryOutcome::Created;
        }

        /*
         * `enquiry_type` and `enquiry_source` are never rewritten on an existing
         * row. If this number first arrived through the full contact form, that
         * row carries a written message and the page it came from — far more use
         * to whoever picks it up than relabelling it would be. The repeat contact
         * shows as a bumped enquiry_count and a refreshed updated_at.
         *
         * Details are filled in but never overwritten: a second submission that
         * finally gives us a name is worth keeping, one that arrives with a
         * blank name must not erase the name we already had.
         */
        $existing->forceFill([
            'enquiry_count' => $existing->enquiry_count + 1,
            'name' => $existing->name ?: $draft->name,
            'email' => $existing->email ?: $draft->email,
            'loan_amount' => $existing->loan_amount ?? $draft->loanAmount,
            'source_url' => $draft->landingPage ?: $existing->source_url,
            'phone_verified_at' => $draft->phoneVerified ? now() : $existing->phone_verified_at,
        ]);

        if ($existing->isOpen()) {
            $existing->save();

            return EnquiryOutcome::Duplicate;
        }

        $existing->forceFill([
            'status' => EnquiryStatus::New,
            'handled_at' => null,
        ])->save();

        return EnquiryOutcome::Reopened;
    }

    /**
     * An enquiry about a specific loan product is matched only against earlier
     * enquiries about that same product. A general enquiry — the homepage Quick
     * Enquiry box, which names no product — is matched against anything we hold
     * for the number, since "call me back" is the same request whatever they
     * were reading at the time.
     */
    private function existingFor(EnquiryDraft $draft): ?ContactEnquiry
    {
        return ContactEnquiry::query()
            ->forPhone($draft->phone)
            ->when(
                $draft->loanProductId !== null,
                fn (Builder $query) => $query->where('loan_product_id', $draft->loanProductId),
            )
            ->first();
    }

    private function identityKey(EnquiryDraft $draft): string
    {
        [$phone, $loanProductId] = $draft->identity();

        return $phone.':'.($loanProductId ?? 'general');
    }
}
