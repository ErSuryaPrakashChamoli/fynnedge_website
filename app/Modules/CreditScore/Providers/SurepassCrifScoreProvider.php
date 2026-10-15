<?php

namespace App\Modules\CreditScore\Providers;

use App\Modules\CreditBureau\DataTransferObjects\CreditCheckResult;
use App\Modules\CreditBureau\DataTransferObjects\CrifSubject;
use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\CreditBureau\Services\CrifReportDocument;
use App\Modules\CreditBureau\Services\SurepassCrifService;
use App\Modules\CreditScore\Contracts\CreditScoreProvider;
use App\Modules\CreditScore\Models\CreditScoreCheck;

/**
 * A real CRIF score for the public "Free CRIF Score" page, through Surepass's
 * score endpoint. Used only for the CRIF bureau and only while CRIF Settings →
 * "Free CRIF Score page uses real scores" is on (see SubmitCreditScoreCheck,
 * which also keeps a repeat check by the same person from paying again).
 *
 * Only the score, Surepass's client id and message code are kept — never the
 * response body, which echoes the visitor's PAN, name and mobile. An outcome
 * that may have been charged but gave no answer (timeout, gateway error) is
 * returned as Pending so it is not re-sent straight away.
 */
class SurepassCrifScoreProvider implements CreditScoreProvider
{
    public function __construct(private readonly SurepassCrifService $surepass) {}

    public function name(): string
    {
        return 'surepass';
    }

    public function check(CreditScoreCheck $check): CreditCheckResult
    {
        [$firstName, $lastName] = self::splitName($check->full_name);

        // The public flow's consent record is the check itself: an OTP-verified
        // mobile, the ticked consent box and consent_given_at, all required
        // before this provider is ever reached.
        $consent = new CreditConsent([
            'status' => ConsentStatus::Given,
            'consented_at' => $check->consent_given_at,
            'purpose' => 'Free CRIF score check',
        ]);

        $result = $this->surepass->fetchScore(new CrifSubject($firstName, $lastName, $check->mobile_number, $check->pan_number, $consent));

        if (! $result->isSuccessful()) {
            return new CreditCheckResult(
                status: $result->isUncertain() ? CreditCheckStatus::Pending : CreditCheckStatus::Failed,
                rawResponse: ['error' => $result->errorCode?->value, 'http_status' => $result->httpStatus, 'message_code' => $result->messageCode],
                failureReason: $result->errorCode?->safeMessage(),
            );
        }

        $document = new CrifReportDocument($result->body ?? []);

        return new CreditCheckResult(
            status: CreditCheckStatus::Completed,
            score: $document->score(),
            reference: $document->clientId(),
            rawResponse: ['client_id' => $document->clientId(), 'message_code' => $result->messageCode],
        );
    }

    /**
     * "Asha Kumari Rao" → ["Asha Kumari", "Rao"]. A single word has no last
     * name to send; SubmitCreditScoreCheck's caller asks for both first.
     *
     * @return array{0: string, 1: string}
     */
    public static function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];

        if (count($parts) < 2) {
            return [$parts[0] ?? '', ''];
        }

        $lastName = array_pop($parts);

        return [implode(' ', $parts), $lastName];
    }
}
