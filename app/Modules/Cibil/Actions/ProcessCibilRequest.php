<?php

namespace App\Modules\Cibil\Actions;

use App\Modules\Cibil\DataTransferObjects\CibilSubject;
use App\Modules\Cibil\DataTransferObjects\SurepassCibilResult;
use App\Modules\Cibil\Enums\CibilErrorCode;
use App\Modules\Cibil\Enums\CibilProduct;
use App\Modules\Cibil\Enums\CibilRequestStatus;
use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\Cibil\Notifications\CibilOperationalAlert;
use App\Modules\Cibil\Services\CibilReportDocument;
use App\Modules\Cibil\Services\CibilSettings;
use App\Modules\Cibil\Services\SurepassCibilService;
use App\Modules\CreditBureau\Enums\ConsentStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Sends ONE pending CIBIL request to Surepass and records what came back —
 * for a PDF request, also downloading the linked PDF into private storage.
 *
 * The pending → processing step is a conditional UPDATE, so however many times
 * the job is delivered (worker restart, duplicate dispatch, a stale-request
 * sweep racing it) only one execution ever makes the paid call for a row.
 */
class ProcessCibilRequest
{
    public function __construct(
        private readonly SurepassCibilService $surepass,
        private readonly FetchCibilPdf $fetchPdf,
    ) {}

    public function handle(int $requestId): void
    {
        $claimed = CibilCreditRequest::query()
            ->whereKey($requestId)
            ->where('status', CibilRequestStatus::Pending)
            ->update(['status' => CibilRequestStatus::Processing, 'processing_started_at' => now(), 'updated_at' => now()]);

        if ($claimed !== 1) {
            return;
        }

        $request = CibilCreditRequest::query()->with('consent')->findOrFail($requestId);
        $request->recordEvent('processing_started');

        if (! CibilSettings::isAvailable()) {
            $this->fail($request, CibilErrorCode::NotConfigured);

            return;
        }

        if ($request->consent?->status !== ConsentStatus::Given) {
            $this->fail($request, CibilErrorCode::ConsentInvalid);

            return;
        }

        // CIBIL cannot be asked without a gender (RequestCibilCheck requires one);
        // a row without it is refused here rather than sent.
        if ($request->gender === null) {
            $this->fail($request, CibilErrorCode::InvalidRequest);

            return;
        }

        $subject = new CibilSubject(
            name: (string) $request->full_name,
            pan: (string) $request->pan_encrypted,
            mobile: (string) $request->mobile,
            gender: $request->gender,
            consent: $request->consent,
        );

        $result = match ($request->product) {
            CibilProduct::Score => $this->surepass->fetchScore($subject),
            CibilProduct::Report => $this->surepass->fetchReport($subject),
            CibilProduct::Pdf => $this->surepass->fetchPdfReport($subject),
        };

        $this->record($request, $result);
    }

    /**
     * Stores what Surepass answered for a request that is being processed —
     * from the queued job above, or from the public score page, which makes
     * its call in the visitor's request (RecordWebsiteCibilCheck).
     */
    public function record(CibilCreditRequest $request, SurepassCibilResult $result): void
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
        $request = CibilCreditRequest::query()->find($requestId);

