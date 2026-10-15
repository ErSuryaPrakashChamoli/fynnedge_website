<?php

namespace App\Modules\CreditBureau\Actions;

use App\Modules\CreditBureau\DataTransferObjects\CrifSubject;
use App\Modules\CreditBureau\DataTransferObjects\SurepassCrifResult;
use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Enums\CrifErrorCode;
use App\Modules\CreditBureau\Enums\CrifReportStatus;
use App\Modules\CreditBureau\Models\CrifReport;
use App\Modules\CreditBureau\Notifications\CrifOperationalAlert;
use App\Modules\CreditBureau\Services\CrifReportDocument;
use App\Modules\CreditBureau\Services\CrifSettings;
use App\Modules\CreditBureau\Services\SurepassCrifService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Sends ONE pending CRIF request to Surepass and records what came back.
 *
 * The pending → processing step is a conditional UPDATE, so however many times
 * the job is delivered (worker restart, duplicate dispatch, a stale-request
 * sweep racing it) only one execution ever makes the paid call for a row.
 */
class ProcessCrifReport
{
    public function __construct(private readonly SurepassCrifService $surepass) {}

    public function handle(int $reportId): void
    {
        $claimed = CrifReport::query()
            ->whereKey($reportId)
            ->where('status', CrifReportStatus::Pending)
            ->update(['status' => CrifReportStatus::Processing, 'processing_started_at' => now(), 'updated_at' => now()]);

        if ($claimed !== 1) {
            return;
        }

        $report = CrifReport::query()->with('consent')->findOrFail($reportId);
        $report->recordEvent('processing_started');

        if (! CrifSettings::isAvailable()) {
            $this->fail($report, CrifErrorCode::NotConfigured);

            return;
        }

        if ($report->consent?->status !== ConsentStatus::Given) {
            $this->fail($report, CrifErrorCode::ConsentInvalid);

            return;
        }

        $result = $this->surepass->fetchReport(new CrifSubject(
            firstName: $report->givenNames(),
            lastName: (string) $report->last_name,
            mobile: (string) $report->mobile,
            pan: (string) $report->pan_encrypted,
            consent: $report->consent,
        ));

        match (true) {
            $result->isSuccessful() => $this->succeed($report, $result),
            $result->isUncertain() => $this->needsReconciliation($report, $result->errorCode, $result),
            default => $this->fail($report, $result->errorCode, $result),
        };
    }

    /**
     * The job died (worker killed, timeout, unexpected exception). A row it had
     * not yet claimed was never sent; one it had claimed may have been.
     */
    public static function markInterrupted(int $reportId): void
    {
        $report = CrifReport::query()->find($reportId);

        if ($report) {
            app(self::class)->interrupt($report);
        }
    }

