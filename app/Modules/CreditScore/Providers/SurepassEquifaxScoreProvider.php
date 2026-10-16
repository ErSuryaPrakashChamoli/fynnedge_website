<?php

namespace App\Modules\CreditScore\Providers;

use App\Modules\CreditBureau\DataTransferObjects\CreditCheckResult;
use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\CreditScore\Contracts\CreditScoreProvider;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\Equifax\Actions\RecordWebsiteEquifaxCheck;
use App\Modules\Equifax\DataTransferObjects\EquifaxSubject;
use App\Modules\Equifax\Enums\EquifaxErrorCode;
use App\Modules\Equifax\Enums\EquifaxProduct;
use App\Modules\Equifax\Enums\Gender;
use App\Modules\Equifax\Services\EquifaxReportDocument;
use App\Modules\Equifax\Services\SurepassEquifaxService;
use Illuminate\Support\Facades\Log;

/**
 * A real Equifax score for the public "Free Equifax Score" page, through the
 * Equifax module's Surepass service. Used only for the Equifax bureau and only
 * while Equifax Settings → "Free Equifax Score page uses real scores" is on
 * (SubmitCreditScoreCheck routes to it and keeps a repeat check by the same
 * person from paying again).
 *
 * The score comes from credit-report-v2/fetch-report by default
 * (services.surepass.equifax_public_score_source = "report"): FynnEdge's
 * Surepass plan answered credit-report-v2/score with 401 "not valid for this
 * API/Scope" on 16 Oct 2026.
 *
 * Every paid call is also an Equifax request (channel "website", through
 * RecordWebsiteEquifaxCheck): the full report is kept there — encrypted, under
 * the Equifax permissions, audit and retention — and the call shows in the
 * Equifax admin. The credit_score_checks row itself keeps only the score,
 * Surepass's client id, the message code and the Equifax request's id.
 */
class SurepassEquifaxScoreProvider implements CreditScoreProvider
{
    public function __construct(
        private readonly SurepassEquifaxService $surepass,
        private readonly RecordWebsiteEquifaxCheck $website,
    ) {}

    public function name(): string
    {
        return 'surepass';
    }

    public function check(CreditScoreCheck $check): CreditCheckResult
    {
        $source = config('services.surepass.equifax_public_score_source') === 'score' ? 'score' : 'report';
        $gender = Gender::tryFrom((string) $check->gender);

        // Equifax cannot be asked without a gender; the page requires one, so this
        // only guards a caller that skipped it — nothing is sent, nothing charged.
        if ($gender === null) {
            return new CreditCheckResult(
                status: CreditCheckStatus::Failed,
                rawResponse: ['error' => EquifaxErrorCode::InvalidRequest->value, 'source' => $source],
                failureReason: EquifaxErrorCode::InvalidRequest->safeMessage(),
            );
        }

        // The visitor's consent — an OTP-verified mobile and the ticked consent
        // box — is recorded with the Equifax request before anything is sent.
        $recorded = $this->website->start($check, $source === 'score' ? EquifaxProduct::Score : EquifaxProduct::Report, $gender);
        $consent = $recorded['consent'] ?? new CreditConsent([
            'status' => ConsentStatus::Given,
            'consented_at' => $check->consent_given_at,
            'purpose' => 'Free Equifax score check',
        ]);

        $subject = new EquifaxSubject((string) $check->full_name, $check->pan_number, $check->mobile_number, $gender, $consent);
        $result = $source === 'score' ? $this->surepass->fetchScore($subject) : $this->surepass->fetchReport($subject);
        $requestId = $recorded['request']->public_id ?? null;

        if ($recorded !== null) {
            $this->website->finish($recorded['request'], $result);
        }

        if (! $result->isSuccessful()) {
            Log::warning('Public Equifax score check failed', [
                'check' => $check->public_id,
                'source' => $source,
                'error' => $result->errorCode?->value,
                'http_status' => $result->httpStatus,
                'message_code' => $result->messageCode,
            ]);

            return new CreditCheckResult(
                status: $result->isUncertain() ? CreditCheckStatus::Pending : CreditCheckStatus::Failed,
                rawResponse: ['error' => $result->errorCode?->value, 'http_status' => $result->httpStatus, 'message_code' => $result->messageCode, 'source' => $source, ...$this->link($requestId)],
                failureReason: $result->errorCode?->safeMessage(),
            );
        }

        $document = new EquifaxReportDocument($result->body ?? []);

        Log::info('Public Equifax score check answered', [
            'check' => $check->public_id,
            'source' => $source,
            'score_available' => $document->score() !== null,
            'http_status' => $result->httpStatus,
        ]);

        return new CreditCheckResult(
            status: CreditCheckStatus::Completed,
            score: $document->score(),
            reference: $document->clientId(),
            rawResponse: ['client_id' => $document->clientId(), 'message_code' => $result->messageCode, 'source' => $source, ...$this->link($requestId)],
            insights: $document->insights(),
        );
    }

    /**
     * @return array{equifax_request?: string}
     */
    private function link(?string $requestId): array
    {
        return $requestId === null ? [] : ['equifax_request' => $requestId];
    }
}
