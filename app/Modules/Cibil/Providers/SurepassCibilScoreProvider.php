<?php

namespace App\Modules\Cibil\Providers;

use App\Modules\Cibil\Actions\RecordWebsiteCibilCheck;
use App\Modules\Cibil\DataTransferObjects\CibilSubject;
use App\Modules\Cibil\Enums\CibilErrorCode;
use App\Modules\Cibil\Enums\CibilProduct;
use App\Modules\Cibil\Enums\Gender;
use App\Modules\Cibil\Services\CibilReportDocument;
use App\Modules\Cibil\Services\SurepassCibilService;
use App\Modules\CreditBureau\DataTransferObjects\CreditCheckResult;
use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\CreditScore\Contracts\CreditScoreProvider;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use Illuminate\Support\Facades\Log;

/**
 * A real CIBIL score for the public "Free CIBIL Score" page, through the CIBIL
 * module's Surepass service — the "cibil" source in CibilSettings::SOURCES,
 * used while CIBIL Settings → "Free CIBIL Score page shows real scores" is on
 * and CIBIL_PUBLIC_SCORE_SOURCE=cibil.
 *
 * The score comes from credit-report-cibil/fetch-report by default
 * (services.surepass.cibil_public_score_endpoint = "report"): FynnEdge's
 * Surepass plan answered credit-report-cibil/score with 401 "not valid for
 * this API/Scope" on 16 Oct 2026.
 *
 * Every paid call is also a CIBIL request (channel "website", through
 * RecordWebsiteCibilCheck): the full report is kept there — encrypted, under
 * the CIBIL permissions, audit and retention — and the call shows in the
 * CIBIL admin. The credit_score_checks row itself keeps only the score,
 * Surepass's client id, the message code, the CIBIL request's id and the
 * report's headline figures (insights).
 */
class SurepassCibilScoreProvider implements CreditScoreProvider
{
    public function __construct(
        private readonly SurepassCibilService $surepass,
        private readonly RecordWebsiteCibilCheck $website,
    ) {}

    public function name(): string
    {
        return 'surepass';
    }

    public function check(CreditScoreCheck $check): CreditCheckResult
    {
        $endpoint = config('services.surepass.cibil_public_score_endpoint') === 'score' ? 'score' : 'report';
        $gender = Gender::tryFrom((string) $check->gender);

        // CIBIL cannot be asked without a gender; the page requires one, so this
        // only guards a caller that skipped it — nothing is sent, nothing charged.
        if ($gender === null) {
            return new CreditCheckResult(
                status: CreditCheckStatus::Failed,
                rawResponse: ['error' => CibilErrorCode::InvalidRequest->value, 'source' => $endpoint],
                failureReason: CibilErrorCode::InvalidRequest->safeMessage(),
            );
        }

        // The visitor's consent — an OTP-verified mobile and the ticked consent
        // box — is recorded with the CIBIL request before anything is sent.
        $recorded = $this->website->start($check, $endpoint === 'score' ? CibilProduct::Score : CibilProduct::Report, $gender);
        $consent = $recorded['consent'] ?? new CreditConsent([
            'status' => ConsentStatus::Given,
            'consented_at' => $check->consent_given_at,
            'purpose' => 'Free CIBIL score check',
        ]);

        $subject = new CibilSubject((string) $check->full_name, $check->pan_number, $check->mobile_number, $gender, $consent);
        $result = $endpoint === 'score' ? $this->surepass->fetchScore($subject) : $this->surepass->fetchReport($subject);
        $requestId = $recorded['request']->public_id ?? null;

        if ($recorded !== null) {
            $this->website->finish($recorded['request'], $result);
        }

        if (! $result->isSuccessful()) {
            Log::warning('Public CIBIL score check failed', [
                'check' => $check->public_id,
                'source' => $endpoint,
                'error' => $result->errorCode?->value,
                'http_status' => $result->httpStatus,
                'message_code' => $result->messageCode,
            ]);

            return new CreditCheckResult(
                status: $result->isUncertain() ? CreditCheckStatus::Pending : CreditCheckStatus::Failed,
                rawResponse: ['error' => $result->errorCode?->value, 'http_status' => $result->httpStatus, 'message_code' => $result->messageCode, 'source' => $endpoint, ...$this->link($requestId)],
                failureReason: $result->errorCode?->safeMessage(),
            );
        }

        $document = new CibilReportDocument($result->body ?? []);

        Log::info('Public CIBIL score check answered', [
            'check' => $check->public_id,
            'source' => $endpoint,
            'score_available' => $document->score() !== null,
            'http_status' => $result->httpStatus,
        ]);

        return new CreditCheckResult(
            status: CreditCheckStatus::Completed,
            score: $document->score(),
            reference: $document->clientId(),
            rawResponse: ['client_id' => $document->clientId(), 'message_code' => $result->messageCode, 'source' => $endpoint, ...$this->link($requestId)],
            insights: $endpoint === 'report' ? $document->insights() : null,
        );
    }

    /**
     * @return array{cibil_request?: string}
     */
    private function link(?string $requestId): array
    {
        return $requestId === null ? [] : ['cibil_request' => $requestId];
    }
}
