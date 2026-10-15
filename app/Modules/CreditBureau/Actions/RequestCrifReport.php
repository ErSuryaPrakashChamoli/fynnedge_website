<?php

namespace App\Modules\CreditBureau\Actions;

use App\Models\User;
use App\Modules\CreditBureau\DataTransferObjects\CrifReportRequest;
use App\Modules\CreditBureau\DataTransferObjects\CrifRequestOutcome;
use App\Modules\CreditBureau\Enums\ConsentMethod;
use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Enums\CrifReportStatus;
use App\Modules\CreditBureau\Enums\CrifRequestType;
use App\Modules\CreditBureau\Exceptions\CrifRequestRefused;
use App\Modules\CreditBureau\Jobs\FetchCrifReport;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\CreditBureau\Models\CrifReport;
use App\Modules\CreditBureau\Services\CrifRequestFingerprint;
use App\Modules\CreditBureau\Services\CrifSettings;
use App\Modules\CreditBureau\Services\PanNumber;
use App\Modules\MobileOtp\Services\MobileNumber;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The only way a CRIF report request is started. It decides whether a PAID
 * Surepass call happens at all, in this order:
 *
 *  1. Permission, integration switched on, PAN / name / mobile valid, and a
 *     consent that is Given, recent enough and belongs to this customer and PAN.
 *  2. A request for the same PAN already pending, processing or awaiting
 *     reconciliation → BLOCKED; the in-flight one is shown instead.
 *  3. A successful report inside the reuse period (unless this is an authorised
 *     Fresh request) → REUSED; no call is made.
 *  4. The same details were already rejected by Surepass → refused, so an
 *     identical request is not paid for again (correct the details, or an
 *     authorised user fetches a fresh report).
 *  5. Otherwise a pending row is created and the paid call is queued.
 *
 * Two employees clicking at once are serialised by a per-PAN cache lock, and
 * the unique index on crif_reports.in_flight_key is the final guarantee: if two
 * requests ever slipped past the lock, the database still refuses the second
 * in-flight row and it is answered as BLOCKED.
 */
class RequestCrifReport
{
    private const LOCK_SECONDS = 30;

    private const LOCK_WAIT_SECONDS = 10;

    public function handle(CrifReportRequest $request, User $actor): CrifRequestOutcome
    {
        $this->authorize($request, $actor);

        if (! CrifSettings::enabled()) {
            throw new CrifRequestRefused('CRIF reports are switched off. An administrator can enable them under Credit Bureau → CRIF Settings.');
        }

        if (! CrifSettings::isConfigured()) {
            throw new CrifRequestRefused('The Surepass integration is not configured on this server. Ask an administrator to set the API token.');
        }

        $pan = PanNumber::normalize($request->pan);
        $firstName = $this->cleanName($request->firstName);
        $lastName = $this->cleanName($request->lastName);
        $mobile = MobileNumber::normalize($request->mobile);

        $this->validateDetails($pan, $firstName, $lastName, $mobile);
        $this->validateConsent($request, $pan);

        $panHash = PanNumber::hash($pan);
        $fingerprint = CrifRequestFingerprint::make($pan, $firstName, $lastName, $mobile);

        try {
            $outcome = Cache::lock('crif-request:'.$panHash, self::LOCK_SECONDS)->block(
                self::LOCK_WAIT_SECONDS,
                fn () => $this->decide($request, $actor, $pan, $firstName, $lastName, $mobile, $panHash, $fingerprint),
            );
        } catch (LockTimeoutException) {
            throw new CrifRequestRefused('Another CRIF request for this PAN is being started right now. Wait a moment and open the customer\'s CRIF reports.');
        }

        if ($outcome->kind === CrifRequestOutcome::QUEUED) {
            FetchCrifReport::dispatch($outcome->report->id);
        }

        Log::info('CRIF report request answered', [
            'outcome' => $outcome->kind,
            'report' => $outcome->report->public_id,
            'pan' => $outcome->report->pan_masked,
            'type' => $request->type->value,
            'user_id' => $actor->id,
        ]);

        return $outcome;
    }