        if ($request) {
            app(self::class)->interrupt($request);
        }
    }

    /**
     * Resolves a request that stopped moving — from the job's failed() hook or
     * the `cibil:flag-stale` sweep. Pending was never sent, so it fails as
     * retryable; Processing may have been sent, so it awaits reconciliation and
     * keeps the PAN locked. Conditional on the status read, so a job that claims
     * the row at the same moment wins.
     */
    public function interrupt(CibilCreditRequest $request): bool
    {
        [$from, $to, $code] = match ($request->status) {
            CibilRequestStatus::Pending => [CibilRequestStatus::Pending, CibilRequestStatus::Failed, CibilErrorCode::QueueStalled],
            CibilRequestStatus::Processing => [CibilRequestStatus::Processing, CibilRequestStatus::ReconciliationRequired, CibilErrorCode::ProcessingStalled],
            default => [null, null, null],
        };

        if ($from === null) {
            return false;
        }

        $changed = CibilCreditRequest::query()
            ->whereKey($request->id)
            ->where('status', $from)
            ->update([
                'status' => $to,
                ...($to === CibilRequestStatus::Failed ? ['in_flight_key' => null] : []),
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
        Log::warning('CIBIL request interrupted', ['request' => $request->public_id, 'pan' => $request->pan_masked, 'error' => $code->value]);
        $this->alert($request);

        return true;
    }

    private function succeed(CibilCreditRequest $request, SurepassCibilResult $result): void
    {
        try {
            $document = new CibilReportDocument($result->body ?? []);
            $isReport = $request->product === CibilProduct::Report;

            $request->update([
                'status' => CibilRequestStatus::Success,
                'in_flight_key' => null,
                'score' => $document->score(),
                'http_status' => $result->httpStatus,
                'provider_message_code' => $result->messageCode,
                'provider_client_id' => $this->limit($document->clientId()),
                'provider_report_id' => $isReport ? $this->limit($document->header()['control_number']) : null,
                'report_date' => $isReport ? $document->reportDate() : null,
                // An inline Base64 PDF is stored as an encrypted file below, not twice in the JSON.
                'raw_response' => $document->withoutInlinePdf(),
                'summary' => $isReport ? $document->summary() : ['score' => $document->score()],
                'error_code' => null,
                'error_message' => null,
                'retryable' => false,
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            // Paid for but not saved — never retry it blindly.
            Log::error('CIBIL result could not be saved', ['request' => $request->public_id, 'exception' => $exception::class]);
            $this->needsReconciliation($request->refresh(), CibilErrorCode::PersistenceFailed, $result);

            return;
        }

        Log::info('CIBIL result fetched', ['request' => $request->public_id, 'product' => $request->product->value, 'pan' => $request->pan_masked, 'http_status' => $result->httpStatus]);

        if ($request->product === CibilProduct::Pdf) {
            // Surepass sends the PDF inline (Base64) or as a temporary link:
            // keep the paid-for PDF now, in encrypted private storage. A failed
            // link download leaves the request successful and the link held,
            // so FetchCibilPdf can try it once more.
            try {
                $inline = $document->inlinePdf();

                $inline !== null
                    ? $this->fetchPdf->store($request, $inline, 'inline_base64')
                    : $this->fetchPdf->storeFromLink($request);
            } catch (Throwable $exception) {
                Log::warning('CIBIL report PDF could not be stored', ['request' => $request->public_id, 'exception' => $exception::class]);
            }
        }
    }

    private function fail(CibilCreditRequest $request, CibilErrorCode $code, ?SurepassCibilResult $result = null): void
    {
        $request->update([
            'status' => CibilRequestStatus::Failed,
            'in_flight_key' => null,
            'error_code' => $code,
            'error_message' => $code->safeMessage(),
            'retryable' => $code->isRetryable(),
            'http_status' => $result?->httpStatus,
            'provider_message_code' => $result?->messageCode,
            'completed_at' => now(),
        ]);

        Log::warning('CIBIL request failed', [
            'request' => $request->public_id,
            'product' => $request->product->value,
            'pan' => $request->pan_masked,
            'error' => $code->value,
            'http_status' => $result?->httpStatus,
            'message_code' => $result?->messageCode,
        ]);

        if (in_array($code, [CibilErrorCode::AuthenticationFailed, CibilErrorCode::ApiNotEnabled, CibilErrorCode::InsufficientBalance, CibilErrorCode::NotConfigured], true)) {
            $this->alert($request);
        }
    }

    /**
     * Keeps the PAN's in-flight lock: nothing more of this product can be sent
     * for this PAN until an admin reconciles it against the Surepass dashboard.
     */
    private function needsReconciliation(CibilCreditRequest $request, CibilErrorCode $code, ?SurepassCibilResult $result = null): void
    {
        $request->update([
            'status' => CibilRequestStatus::ReconciliationRequired,
            'error_code' => $code,
            'error_message' => $code->safeMessage(),
            'retryable' => false,
            'http_status' => $result?->httpStatus,
            'provider_message_code' => $result?->messageCode,
            'completed_at' => now(),
        ]);

        Log::warning('CIBIL request needs reconciliation', [
            'request' => $request->public_id,
            'pan' => $request->pan_masked,
            'error' => $code->value,
            'http_status' => $result?->httpStatus,
        ]);

        $this->alert($request);
    }

    private function alert(CibilCreditRequest $request): void
    {
        $email = CibilSettings::alertEmail();

        if ($email === null) {
            return;
        }

        try {
            Notification::route('mail', $email)->notify(new CibilOperationalAlert($request));
        } catch (Throwable $exception) {
            Log::warning('CIBIL operational alert could not be sent', ['request' => $request->public_id, 'exception' => $exception::class]);
        }
    }

    private function limit(?string $value): ?string
    {
        return $value === null ? null : mb_substr($value, 0, 191);
    }
}
