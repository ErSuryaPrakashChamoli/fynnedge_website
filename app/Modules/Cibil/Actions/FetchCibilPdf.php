<?php

namespace App\Modules\Cibil\Actions;

use App\Models\User;
use App\Modules\Cibil\Enums\CibilErrorCode;
use App\Modules\Cibil\Exceptions\CibilRequestRefused;
use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\Cibil\Services\CibilReportDocument;
use App\Modules\Cibil\Services\SurepassCibilService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Delivers the PDF of a successful CIBIL PDF request. It NEVER calls a paid
 * Surepass endpoint — a PDF is bought only through RequestCibilCheck
 * (product "pdf"), with its consent, duplicate and reuse rules:
 *
 *  1. A copy already stored (encrypted, private disk) — no call at all.
 *  2. Otherwise the temporary link Surepass returned for that request
 *     (data.credit_report_link), downloaded WITHOUT the bearer token, stored
 *     encrypted and served from that copy from then on. ProcessCibilRequest
 *     does this as soon as the PDF arrives — or stores the inline Base64 PDF
 *     (data.credit_report_base64) directly; this is the second chance.
 *
 * The link itself is never shown, returned or logged.
 */
class FetchCibilPdf
{
    public const DISK = 'local';

    public function __construct(private readonly SurepassCibilService $surepass) {}

    /**
     * @return string The PDF bytes.
     */
    public function handle(CibilCreditRequest $request, User $actor): string
    {
        if (! $actor->can('downloadPdf', $request)) {
            throw new AuthorizationException('You are not allowed to download CIBIL report PDFs.');
        }

        if ($request->hasStoredPdf()) {
            return $this->stored($request, $actor);
        }

        if (! $request->isPdfResult()) {
            throw new CibilRequestRefused('This CIBIL record has no PDF. Request a CIBIL PDF for the customer — a separate paid request, answered from a recent PDF when one exists.');
        }

        try {
            return Cache::lock('cibil-pdf:'.$request->id, 120)->block(5, function () use ($request, $actor): string {
                $request->refresh();

                if ($request->hasStoredPdf()) {
                    return $this->stored($request, $actor);
                }

                return $this->storeFromLink($request, $actor)
                    ?? throw new CibilRequestRefused(CibilErrorCode::PdfUnavailable->safeMessage());
            });
        } catch (LockTimeoutException) {
            throw new CibilRequestRefused('This PDF is being downloaded right now. Try again in a minute.');
        }
    }

    /**
     * Whether handle() can produce a PDF for this record without a new paid request.
     */
    public function isAvailable(CibilCreditRequest $request): bool
    {
        return $request->hasStoredPdf()
            || ($request->isPdfResult() && (new CibilReportDocument($request->raw_response ?? []))->pdfLink() !== null);
    }

    /**
     * Downloads the PDF behind the link in a successful PDF request and keeps
     * an encrypted copy. Returns the bytes, or null when the link is missing,
     * expired or not a PDF (recorded as pdf_error_code, never retried as a
     * provider call).
     */
    public function storeFromLink(CibilCreditRequest $request, ?User $actor = null): ?string
    {
        $link = (new CibilReportDocument($request->raw_response ?? []))->pdfLink();
        $bytes = $link !== null ? $this->surepass->downloadPdf($link) : null;

        if ($bytes === null) {
            $request->update(['pdf_error_code' => CibilErrorCode::PdfUnavailable->value]);
            $request->recordEvent('pdf_failed', ['error' => CibilErrorCode::PdfUnavailable->value], $actor?->id);
            Log::warning('CIBIL report PDF could not be downloaded', ['request' => $request->public_id, 'link_present' => $link !== null]);

            return null;
        }

        return $this->store($request, $bytes, 'report_link', $actor);
    }

    /**
     * Keeps PDF bytes as an encrypted file on the private disk and records it.
     * Returns the bytes either way, so a storage failure never loses the PDF
     * the caller is about to serve.
     */
    public function store(CibilCreditRequest $request, string $bytes, string $source, ?User $actor = null): string
    {
        $path = 'cibil-reports/'.$request->public_id.'.pdf.enc';

        if (! Storage::disk(self::DISK)->put($path, Crypt::encryptString($bytes))) {
            $request->update(['pdf_error_code' => 'storage_failed']);
            $request->recordEvent('pdf_fetched', ['stored' => 'no'], $actor?->id);
            Log::error('CIBIL report PDF could not be stored', ['request' => $request->public_id]);

            return $bytes;
        }

        $request->update(['pdf_path' => $path, 'pdf_fetched_at' => now(), 'pdf_error_code' => null]);
        $request->recordEvent('pdf_fetched', ['source' => $source], $actor?->id);

        return $bytes;
    }

    private function stored(CibilCreditRequest $request, User $actor): string
    {
        $encrypted = Storage::disk(self::DISK)->get((string) $request->pdf_path);

        if ($encrypted === null) {
            throw new CibilRequestRefused('The stored PDF is missing from the server. An administrator should check the private storage disk.');
        }

        $request->recordEvent('pdf_downloaded', [], $actor->id);

        return Crypt::decryptString($encrypted);
    }
}