    /**
     * Resolves a request that stopped moving — from the job's failed() hook or
     * the `crif:flag-stale` sweep. Pending was never sent, so it fails as
     * retryable; Processing may have been sent, so it awaits reconciliation and
     * keeps the PAN locked. The UPDATE is conditional on the status it read, so
     * a job that claims the row at the same moment wins and nothing is lost.
     */
    public function interrupt(CrifReport $report): bool
    {
        [$from, $to, $code] = match ($report->status) {
            CrifReportStatus::Pending => [CrifReportStatus::Pending, CrifReportStatus::Failed, CrifErrorCode::QueueStalled],
            CrifReportStatus::Processing => [CrifReportStatus::Processing, CrifReportStatus::ReconciliationRequired, CrifErrorCode::ProcessingStalled],
            default => [null, null, null],
        };

        if ($from === null) {
            return false;
        }

        $changed = CrifReport::query()
            ->whereKey($report->id)
            ->where('status', $from)
            ->update([
                'status' => $to,
                ...($to === CrifReportStatus::Failed ? ['in_flight_key' => null] : []),
                'error_code' => $code,
                'error_message' => $code->safeMessage(),
                'retryable' => $code->isRetryable(),
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

        if ($changed !== 1) {
            return false;
        }

        $report->refresh();
        $report->recordEvent('interrupted', ['status' => $to->value, 'error' => $code->value]);
        Log::warning('CRIF report request interrupted', ['report' => $report->public_id, 'pan' => $report->pan_masked, 'error' => $code->value]);
        $this->alert($report);

        return true;
    }

    private function succeed(CrifReport $report, SurepassCrifResult $result): void
    {
        try {
            $document = new CrifReportDocument($result->body ?? []);
            $header = $document->header();

            $report->update([
                'status' => CrifReportStatus::Success,
                'in_flight_key' => null,
                'score' => $document->score(),
                'http_status' => $result->httpStatus,
                'provider_message_code' => $result->messageCode,
                'provider_client_id' => $this->limit($document->clientId()),
                'provider_report_id' => $this->limit($header['report_id']),
                'provider_inquiry_id' => $this->limit($header['inquiry_id']),
                'report_requested_on' => $document->requestedOn(),
                'report_issued_on' => $document->issuedOn(),
                'raw_response' => $result->body,
                'error_code' => null,
                'error_message' => null,
                'retryable' => false,
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            // The report was paid for but not saved — never retry it blindly.
            Log::error('CRIF report could not be saved', ['report' => $report->public_id, 'exception' => $exception::class]);
            $this->needsReconciliation($report->refresh(), CrifErrorCode::PersistenceFailed, $result);

            return;
        }

        Log::info('CRIF report fetched', ['report' => $report->public_id, 'pan' => $report->pan_masked, 'http_status' => $result->httpStatus]);
    }

    private function fail(CrifReport $report, CrifErrorCode $code, ?SurepassCrifResult $result = null): void
    {
        $report->update([
            'status' => CrifReportStatus::Failed,
            'in_flight_key' => null,
            'error_code' => $code,
            'error_message' => $code->safeMessage(),
            'retryable' => $code->isRetryable(),
            'http_status' => $result?->httpStatus,
            'provider_message_code' => $result?->messageCode,
            'completed_at' => now(),
        ]);

        Log::warning('CRIF report request failed', [
            'report' => $report->public_id,
            'pan' => $report->pan_masked,
            'error' => $code->value,
            'http_status' => $result?->httpStatus,
            'message_code' => $result?->messageCode,
        ]);

        if (in_array($code, [CrifErrorCode::AuthenticationFailed, CrifErrorCode::InsufficientBalance, CrifErrorCode::NotConfigured], true)) {
            $this->alert($report);
        }
    }

    /**
     * Keeps the PAN's in-flight lock: nothing more can be sent for this PAN
     * until an admin reconciles the attempt against the Surepass dashboard.
     */
    private function needsReconciliation(CrifReport $report, CrifErrorCode $code, ?SurepassCrifResult $result = null): void
    {
        $report->update([
            'status' => CrifReportStatus::ReconciliationRequired,
            'error_code' => $code,
            'error_message' => $code->safeMessage(),
            'retryable' => false,
            'http_status' => $result?->httpStatus,
            'provider_message_code' => $result?->messageCode,
            'completed_at' => now(),
        ]);

        Log::warning('CRIF report needs reconciliation', [
            'report' => $report->public_id,
            'pan' => $report->pan_masked,
            'error' => $code->value,
            'http_status' => $result?->httpStatus,
        ]);

        $this->alert($report);
    }

    private function alert(CrifReport $report): void
    {
        $email = CrifSettings::alertEmail();

        if ($email === null) {
            return;
        }

        try {
            Notification::route('mail', $email)->notify(new CrifOperationalAlert($report));
        } catch (Throwable $exception) {
            Log::warning('CRIF operational alert could not be sent', ['report' => $report->public_id, 'exception' => $exception::class]);
        }
    }

    private function limit(?string $value): ?string
    {
        return $value === null ? null : mb_substr($value, 0, 191);
    }
}
