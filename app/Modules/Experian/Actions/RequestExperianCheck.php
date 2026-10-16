<?php

namespace App\Modules\Experian\Actions;

use App\Models\User;
use App\Modules\CreditBureau\Services\CreditConsentGate;
use App\Modules\CreditBureau\Services\PanNumber;
use App\Modules\Experian\DataTransferObjects\ExperianCheckRequest;
use App\Modules\Experian\DataTransferObjects\ExperianOutcome;
use App\Modules\Experian\Enums\ExperianProduct;
use App\Modules\Experian\Enums\ExperianRequestStatus;
use App\Modules\Experian\Enums\ExperianRequestType;
use App\Modules\Experian\Exceptions\ExperianRequestRefused;
use App\Modules\Experian\Jobs\FetchExperianRequest;
use App\Modules\Experian\Models\ExperianCreditRequest;
use App\Modules\Experian\Services\ExperianFingerprint;
use App\Modules\Experian\Services\ExperianSettings;
use App\Modules\MobileOtp\Services\MobileNumber;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The only way an Experian score, report or PDF request is started — from the
 * admin, the internal API or another module (ExperianCreditService). It decides
 * whether a PAID Surepass call happens at all, in this order:
 *
 *  1. Permission, integration on, PAN / name / mobile valid, and a consent that
 *     is Given, recent enough and belongs to this customer and PAN.
 *  2. An equivalent request for the PAN already in flight → BLOCKED (any product
 *     in flight also blocks a score request, since reports and PDFs carry the
 *     score; a report or PDF is blocked only by one of its own kind).
 *  3. A successful result inside the product's reuse period (unless this is an
 *     authorised Fresh request) → REUSED; no call is made.
 *  4. Surepass already rejected these exact details → refused, so an identical
 *     request is not paid for again.
 *  5. Otherwise a pending row is created and the paid call is queued.
 *
 * Simultaneous callers are serialised by a per-PAN cache lock, and the unique
 * index on experian_credit_requests.in_flight_key (PAN + product) is the final
 * guarantee: a second unresolved row for the same PAN and product is refused by
 * the database and answered as BLOCKED.
 */
class RequestExperianCheck
{
    private const LOCK_SECONDS = 30;

    private const LOCK_WAIT_SECONDS = 10;

    public function handle(ExperianCheckRequest $request, User $actor): ExperianOutcome
    {
        $this->authorize($request, $actor);

        if (! ExperianSettings::enabled()) {
            throw new ExperianRequestRefused('Experian requests are switched off. An administrator can enable them under Experian → Experian Settings.');
        }

        if (! ExperianSettings::isConfigured()) {
            throw new ExperianRequestRefused('The Surepass integration is not configured on this server. Ask an administrator to set the API token.');
        }

        $pan = PanNumber::normalize($request->pan);
        $name = trim(preg_replace('/\s+/', ' ', $request->name) ?? '');
        $mobile = MobileNumber::normalize($request->mobile);

        $this->validateDetails($pan, $name, $mobile);

        $refusal = CreditConsentGate::refusal($request->consent, $request->customer, $pan, ExperianSettings::consentValidityDays());

        if ($refusal !== null) {
            throw new ExperianRequestRefused($refusal);
        }

        $panHash = PanNumber::hash($pan);
        $fingerprint = ExperianFingerprint::request($pan, $name, $mobile);

        try {
            $outcome = Cache::lock('experian-request:'.$panHash, self::LOCK_SECONDS)->block(
                self::LOCK_WAIT_SECONDS,
                fn () => $this->decide($request, $actor, $pan, $name, $mobile, $panHash, $fingerprint),
            );
        } catch (LockTimeoutException) {
            throw new ExperianRequestRefused('Another Experian request for this PAN is being started right now. Wait a moment and open the customer\'s Experian requests.');
        }

        if ($outcome->kind === ExperianOutcome::QUEUED) {
            FetchExperianRequest::dispatch($outcome->request->id);
        }

        Log::info('Experian request answered', [
            'outcome' => $outcome->kind,
            'request' => $outcome->request->public_id,
            'product' => $request->product->value,
            'pan' => $outcome->request->pan_masked,
            'type' => $request->type->value,
            'channel' => $request->channel,
            'user_id' => $actor->id,
        ]);

        return $outcome;
    }

