<?php

namespace App\Modules\Experian\Actions;

use App\Models\User;
use App\Modules\Experian\Enums\ExperianErrorCode;
use App\Modules\Experian\Exceptions\ExperianRequestRefused;
use App\Modules\Experian\Models\ExperianCreditRequest;
use App\Modules\Experian\Services\ExperianReportDocument;
use App\Modules\Experian\Services\SurepassExperianService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Delivers the PDF of a successful Experian PDF request. It NEVER calls a paid
 * Surepass endpoint — a PDF is bought only through RequestExperianCheck
 * (product "pdf"), with its consent, duplicate and reuse rules:
 *
 *  1. A copy already stored (encrypted, private disk) — no call at all.
 *  2. Otherwise the temporary link Surepass returned for that request
 *     (data.credit_report_link), downloaded WITHOUT the bearer token, stored
 *     encrypted and served from that copy from then on. ProcessExperianRequest
 *     does this as soon as the PDF arrives; this is the second chance.
 *
 * The link itself is never shown, returned or logged.
 */
class FetchExperianPdf
{
    public const DISK = 'local';

    public function __construct(private readonly SurepassExperianService $surepass) {}

    /**
     * @return string The PDF bytes.
     */
    public function handle(ExperianCreditRequest $request, User $actor): string
    {
        if (! $actor->can('downloadPdf', $request)) {
            throw new AuthorizationException('You are not allowed to download Experian report PDFs.');
        }

        if ($request->hasStoredPdf()) {
            return $this->stored($request, $actor);
        }

        if (! $request->isPdfResult()) {
            throw new ExperianRequestRefused('This Experian record has no PDF. Request an Experian PDF for the customer — a separate paid request, answered from a recent PDF when one exists.');
        }

        try {
            return Cache::lock('experian-pdf:'.$request->id, 120)->block(5, function () use ($request, $actor): string {
                $request->refresh();

                if ($request->hasStoredPdf()) {
                    return $this->stored($request, $actor);
                }

                return $this->storeFromLink($request, $actor)
                    ?? throw new ExperianRequestRefused(ExperianErrorCode::PdfUnavailable->safeMessage());
            });
        } catch (LockTimeoutException) {
            throw new ExperianRequestRefused('This PDF is being downloaded right now. Try again in a minute.');
        }
    }

    /**
     * Whether handle() can produce a PDF for this record without a new paid request.
     */
    public function isAvailable(ExperianCreditRequest $request): bool
    {
        return $request->hasStoredPdf()
            || ($request->isPdfResult() && (new ExperianReportDocument($request->raw_response ?? []))->pdfLink() !== null);
    }

    /**
     * Downloads the PDF behind the link in a successful PDF request and keeps
     * an encrypted copy. Returns the bytes, or null when the link is missing,
     * expired or not a PDF (recorded as pdf_error_code, never retried as a
     * provider call).
     */
    public function storeFromLink(ExperianCreditRequest $request, ?User $actor = null): ?string
    {
        $link = (new ExperianReportDocument($request->raw_response ?? []))->pdfLink();
        $bytes = $link !== null ? $this->surepass->downloadPdf($link) : null;

        if ($bytes === null) {
            $request->update(['pdf_error_code' => ExperianErrorCode::PdfUnavailable->value]);
            $request->recordEvent('pdf_failed', ['error' => ExperianErrorCode::PdfUnavailable->value], $actor?->id);
            Log::warning('Experian report PDF could not be downloaded', ['request' => $request->public_id, 'link_present' => $link !== null]);

            return null;
        }

        $path = 'experian-reports/'.$request->public_id.'.pdf.enc';

        if (! Storage::disk(self::DISK)->put($path, Crypt::encryptString($bytes))) {
            $request->update(['pdf_error_code' => 'storage_failed']);
            $request->recordEvent('pdf_fetched', ['stored' => 'no'], $actor?->id);
            Log::error('Experian report PDF could not be stored', ['request' => $request->public_id]);

            return $bytes;
        }

        $request->update(['pdf_path' => $path, 'pdf_fetched_at' => now(), 'pdf_error_code' => null]);
        $request->recordEvent('pdf_fetched', ['source' => 'report_link'], $actor?->id);

        return $bytes;
    }

    private function stored(ExperianCreditRequest $request, User $actor): string
    {
        $encrypted = Storage::disk(self::DISK)->get((string) $request->pdf_path);

        if ($encrypted === null) {
            throw new ExperianRequestRefused('The stored PDF is missing from the server. An administrator should check the private storage disk.');
        }

        $request->recordEvent('pdf_downloaded', [], $actor->id);

        return Crypt::decryptString($encrypted);
    }
}
