<?php

namespace App\Modules\CreditScore\Providers;

use App\Modules\CreditBureau\DataTransferObjects\CreditCheckResult;
use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\CreditScore\Contracts\CreditScoreProvider;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\Experian\DataTransferObjects\ExperianSubject;
use App\Modules\Experian\Services\ExperianReportDocument;
use App\Modules\Experian\Services\SurepassExperianService;
use Illuminate\Support\Facades\Log;

/**
 * A real Experian score for the public "Free Experian Score" page, through the
 * Experian module's Surepass service. Used only for the Experian bureau and
 * only while Experian Settings → "Free Experian Score page uses real scores" is
 * on (SubmitCreditScoreCheck routes to it and keeps a repeat check by the same
 * person from paying again).
 *
 * The score comes from credit-report-experian/fetch-report by default
 * (services.surepass.experian_public_score_source = "report"): FynnEdge's
 * Surepass plan answered credit-report-experian/score with 401 "not valid for
 * this API/Scope" on 16 Oct 2026. Only the score, Surepass's client id and
 * message code are kept — never the response, which holds the whole report and
 * echoes the visitor's PAN, name and mobile.
 */
class SurepassExperianScoreProvider implements CreditScoreProvider
{
    public function __construct(private readonly SurepassExperianService $surepass) {}

    public function name(): string
    {
        return 'surepass';
    }

    public function check(CreditScoreCheck $check): CreditCheckResult
    {
        $source = config('services.surepass.experian_public_score_source') === 'score' ? 'score' : 'report';

        // The public flow's consent record is the check itself: an OTP-verified
        // mobile, the ticked consent box and consent_given_at.
        $consent = new CreditConsent([
            'status' => ConsentStatus::Given,
            'consented_at' => $check->consent_given_at,
            'purpose' => 'Free Experian score check',
        ]);

        $subject = new ExperianSubject((string) $check->full_name, $check->pan_number, $check->mobile_number, $consent);
        $result = $source === 'score' ? $this->surepass->fetchScore($subject) : $this->surepass->fetchReport($subject);

        if (! $result->isSuccessful()) {
            Log::warning('Public Experian score check failed', [
                'check' => $check->public_id,
                'source' => $source,
                'error' => $result->errorCode?->value,
                'http_status' => $result->httpStatus,
                'message_code' => $result->messageCode,
            ]);

            return new CreditCheckResult(
                status: $result->isUncertain() ? CreditCheckStatus::Pending : CreditCheckStatus::Failed,
                rawResponse: ['error' => $result->errorCode?->value, 'http_status' => $result->httpStatus, 'message_code' => $result->messageCode, 'source' => $source],
                failureReason: $result->errorCode?->safeMessage(),
            );
        }

        $document = new ExperianReportDocument($result->body ?? []);

        Log::info('Public Experian score check answered', [
            'check' => $check->public_id,
            'source' => $source,
            'score_available' => $document->score() !== null,
            'http_status' => $result->httpStatus,
        ]);

        return new CreditCheckResult(
            status: CreditCheckStatus::Completed,
            score: $document->score(),
            reference: $document->clientId(),
            rawResponse: ['client_id' => $document->clientId(), 'message_code' => $result->messageCode, 'source' => $source],
        );
    }
}
