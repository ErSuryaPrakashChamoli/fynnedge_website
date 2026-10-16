<?php

namespace App\Modules\Equifax\Actions;

use App\Models\User;
use App\Modules\CreditBureau\Services\CreditConsentGate;
use App\Modules\CreditBureau\Services\PanNumber;
use App\Modules\Equifax\DataTransferObjects\EquifaxCheckRequest;
use App\Modules\Equifax\DataTransferObjects\EquifaxOutcome;
use App\Modules\Equifax\Enums\EquifaxProduct;
use App\Modules\Equifax\Enums\EquifaxRequestStatus;
use App\Modules\Equifax\Enums\EquifaxRequestType;
use App\Modules\Equifax\Exceptions\EquifaxRequestRefused;
use App\Modules\Equifax\Jobs\FetchEquifaxRequest;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Equifax\Services\EquifaxFingerprint;
use App\Modules\Equifax\Services\EquifaxSettings;
use App\Modules\MobileOtp\Services\MobileNumber;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The only way an Equifax score or report request is started — from the
 * admin, the internal API or another module (EquifaxCreditService). It decides
 * whether a PAID Surepass call happens at all, in this order:
 *
 *  1. Permission, integration on, PAN / name / mobile / gender valid, and a
 *     consent that is Given, recent enough and belongs to this customer and PAN.
 *  2. An equivalent request for the PAN already in flight → BLOCKED (a report
 *     in flight also answers a score request; a score never answers a report).
 *  3. A successful result inside the product's reuse period (unless this is an
 *     authorised Fresh request) → REUSED; no call is made.
 *  4. Surepass already rejected these exact details → refused, so an identical
 *     request is not paid for again.
 *  5. Otherwise a pending row is created and the paid call is queued.
 *
 * Simultaneous callers are serialised by a per-PAN cache lock, and the unique
 * index on equifax_credit_requests.in_flight_key (PAN + product) is the final
 * guarantee: a second unresolved row for the same PAN and product is refused by
 * the database and answered as BLOCKED.
 */
class RequestEquifaxCheck
{
    private const LOCK_SECONDS = 30;

    private const LOCK_WAIT_SECONDS = 10;

