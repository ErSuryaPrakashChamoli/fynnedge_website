<?php

namespace App\Modules\Equifax\Actions;

use App\Models\User;
use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\Equifax\DataTransferObjects\EquifaxSubject;
use App\Modules\Equifax\Enums\EquifaxErrorCode;
use App\Modules\Equifax\Enums\Gender;
use App\Modules\Equifax\Exceptions\EquifaxRequestRefused;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Equifax\Services\EquifaxReportDocument;
use App\Modules\Equifax\Services\EquifaxSettings;
use App\Modules\Equifax\Services\SurepassEquifaxService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * The PDF of a successful Equifax full report, for authorised download, in
 * order of cost:
 *
 *  1. A copy already stored (encrypted, private disk) — no call at all.
 *  2. The PDF link Surepass put in the report itself (data.credit_report_link)
 *     — downloaded without the token; such links are temporary, so a dead one
 *     simply falls through.
 *  3. Surepass's fetch-pdf-report endpoint — another provider call with the
 *     customer's details, separately licensed (FynnEdge's plan answered it with
 *     "not valid for this API/Scope" on 16 Oct 2026), made at most once per
 *     report.
 *
 * Whatever is fetched is stored encrypted and served from that copy from then
 * on; the temporary link itself is never shown to anyone.
 */
class FetchEquifaxPdf
{
    public const DISK = 'local';

    public function __construct(private readonly SurepassEquifaxService $surepass) {}

    /**
     * @return string The PDF bytes.
     */
    public function handle(EquifaxCreditRequest $request, User $actor): string
    {
        if (! $actor->can('downloadPdf', $request)) {
            throw new AuthorizationException('You are not allowed to download Equifax report PDFs.');
        }

        if (! $request->hasReport()) {
            throw new EquifaxRequestRefused('Only a successful full report whose data is still held has a PDF.');
        }

        if ($request->hasStoredPdf()) {
            return $this->stored($request, $actor);
        }

        try {
            return Cache::lock('equifax-pdf:'.$request->id, 120)->block(5, function () use ($request, $actor): string {
                $request->refresh();

                return $request->hasStoredPdf() ? $this->stored($request, $actor) : $this->fetch($request, $actor);
            });
        } catch (LockTimeoutException) {
            throw new EquifaxRequestRefused('This PDF is being fetched right now. Try again in a minute.');
        }
    }

    private function fetch(EquifaxCreditRequest $request, User $actor): string
    {
        $link = (new EquifaxReportDocument($request->raw_response ?? []))->pdfLink();
        $bytes = $link !== null ? $this->surepass->downloadPdf($link) : null;

        if ($bytes !== null) {
            return $this->store($request, $actor, $bytes, 'report_link');
        }

        if (! EquifaxSettings::isAvailable()) {
            throw new EquifaxRequestRefused('The Equifax integration is switched off or not configured, so the PDF cannot be requested.');
        }

        if (! $request->isReusable()) {
            throw new EquifaxRequestRefused('This report is older than the reuse period. Its PDF would no longer match a new pull, so request a new report instead.');
        }

        $consent = $request->consent;

        if ($consent?->status !== ConsentStatus::Given || $consent->consented_at?->lt(now()->subDays(EquifaxSettings::consentValidityDays()))) {
            throw new EquifaxRequestRefused('The customer\'s consent is withdrawn or older than '.EquifaxSettings::consentValidityDays().' days, so the PDF cannot be requested.');
        }

        $result = $this->surepass->fetchPdfReport(new EquifaxSubject(
            name: (string) $request->full_name,
            pan: (string) $request->pan_encrypted,
            mobile: (string) $request->mobile,
            gender: $request->gender ?? Gender::Male,
            consent: $consent,
        ));

        $link = $result->isSuccessful() ? $this->surepass->pdfLink($result->data()) : null;
        $bytes = $link !== null ? $this->surepass->downloadPdf($link) : null;

        if ($bytes === null) {
            $code = $result->errorCode ?? EquifaxErrorCode::MissingPdfLink;

            $request->update(['pdf_error_code' => $code->value]);
            $request->recordEvent('pdf_failed', ['error' => $code->value], $actor->id);
            Log::warning('Equifax report PDF could not be fetched', ['request' => $request->public_id, 'error' => $code->value, 'http_status' => $result->httpStatus]);

            throw new EquifaxRequestRefused($code->safeMessage());
        }

        return $this->store($request, $actor, $bytes, 'surepass');
    }

    private function store(EquifaxCreditRequest $request, User $actor, string $bytes, string $source): string
    {
        $path = 'equifax-reports/'.$request->public_id.'.pdf.enc';

        if (! Storage::disk(self::DISK)->put($path, Crypt::encryptString($bytes))) {
            $request->update(['pdf_error_code' => 'storage_failed']);
            $request->recordEvent('pdf_fetched', ['stored' => 'no', 'source' => $source], $actor->id);
            Log::error('Equifax report PDF could not be stored', ['request' => $request->public_id]);

            return $bytes;
        }

        $request->update(['pdf_path' => $path, 'pdf_fetched_at' => now(), 'pdf_error_code' => null]);
        $request->recordEvent('pdf_fetched', ['source' => $source], $actor->id);

        return $bytes;
    }

    private function stored(EquifaxCreditRequest $request, User $actor): string
    {
        $encrypted = Storage::disk(self::DISK)->get((string) $request->pdf_path);

        if ($encrypted === null) {
            throw new EquifaxRequestRefused('The stored PDF is missing from the server. An administrator should check the private storage disk.');
        }

        $request->recordEvent('pdf_downloaded', [], $actor->id);

        return Crypt::decryptString($encrypted);
    }
}
