<?php

namespace App\Modules\Experian\Actions;

use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\Experian\DataTransferObjects\ExperianSubject;
use App\Modules\Experian\DataTransferObjects\SurepassExperianResult;
use App\Modules\Experian\Enums\ExperianErrorCode;
use App\Modules\Experian\Enums\ExperianProduct;
use App\Modules\Experian\Enums\ExperianRequestStatus;
use App\Modules\Experian\Models\ExperianCreditRequest;
use App\Modules\Experian\Notifications\ExperianOperationalAlert;
use App\Modules\Experian\Services\ExperianReportDocument;
use App\Modules\Experian\Services\ExperianSettings;
use App\Modules\Experian\Services\SurepassExperianService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Sends ONE pending Experian request to Surepass and records what came back —
 * for a PDF request, also downloading the linked PDF into private storage.
 *
 * The pending → processing step is a conditional UPDATE, so however many times
 * the job is delivered (worker restart, duplicate dispatch, a stale-request
 * sweep racing it) only one execution ever makes the paid call for a row.
 */
class ProcessExperianRequest
{
    public function __construct(
        private readonly SurepassExperianService $surepass,
        private readonly FetchExperianPdf $fetchPdf,
    ) {}

    public function handle(int $requestId): void
    {
        $claimed = ExperianCreditRequest::query()
            ->whereKey($requestId)
            ->where('status', ExperianRequestStatus::Pending)
            ->update(['status' => ExperianRequestStatus::Processing, 'processing_started_at' => now(), 'updated_at' => now()]);

        if ($claimed !== 1) {
            return;
        }

        $request = ExperianCreditRequest::query()->with('consent')->findOrFail($requestId);
        $request->recordEvent('processing_started');

        if (! ExperianSettings::isAvailable()) {
            $this->fail($request, ExperianErrorCode::NotConfigured);

            return;
        }

        if ($request->consent?->status !== ConsentStatus::Given) {
            $this->fail($request, ExperianErrorCode::ConsentInvalid);

            return;
        }

        $subject = new ExperianSubject(
            name: (string) $request->full_name,
            pan: (string) $request->pan_encrypted,
            mobile: (string) $request->mobile,
            consent: $request->consent,
        );

        $result = match ($request->product) {
            ExperianProduct::Score => $this->surepass->fetchScore($subject),
            ExperianProduct::Report => $this->surepass->fetchReport($subject),
            ExperianProduct::Pdf => $this->surepass->fetchPdfReport($subject),
        };

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
        $request = ExperianCreditRequest::query()->find($requestId);

        if ($request) {
            app(self::class)->interrupt($request);
        }
    }