    public function handle(EquifaxCheckRequest $request, User $actor): EquifaxOutcome
    {
        $this->authorize($request, $actor);

        if (! EquifaxSettings::enabled()) {
            throw new EquifaxRequestRefused('Equifax requests are switched off. An administrator can enable them under Equifax → Equifax Settings.');
        }

        if (! EquifaxSettings::isConfigured()) {
            throw new EquifaxRequestRefused('The Surepass integration is not configured on this server. Ask an administrator to set the API token.');
        }

        $pan = PanNumber::normalize($request->pan);
        $name = trim(preg_replace('/\s+/', ' ', $request->name) ?? '');
        $mobile = MobileNumber::normalize($request->mobile);

        $this->validateDetails($request, $pan, $name, $mobile);

        $refusal = CreditConsentGate::refusal($request->consent, $request->customer, $pan, EquifaxSettings::consentValidityDays());

        if ($refusal !== null) {
            throw new EquifaxRequestRefused($refusal);
        }

        $panHash = PanNumber::hash($pan);
        $fingerprint = EquifaxFingerprint::request($pan, $name, $mobile, $request->gender->value);

        try {
            $outcome = Cache::lock('equifax-request:'.$panHash, self::LOCK_SECONDS)->block(
                self::LOCK_WAIT_SECONDS,
                fn () => $this->decide($request, $actor, $pan, $name, $mobile, $panHash, $fingerprint),
            );
        } catch (LockTimeoutException) {
            throw new EquifaxRequestRefused('Another Equifax request for this PAN is being started right now. Wait a moment and open the customer\'s Equifax requests.');
        }

        if ($outcome->kind === EquifaxOutcome::QUEUED) {
            FetchEquifaxRequest::dispatch($outcome->request->id);
        }

        Log::info('Equifax request answered', [
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

    private function decide(EquifaxCheckRequest $request, User $actor, string $pan, string $name, string $mobile, string $panHash, string $fingerprint): EquifaxOutcome
    {
        $inFlightKey = EquifaxFingerprint::inFlight($panHash, $request->product->value);

        try {
            return DB::transaction(function () use ($request, $actor, $pan, $name, $mobile, $panHash, $fingerprint, $inFlightKey): EquifaxOutcome {
                $inFlight = EquifaxCreditRequest::query()
                    ->forPan($panHash)
                    ->inFlight()
                    ->when($request->product === EquifaxProduct::Report, fn ($query) => $query->where('product', EquifaxProduct::Report))
                    ->latest('id')
                    ->first();

                if ($inFlight) {
                    return $this->block($inFlight, $request, $actor);
                }

                if ($request->type !== EquifaxRequestType::Fresh) {
                    $reusable = EquifaxCreditRequest::query()
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

                $consent = CreditConsentGate::record($request->consent, $request->customer, $actor, 'Equifax credit '.$request->product->value);

                $created = EquifaxCreditRequest::query()->create([
                    'customer_id' => $request->customer->id,
                    'journey_session_id' => $request->journeySession?->id,
                    'credit_consent_id' => $consent->id,
                    'previous_request_id' => $request->previousRequest?->id,
                    'requested_by' => $actor->id,
                    'product' => $request->product,
                    'request_type' => $request->type,
                    'channel' => $request->channel,
                    'fresh_reason' => $request->type === EquifaxRequestType::Fresh ? trim((string) $request->freshReason) : null,
                    'status' => EquifaxRequestStatus::Pending,
                    'pan_hash' => $panHash,
                    'in_flight_key' => $inFlightKey,
                    'request_fingerprint' => $fingerprint,
                    'pan_masked' => PanNumber::mask($pan),
                    'mobile_masked' => MobileNumber::mask($mobile),
                    'pan_encrypted' => $pan,
                    'full_name' => $name,
                    'mobile' => $mobile,
                    'gender' => $request->gender,
                    'provider' => 'surepass',
                    'requested_at' => now(),
                ]);

                return EquifaxOutcome::queued($created);
            });
        } catch (UniqueConstraintViolationException) {
            // Another request for this PAN and product won the race to the in_flight_key index.
            return DB::transaction(function () use ($request, $actor, $inFlightKey): EquifaxOutcome {
                $winner = EquifaxCreditRequest::query()->where('in_flight_key', $inFlightKey)->firstOrFail();

                return $this->block($winner, $request, $actor);
            });
        }
    }

    private function block(EquifaxCreditRequest $inFlight, EquifaxCheckRequest $request, User $actor): EquifaxOutcome
    {
        $inFlight->newQuery()->whereKey($inFlight->id)->increment('duplicates_blocked');
        $inFlight->recordEvent('duplicate_blocked', [
            'requested_product' => $request->product->value,
            'requested_type' => $request->type->value,
            'customer' => $request->customer->public_id,
            'channel' => $request->channel,
        ], $actor->id);

        return EquifaxOutcome::blocked($inFlight->refresh());
    }

    private function reuse(EquifaxCreditRequest $reusable, EquifaxCheckRequest $request, User $actor): EquifaxOutcome
    {
        $reusable->newQuery()->whereKey($reusable->id)->increment('times_reused');
        $reusable->recordEvent('reused', [
            'requested_product' => $request->product->value,
            'requested_type' => $request->type->value,
            'customer' => $request->customer->public_id,
            'channel' => $request->channel,
            'other_customer_record' => $reusable->customer_id !== null && $reusable->customer_id !== $request->customer->id ? 'yes' : null,
        ], $actor->id);

        return EquifaxOutcome::reused($reusable->refresh());
    }

    private function guardAgainstRepeatingARejection(EquifaxCheckRequest $request, string $panHash, string $fingerprint): void
    {
        if ($request->type === EquifaxRequestType::Fresh) {
            return;
        }

        if ($request->type === EquifaxRequestType::Retry) {
            $previous = $request->previousRequest;

            if ($previous === null || $previous->pan_hash !== $panHash || $previous->product !== $request->product
                || $previous->status !== EquifaxRequestStatus::Failed || ! $previous->retryable) {
                throw new EquifaxRequestRefused('Only a failed attempt that Surepass is known not to have processed can be retried.');
            }

            if (EquifaxCreditRequest::query()->where('previous_request_id', $previous->id)->exists()) {
                throw new EquifaxRequestRefused('This attempt has already been retried. Open the latest attempt instead.');
            }

            return;
        }

        $latest = EquifaxCreditRequest::query()->forPan($panHash)->where('product', $request->product)->latest('id')->first();

        if ($latest
            && $latest->status === EquifaxRequestStatus::Failed
            && ! $latest->retryable
            && $latest->request_fingerprint === $fingerprint) {
            $reason = $latest->error_code?->getLabel() ?? 'rejected';

            throw new EquifaxRequestRefused("The last request with exactly these details failed ({$reason}) and would fail again. Correct the customer details, or ask an authorised user to fetch a fresh result if a new pull is genuinely needed.");
        }
    }

    private function authorize(EquifaxCheckRequest $request, User $actor): void
    {
        $allowed = $request->type === EquifaxRequestType::Fresh
            ? $actor->can('fetchFresh', EquifaxCreditRequest::class)
            : $actor->can('create', EquifaxCreditRequest::class);

        if (! $allowed) {
            throw new AuthorizationException('You are not allowed to request this Equifax '.$request->product->value.'.');
        }

        if ($request->type === EquifaxRequestType::Fresh && mb_strlen(trim((string) $request->freshReason)) < 15) {
            throw new EquifaxRequestRefused('A fresh pull is a second paid request: give the reason it is needed (at least 15 characters).');
        }
    }

    private function validateDetails(EquifaxCheckRequest $request, string $pan, string $name, string $mobile): void
    {
        if (! PanNumber::isValid($pan)) {
            throw new EquifaxRequestRefused('Enter a valid PAN (five letters, four digits, one letter — e.g. ABCDE1234F).');
        }

        if ($name === '' || mb_strlen($name) > 150) {
            throw new EquifaxRequestRefused('Enter the customer\'s full name as on the PAN card (150 characters or fewer).');
        }

        if (! MobileNumber::isValid($mobile)) {
            throw new EquifaxRequestRefused('Enter the customer\'s 10-digit Indian mobile number.');
        }

        if ($request->gender === null) {
            throw new EquifaxRequestRefused('Choose the customer\'s gender — Equifax requires it.');
        }
    }
}