    private function decide(
        CrifReportRequest $request,
        User $actor,
        string $pan,
        string $firstName,
        string $lastName,
        string $mobile,
        string $panHash,
        string $fingerprint,
    ): CrifRequestOutcome {
        try {
            return DB::transaction(function () use ($request, $actor, $pan, $firstName, $lastName, $mobile, $panHash, $fingerprint): CrifRequestOutcome {
                $inFlight = CrifReport::query()->forPan($panHash)->inFlight()->latest('id')->first();

                if ($inFlight) {
                    return $this->block($inFlight, $request, $actor);
                }

                if ($request->type !== CrifRequestType::Fresh) {
                    $reusable = CrifReport::query()->forPan($panHash)->reusable()->latest('completed_at')->latest('id')->first();

                    if ($reusable) {
                        return $this->reuse($reusable, $request, $actor);
                    }
                }

                $this->guardAgainstRepeatingARejection($request, $panHash, $fingerprint);

                $consent = $this->consentFor($request, $actor, $pan);

                $report = CrifReport::query()->create([
                    'customer_id' => $request->customer->id,
                    'journey_session_id' => $request->journeySession?->id,
                    'credit_consent_id' => $consent->id,
                    'previous_report_id' => $request->previousReport?->id,
                    'requested_by' => $actor->id,
                    'request_type' => $request->type,
                    'fresh_reason' => $request->type === CrifRequestType::Fresh ? trim((string) $request->freshReason) : null,
                    'status' => CrifReportStatus::Pending,
                    'pan_hash' => $panHash,
                    'in_flight_key' => $panHash,
                    'request_fingerprint' => $fingerprint,
                    'pan_masked' => PanNumber::mask($pan),
                    'mobile_masked' => MobileNumber::mask($mobile),
                    'pan_encrypted' => $pan,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'mobile' => $mobile,
                    'provider' => 'surepass',
                    'requested_at' => now(),
                ]);

                return CrifRequestOutcome::queued($report);
            });
        } catch (UniqueConstraintViolationException) {
            // Another request for this PAN won the race to the in_flight_key index.
            return DB::transaction(function () use ($request, $actor, $panHash): CrifRequestOutcome {
                $winner = CrifReport::query()->where('in_flight_key', $panHash)->firstOrFail();

                return $this->block($winner, $request, $actor);
            });
        }
    }

    private function block(CrifReport $inFlight, CrifReportRequest $request, User $actor): CrifRequestOutcome
    {
        $inFlight->newQuery()->whereKey($inFlight->id)->increment('duplicates_blocked');
        $inFlight->recordEvent('duplicate_blocked', [
            'requested_type' => $request->type->value,
            'customer' => $request->customer->public_id,
        ], $actor->id);

        return CrifRequestOutcome::blocked($inFlight->refresh());
    }

    private function reuse(CrifReport $reusable, CrifReportRequest $request, User $actor): CrifRequestOutcome
    {
        $reusable->newQuery()->whereKey($reusable->id)->increment('times_reused');
        $reusable->recordEvent('reused', [
            'requested_type' => $request->type->value,
            'customer' => $request->customer->public_id,
            'other_customer_record' => $reusable->customer_id !== null && $reusable->customer_id !== $request->customer->id ? 'yes' : null,
        ], $actor->id);

        return CrifRequestOutcome::reused($reusable->refresh());
    }

    /**
     * After Surepass rejected a request (invalid details, no record, declined),
     * sending the identical details again would be paid for and fail the same
     * way. Corrected details are a new request; an authorised Fresh request
     * overrides this when a new pull is genuinely needed.
     */
    private function guardAgainstRepeatingARejection(CrifReportRequest $request, string $panHash, string $fingerprint): void
    {
        if ($request->type === CrifRequestType::Fresh) {
            return;
        }

        if ($request->type === CrifRequestType::Retry) {
            $previous = $request->previousReport;

            if ($previous === null || $previous->pan_hash !== $panHash || $previous->status !== CrifReportStatus::Failed || ! $previous->retryable) {
                throw new CrifRequestRefused('Only a failed attempt that Surepass is known not to have processed can be retried.');
            }

            if (CrifReport::query()->where('previous_report_id', $previous->id)->exists()) {
                throw new CrifRequestRefused('This attempt has already been retried. Open the latest attempt instead.');
            }

            return;
        }

        $latest = CrifReport::query()->forPan($panHash)->latest('id')->first();

        if ($latest
            && $latest->status === CrifReportStatus::Failed
            && ! $latest->retryable
            && $latest->request_fingerprint === $fingerprint) {
            $reason = $latest->error_code?->getLabel() ?? 'rejected';

            throw new CrifRequestRefused("The last request with exactly these details failed ({$reason}) and would fail again. Correct the customer details, or ask an authorised user to fetch a fresh report if a new pull is genuinely needed.");
        }
    }

