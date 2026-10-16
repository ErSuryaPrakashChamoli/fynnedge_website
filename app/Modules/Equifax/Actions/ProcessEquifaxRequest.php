<?php

namespace App\Modules\Equifax\Actions;

use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\Equifax\DataTransferObjects\EquifaxSubject;
use App\Modules\Equifax\DataTransferObjects\SurepassEquifaxResult;
use App\Modules\Equifax\Enums\EquifaxErrorCode;
use App\Modules\Equifax\Enums\EquifaxProduct;
use App\Modules\Equifax\Enums\EquifaxRequestStatus;
use App\Modules\Equifax\Enums\Gender;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Equifax\Notifications\EquifaxOperationalAlert;
use App\Modules\Equifax\Services\EquifaxReportDocument;
use App\Modules\Equifax\Services\EquifaxSettings;
use App\Modules\Equifax\Services\SurepassEquifaxService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Sends ONE pending Equifax request to Surepass and records what came back.
 *
 * The pending → processing step is a conditional UPDATE, so however many times
 * the job is delivered (worker restart, duplicate dispatch, a stale-request
 * sweep racing it) only one execution ever makes the paid call for a row.
 */
class ProcessEquifaxRequest
{
    public function __construct(private readonly SurepassEquifaxService $surepass) {}

    public function handle(int $requestId): void
    {
        $claimed = EquifaxCreditRequest::query()
            ->whereKey($requestId)
            ->where('status', EquifaxRequestStatus::Pending)
            ->update(['status' => EquifaxRequestStatus::Processing, 'processing_started_at' => now(), 'updated_at' => now()]);

        if ($claimed !== 1) {
            return;
        }

        $request = EquifaxCreditRequest::query()->with('consent')->findOrFail($requestId);
        $request->recordEvent('processing_started');

        if (! EquifaxSettings::isAvailable()) {
            $this->fail($request, EquifaxErrorCode::NotConfigured);

            return;
        }

        if ($request->consent?->status !== ConsentStatus::Given) {
            $this->fail($request, EquifaxErrorCode::ConsentInvalid);

            return;
        }

        $subject = new EquifaxSubject(
            name: (string) $request->full_name,
            pan: (string) $request->pan_encrypted,
            mobile: (string) $request->mobile,
            gender: $request->gender ?? Gender::Male,
            consent: $request->consent,
        );

        $result = $request->product === EquifaxProduct::Score
            ? $this->surepass->fetchScore($subject)
            : $this->surepass->fetchReport($subject);

        $this->record($request, $result);
    }

    /**
     * Stores what Surepass answered for a request that is being processed —
     * from the queued job above, or from the public score page, which makes
     * its call in the visitor's request (RecordWebsiteEquifaxCheck).
     */
    public function record(EquifaxCreditRequest $request, SurepassEquifaxResult $result): void
    {
        match (true) {
            $result->isSuccessful() => $this->succeed($request, $result),
            $result->isUncertain() => $this->needsReconciliation($request, $result->errorCode, $result),
            default => $this->fail($request, $result->errorCode, $result),
        };
    }

    /**
     * The job died (worker killed, timeout, unexpected exception).
     */
    public static function markInterrupted(int $requestId): void
    {
        $request = EquifaxCreditRequest::query()->find($requestId);

        if ($request) {
            app(self::class)->interrupt($request);
        }
    }

