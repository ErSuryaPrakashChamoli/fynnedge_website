<?php

namespace App\Modules\CreditBureau\Actions;

use App\Models\User;
use App\Modules\CreditBureau\DataTransferObjects\CrifSubject;
use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Exceptions\CrifRequestRefused;
use App\Modules\CreditBureau\Models\CrifReport;
use App\Modules\CreditBureau\Services\CrifSettings;
use App\Modules\CreditBureau\Services\SurepassCrifService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * The provider PDF of a successful CRIF report, for authorised download.
 *
 * Surepass's PDF endpoint takes the customer's details again (not a report id)
 * and answers with a temporary link, so asking for it is another provider call.
 * It is therefore made at most once per report: the PDF is downloaded server
 * side, stored ENCRYPTED on the private disk, and every later download is
 * served from that copy. The temporary link itself is never shown to anyone.
 */
class FetchCrifReportPdf
{
    public const DISK = 'local';

    public function __construct(private readonly SurepassCrifService $surepass) {}

    /**
     * @return string The PDF bytes.
     */
    public function handle(CrifReport $report, User $actor): string
    {
        if (! $actor->can('downloadPdf', $report)) {
            throw new AuthorizationException('You are not allowed to download CRIF report PDFs.');
        }

        if (! $report->hasReport()) {
            throw new CrifRequestRefused('Only a successful report whose data is still held has a PDF.');
        }

        if ($report->hasStoredPdf()) {
            return $this->stored($report, $actor);
        }

        try {
            return Cache::lock('crif-pdf:'.$report->id, 120)->block(5, function () use ($report, $actor): string {
                $report->refresh();

                return $report->hasStoredPdf() ? $this->stored($report, $actor) : $this->fetch($report, $actor);
            });
        } catch (LockTimeoutException) {
            throw new CrifRequestRefused('This PDF is being fetched right now. Try again in a minute.');
        }
    }

    private function fetch(CrifReport $report, User $actor): string
    {
        if (! CrifSettings::isAvailable()) {
            throw new CrifRequestRefused('The Surepass integration is switched off or not configured, so the PDF cannot be fetched.');
        }

        if (! $report->isReusable()) {
            throw new CrifRequestRefused('This report is older than the reuse period. Its PDF would no longer match a new pull, so request a new report instead.');
        }

        $consent = $report->consent;

        if ($consent?->status !== ConsentStatus::Given || $consent->consented_at?->lt(now()->subDays(CrifSettings::consentValidityDays()))) {
            throw new CrifRequestRefused('The customer\'s consent is withdrawn or older than '.CrifSettings::consentValidityDays().' days, so the PDF cannot be requested.');
        }

        $result = $this->surepass->fetchReportPdf(new CrifSubject(
            firstName: (string) $report->first_name,
            lastName: (string) $report->last_name,
            mobile: (string) $report->mobile,
            pan: (string) $report->pan_encrypted,
            consent: $consent,
        ));

        $link = $result->isSuccessful() ? $this->surepass->pdfLink($result->data()) : null;
        $bytes = $link !== null ? $this->surepass->downloadPdf($link) : null;

        if ($bytes === null) {
            $code = $result->errorCode?->value ?? 'pdf_download_failed';

            $report->update(['pdf_error_code' => $code]);
            $report->recordEvent('pdf_failed', ['error' => $code], $actor->id);
            Log::warning('CRIF report PDF could not be fetched', ['report' => $report->public_id, 'error' => $code, 'http_status' => $result->httpStatus]);

            throw new CrifRequestRefused($result->errorCode?->safeMessage() ?? 'Surepass returned no usable PDF. Try again later; the report itself is unaffected.');
        }

        $path = 'crif-reports/'.$report->public_id.'.pdf.enc';

        if (! Storage::disk(self::DISK)->put($path, Crypt::encryptString($bytes))) {
            // The employee still gets the PDF they asked for, but the next download
            // would be another provider call — make that visible.
            $report->update(['pdf_error_code' => 'storage_failed']);
            $report->recordEvent('pdf_fetched', ['stored' => 'no'], $actor->id);
            Log::error('CRIF report PDF could not be stored', ['report' => $report->public_id]);

            return $bytes;
        }

        $report->update(['pdf_path' => $path, 'pdf_fetched_at' => now(), 'pdf_error_code' => null]);
        $report->recordEvent('pdf_fetched', [], $actor->id);

        return $bytes;
    }

    private function stored(CrifReport $report, User $actor): string
    {
        $encrypted = Storage::disk(self::DISK)->get((string) $report->pdf_path);

        if ($encrypted === null) {
            throw new CrifRequestRefused('The stored PDF is missing from the server. An administrator should check the private storage disk.');
        }

        $report->recordEvent('pdf_downloaded', [], $actor->id);

        return Crypt::decryptString($encrypted);
    }
}