    private function authorize(CrifReportRequest $request, User $actor): void
    {
        $allowed = $request->type === CrifRequestType::Fresh
            ? $actor->can('fetchFresh', CrifReport::class)
            : $actor->can('create', CrifReport::class);

        if (! $allowed) {
            throw new AuthorizationException('You are not allowed to request this CRIF report.');
        }

        if ($request->type === CrifRequestType::Fresh && mb_strlen(trim((string) $request->freshReason)) < 15) {
            throw new CrifRequestRefused('A fresh report is a second paid pull: give the reason it is needed (at least 15 characters).');
        }
    }

    private function validateDetails(string $pan, string $firstName, string $lastName, string $mobile): void
    {
        if (! PanNumber::isValid($pan)) {
            throw new CrifRequestRefused('Enter a valid PAN (five letters, four digits, one letter — e.g. ABCDE1234F).');
        }

        if ($firstName === '' || $lastName === '') {
            throw new CrifRequestRefused('The customer\'s first and last name are both required for a CRIF report.');
        }

        if (mb_strlen($firstName) > 100 || mb_strlen($lastName) > 100) {
            throw new CrifRequestRefused('The customer\'s first and last name must each be 100 characters or fewer.');
        }

        if (! MobileNumber::isValid($mobile)) {
            throw new CrifRequestRefused('Enter the customer\'s 10-digit Indian mobile number.');
        }
    }

    private function validateConsent(CrifReportRequest $request, string $pan): void
    {
        $input = $request->consent;
        $earliest = now()->subDays(CrifSettings::consentValidityDays());

        if ($input->existing) {
            $this->validateExistingConsent($input->existing, $request, $pan, $earliest);

            return;
        }

        if ($input->method === null || ! array_key_exists($input->method->value, ConsentMethod::employeeRecordable())) {
            throw new CrifRequestRefused('Choose how the customer gave consent.');
        }

        if (mb_strlen((string) $input->evidenceReference) < 3) {
            throw new CrifRequestRefused('Enter the consent evidence reference (e.g. the form number, call ID or email subject).');
        }

        if ($input->consentedAt === null || $input->consentedAt->isFuture() || $input->consentedAt->lt($earliest)) {
            throw new CrifRequestRefused('The consent date must be within the last '.CrifSettings::consentValidityDays().' days and not in the future.');
        }

        if (! $input->attested) {
            throw new CrifRequestRefused('Confirm that the customer consented to this CRIF credit report.');
        }
    }

    private function validateExistingConsent(CreditConsent $consent, CrifReportRequest $request, string $pan, CarbonInterface $earliest): void
    {
        $consentCustomerId = $consent->customer_id ?? $consent->journeySession?->customer_id;

        if ($consent->status !== ConsentStatus::Given) {
            throw new CrifRequestRefused('The customer\'s consent has been withdrawn. Record fresh consent before requesting a report.');
        }

        if ($consentCustomerId !== $request->customer->id) {
            throw new CrifRequestRefused('That consent belongs to a different customer.');
        }

        if ($consent->consented_at === null || $consent->consented_at->lt($earliest)) {
            throw new CrifRequestRefused('The customer\'s consent is older than '.CrifSettings::consentValidityDays().' days. Record fresh consent before requesting a report.');
        }

        if (filled($consent->pan_number) && PanNumber::normalize($consent->pan_number) !== $pan) {
            throw new CrifRequestRefused('The customer consented for a different PAN than the one entered.');
        }
    }

    private function consentFor(CrifReportRequest $request, User $actor, string $pan): CreditConsent
    {
        $input = $request->consent;

        if ($input->existing) {
            return $input->existing;
        }

        return CreditConsent::query()->create([
            'customer_id' => $request->customer->id,
            'journey_session_id' => null,
            'purpose' => 'CRIF credit report',
            'method' => $input->method,
            'evidence_reference' => mb_substr((string) $input->evidenceReference, 0, 255),
            'recorded_by' => $actor->id,
            'terms_version' => config('services.credit_bureau.terms_version', 'v1'),
            'status' => ConsentStatus::Given,
            'consented_at' => $input->consentedAt,
        ]);
    }

    private function cleanName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', $name) ?? '');
    }
}