    /**
     * Resolves a request that stopped moving — from the job's failed() hook or
     * the `experian:flag-stale` sweep. Pending was never sent, so it fails as
     * retryable; Processing may have been sent, so it awaits reconciliation and
     * keeps the PAN locked. Conditional on the status read, so a job that claims
     * the row at the same moment wins.
     */
    public function interrupt(ExperianCreditRequest $request): bool
    {
        [$from, $to, $code] = match ($request->status) {
            ExperianRequestStatus::Pending => [ExperianRequestStatus::Pending, ExperianRequestStatus::Failed, ExperianErrorCode::QueueStalled],
            ExperianRequestStatus::Processing => [ExperianRequestStatus::Processing, ExperianRequestStatus::ReconciliationRequired, ExperianErrorCode::ProcessingStalled],
            default => [null, null, null],
        };

        if ($from === null) {
            return false;
        }

        $changed = ExperianCreditRequest::query()
            ->whereKey($request->id)
            ->where('status', $from)
            ->update([
                'status' => $to,
                ...($to === ExperianRequestStatus::Failed ? ['in_flight_key' => null] : []),
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
        Log::warning('Experian request interrupted', ['request' => $request->public_id, 'pan' => $request->pan_masked, 'error' => $code->value]);
        $this->alert($request);

        return true;
    }

    private function succeed(ExperianCreditRequest $request, SurepassExperianResult $result): void
    {
        try {
            $document = new ExperianReportDocument($result->body ?? []);
            $isReport = $request->product === ExperianProduct::Report;

            $request->update([
                'status' => ExperianRequestStatus::Success,
                'in_flight_key' => null,
                'score' => $document->score(),
                'http_status' => $result->httpStatus,
                'provider_message_code' => $result->messageCode,
                'provider_client_id' => $this->limit($document->clientId()),
                'provider_report_id' => $isReport ? $this->limit($document->header()['report_number']) : null,
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
            Log::error('Experian result could not be saved', ['request' => $request->public_id, 'exception' => $exception::class]);
            $this->needsReconciliation($request->refresh(), ExperianErrorCode::PersistenceFailed, $result);

            return;
        }

        Log::info('Experian result fetched', ['request' => $request->public_id, 'product' => $request->product->value, 'pan' => $request->pan_masked, 'http_status' => $result->httpStatus]);

        if ($request->product === ExperianProduct::Pdf) {
            // The link is temporary: download the paid-for PDF now, into
            // encrypted private storage. A failure leaves the request successful
            // and the link held, so FetchExperianPdf can try it once more.
            try {
                $this->fetchPdf->storeFromLink($request);
            } catch (Throwable $exception) {
                Log::warning('Experian report PDF could not be stored', ['request' => $request->public_id, 'exception' => $exception::class]);
            }
        }
    }

    private function fail(ExperianCreditRequest $request, ExperianErrorCode $code, ?SurepassExperianResult $result = null): void
    {
        $request->update([
            'status' => ExperianRequestStatus::Failed,
            'in_flight_key' => null,
            'error_code' => $code,
            'error_message' => $code->safeMessage(),
            'retryable' => $code->isRetryable(),
            'http_status' => $result?->httpStatus,
            'provider_message_code' => $result?->messageCode,
            'completed_at' => now(),
        ]);

        Log::warning('Experian request failed', [
            'request' => $request->public_id,
            'product' => $request->product->value,
            'pan' => $request->pan_masked,
            'error' => $code->value,
            'http_status' => $result?->httpStatus,
            'message_code' => $result?->messageCode,
        ]);

        if (in_array($code, [ExperianErrorCode::AuthenticationFailed, ExperianErrorCode::ApiNotEnabled, ExperianErrorCode::InsufficientBalance, ExperianErrorCode::NotConfigured], true)) {
            $this->alert($request);
        }
    }

    /**
     * Keeps the PAN's in-flight lock: nothing more of this product can be sent
     * for this PAN until an admin reconciles it against the Surepass dashboard.
     */
    private function needsReconciliation(ExperianCreditRequest $request, ExperianErrorCode $code, ?SurepassExperianResult $result = null): void
    {
        $request->update([
            'status' => ExperianRequestStatus::ReconciliationRequired,
            'error_code' => $code,
            'error_message' => $code->safeMessage(),
            'retryable' => false,
            'http_status' => $result?->httpStatus,
            'provider_message_code' => $result?->messageCode,
            'completed_at' => now(),
        ]);

        Log::warning('Experian request needs reconciliation', [
            'request' => $request->public_id,
            'pan' => $request->pan_masked,
            'error' => $code->value,
            'http_status' => $result?->httpStatus,
        ]);

        $this->alert($request);
    }

    private function alert(ExperianCreditRequest $request): void
    {
        $email = ExperianSettings::alertEmail();

        if ($email === null) {
            return;
        }

        try {
            Notification::route('mail', $email)->notify(new ExperianOperationalAlert($request));
        } catch (Throwable $exception) {
            Log::warning('Experian operational alert could not be sent', ['request' => $request->public_id, 'exception' => $exception::class]);
        }
    }

    private function limit(?string $value): ?string
    {
        return $value === null ? null : mb_substr($value, 0, 191);
    }
}