    private function decide(ExperianCheckRequest $request, User $actor, string $pan, string $name, string $mobile, string $panHash, string $fingerprint): ExperianOutcome
    {
        $inFlightKey = ExperianFingerprint::inFlight($panHash, $request->product->value);

        try {
            return DB::transaction(function () use ($request, $actor, $pan, $name, $mobile, $panHash, $fingerprint, $inFlightKey): ExperianOutcome {
                $inFlight = ExperianCreditRequest::query()
                    ->forPan($panHash)
                    ->inFlight()
                    ->when($request->product !== ExperianProduct::Score, fn ($query) => $query->where('product', $request->product))
                    ->latest('id')
                    ->first();

                if ($inFlight) {
                    return $this->block($inFlight, $request, $actor);
                }

                if ($request->type !== ExperianRequestType::Fresh) {
                    $reusable = ExperianCreditRequest::query()
                        ->forPan($panHash)
                        ->reusableFor($request->product)
                        ->latest('completed_at')
                        ->latest('id')
                        ->first();

                    if ($reusable) {
                        return $this->reuse($reusable, $request, $actor);
                    }
                }

                $this->guardAgainstRepeatingARejection($request, $panHash, $fingerprint);

                $consent = CreditConsentGate::record($request->consent, $request->customer, $actor, 'Experian credit '.$request->product->noun());

                $created = ExperianCreditRequest::query()->create([
                    'customer_id' => $request->customer->id,
                    'journey_session_id' => $request->journeySession?->id,
                    'credit_consent_id' => $consent->id,
                    'previous_request_id' => $request->previousRequest?->id,
                    'requested_by' => $actor->id,
                    'product' => $request->product,
                    'request_type' => $request->type,
                    'channel' => $request->channel,
                    'fresh_reason' => $request->type === ExperianRequestType::Fresh ? trim((string) $request->freshReason) : null,
                    'status' => ExperianRequestStatus::Pending,
                    'pan_hash' => $panHash,
                    'in_flight_key' => $inFlightKey,
                    'request_fingerprint' => $fingerprint,
                    'pan_masked' => PanNumber::mask($pan),
                    'mobile_masked' => MobileNumber::mask($mobile),
                    'pan_encrypted' => $pan,
                    'full_name' => $name,
                    'mobile' => $mobile,
                    'provider' => 'surepass',
                    'requested_at' => now(),
                ]);

                return ExperianOutcome::queued($created);
            });
        } catch (UniqueConstraintViolationException) {
            // Another request for this PAN and product won the race to the in_flight_key index.
            return DB::transaction(function () use ($request, $actor, $inFlightKey): ExperianOutcome {
                $winner = ExperianCreditRequest::query()->where('in_flight_key', $inFlightKey)->firstOrFail();

                return $this->block($winner, $request, $actor);
            });
        }
    }

    private function block(ExperianCreditRequest $inFlight, ExperianCheckRequest $request, User $actor): ExperianOutcome
    {
        $inFlight->newQuery()->whereKey($inFlight->id)->increment('duplicates_blocked');
        $inFlight->recordEvent('duplicate_blocked', [
            'requested_product' => $request->product->value,
            'requested_type' => $request->type->value,
            'customer' => $request->customer->public_id,
            'channel' => $request->channel,
        ], $actor->id);

        return ExperianOutcome::blocked($inFlight->refresh());
    }

    private function reuse(ExperianCreditRequest $reusable, ExperianCheckRequest $request, User $actor): ExperianOutcome
    {
        $reusable->newQuery()->whereKey($reusable->id)->increment('times_reused');
        $reusable->recordEvent('reused', [
            'requested_product' => $request->product->value,
            'requested_type' => $request->type->value,
            'customer' => $request->customer->public_id,
            'channel' => $request->channel,
            'other_customer_record' => $reusable->customer_id !== null && $reusable->customer_id !== $request->customer->id ? 'yes' : null,
        ], $actor->id);

        return ExperianOutcome::reused($reusable->refresh());
    }

    private function guardAgainstRepeatingARejection(ExperianCheckRequest $request, string $panHash, string $fingerprint): void
    {
        if ($request->type === ExperianRequestType::Fresh) {
            return;
        }

        if ($request->type === ExperianRequestType::Retry) {
            $previous = $request->previousRequest;

            if ($previous === null || $previous->pan_hash !== $panHash || $previous->product !== $request->product
                || $previous->status !== ExperianRequestStatus::Failed || ! $previous->retryable) {
                throw new ExperianRequestRefused('Only a failed attempt that Surepass is known not to have processed can be retried.');
            }

            if (ExperianCreditRequest::query()->where('previous_request_id', $previous->id)->exists()) {
                throw new ExperianRequestRefused('This attempt has already been retried. Open the latest attempt instead.');
            }

            return;
        }

        $latest = ExperianCreditRequest::query()->forPan($panHash)->where('product', $request->product)->latest('id')->first();

        if ($latest
            && $latest->status === ExperianRequestStatus::Failed
            && ! $latest->retryable
            && $latest->request_fingerprint === $fingerprint) {
            $reason = $latest->error_code?->getLabel() ?? 'rejected';

            throw new ExperianRequestRefused("The last request with exactly these details failed ({$reason}) and would fail again. Correct the customer details, or ask an authorised user to fetch a fresh result if a new pull is genuinely needed.");
        }
    }

    private function authorize(ExperianCheckRequest $request, User $actor): void
    {
        $allowed = $request->type === ExperianRequestType::Fresh
            ? $actor->can('fetchFresh', ExperianCreditRequest::class)
            : $actor->can('create', ExperianCreditRequest::class);

        if (! $allowed) {
            throw new AuthorizationException('You are not allowed to request this Experian '.$request->product->noun().'.');
        }

        if ($request->type === ExperianRequestType::Fresh && mb_strlen(trim((string) $request->freshReason)) < 15) {
            throw new ExperianRequestRefused('A fresh pull is a second paid request: give the reason it is needed (at least 15 characters).');
        }
    }

    private function validateDetails(string $pan, string $name, string $mobile): void
    {
        if (! PanNumber::isValid($pan)) {
            throw new ExperianRequestRefused('Enter a valid PAN (five letters, four digits, one letter — e.g. ABCDE1234F).');
        }

        if ($name === '' || mb_strlen($name) > 150) {
            throw new ExperianRequestRefused('Enter the customer\'s full name as on the PAN card (150 characters or fewer).');
        }

        if (! MobileNumber::isValid($mobile)) {
            throw new ExperianRequestRefused('Enter the customer\'s 10-digit Indian mobile number.');
        }
    }
}