    /**
     * Resolves a request that stopped moving — from the job's failed() hook or
     * the `equifax:flag-stale` sweep. Pending was never sent, so it fails as
     * retryable; Processing may have been sent, so it awaits reconciliation and
     * keeps the PAN locked. Conditional on the status read, so a job that claims
     * the row at the same moment wins.
     */
    public function interrupt(EquifaxCreditRequest $request): bool
    {
        [$from, $to, $code] = match ($request->status) {
            EquifaxRequestStatus::Pending => [EquifaxRequestStatus::Pending, EquifaxRequestStatus::Failed, EquifaxErrorCode::QueueStalled],
            EquifaxRequestStatus::Processing => [EquifaxRequestStatus::Processing, EquifaxRequestStatus::ReconciliationRequired, EquifaxErrorCode::ProcessingStalled],
            default => [null, null, null],
        };

        if ($from === null) {
            return false;
        }

        $changed = EquifaxCreditRequest::query()
            ->whereKey($request->id)
            ->where('status', $from)
            ->update([
                'status' => $to,
                ...($to === EquifaxRequestStatus::Failed ? ['in_flight_key' => null] : []),
                'error_code' => $code,
                'error_message' => $code->safeMessage(),
                'retryable' => $code->isRetryable(),
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

        if ($changed !== 1) {
            return false;
        }

        $request->refresh();
        $request->recordEvent('interrupted', ['status' => $to->value, 'error' => $code->value]);
        Log::warning('Equifax request interrupted', ['request' => $request->public_id, 'pan' => $request->pan_masked, 'error' => $code->value]);
        $this->alert($request);

        return true;
    }

    private function succeed(EquifaxCreditRequest $request, SurepassEquifaxResult $result): void
    {
        try {
            $document = new EquifaxReportDocument($result->body ?? []);
            $isReport = $request->product === EquifaxProduct::Report;

            $request->update([
                'status' => EquifaxRequestStatus::Success,
                'in_flight_key' => null,
                'score' => $document->score(),
                'http_status' => $result->httpStatus,
                'provider_message_code' => $result->messageCode,
                'provider_client_id' => $this->limit($document->clientId()),
                'provider_report_id' => $isReport ? $this->limit($document->header()['order_number']) : null,
                'report_date' => $isReport ? $document->reportDate() : null,
                'raw_response' => $result->body,
                'summary' => $isReport ? $document->summary() : ['score' => $document->score()],
                'error_code' => null,
                'error_message' => null,
                'retryable' => false,
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            // Paid for but not saved — never retry it blindly.
            Log::error('Equifax result could not be saved', ['request' => $request->public_id, 'exception' => $exception::class]);
            $this->needsReconciliation($request->refresh(), EquifaxErrorCode::PersistenceFailed, $result);

            return;
        }

        Log::info('Equifax result fetched', ['request' => $request->public_id, 'product' => $request->product->value, 'pan' => $request->pan_masked, 'http_status' => $result->httpStatus]);
    }

    private function fail(EquifaxCreditRequest $request, EquifaxErrorCode $code, ?SurepassEquifaxResult $result = null): void
    {
        $request->update([
            'status' => EquifaxRequestStatus::Failed,
            'in_flight_key' => null,
            'error_code' => $code,
            'error_message' => $code->safeMessage(),
            'retryable' => $code->isRetryable(),
            'http_status' => $result?->httpStatus,
            'provider_message_code' => $result?->messageCode,
            'completed_at' => now(),
        ]);

        Log::warning('Equifax request failed', [
            'request' => $request->public_id,
            'product' => $request->product->value,
            'pan' => $request->pan_masked,
            'error' => $code->value,
            'http_status' => $result?->httpStatus,
            'message_code' => $result?->messageCode,
        ]);

        if (in_array($code, [EquifaxErrorCode::AuthenticationFailed, EquifaxErrorCode::ApiNotEnabled, EquifaxErrorCode::InsufficientBalance, EquifaxErrorCode::NotConfigured], true)) {
            $this->alert($request);
        }
    }

    /**
     * Keeps the PAN's in-flight lock: nothing more of this product can be sent
     * for this PAN until an admin reconciles it against the Surepass dashboard.
     */
    private function needsReconciliation(EquifaxCreditRequest $request, EquifaxErrorCode $code, ?SurepassEquifaxResult $result = null): void
    {
        $request->update([
            'status' => EquifaxRequestStatus::ReconciliationRequired,
            'error_code' => $code,
            'error_message' => $code->safeMessage(),
            'retryable' => false,
            'http_status' => $result?->httpStatus,
            'provider_message_code' => $result?->messageCode,
            'completed_at' => now(),
        ]);

        Log::warning('Equifax request needs reconciliation', [
            'request' => $request->public_id,
            'pan' => $request->pan_masked,
            'error' => $code->value,
            'http_status' => $result?->httpStatus,
        ]);

        $this->alert($request);
    }

    private function alert(EquifaxCreditRequest $request): void
    {
        $email = EquifaxSettings::alertEmail();

        if ($email === null) {
            return;
        }

        try {
            Notification::route('mail', $email)->notify(new EquifaxOperationalAlert($request));
        } catch (Throwable $exception) {
            Log::warning('Equifax operational alert could not be sent', ['request' => $request->public_id, 'exception' => $exception::class]);
        }
    }

    private function limit(?string $value): ?string
    {
        return $value === null ? null : mb_substr($value, 0, 191);
    }
}
