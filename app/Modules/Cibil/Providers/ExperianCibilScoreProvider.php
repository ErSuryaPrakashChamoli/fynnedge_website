<?php

namespace App\Modules\Cibil\Providers;

use App\Modules\CreditBureau\DataTransferObjects\CreditCheckResult;
use App\Modules\CreditScore\Contracts\CreditScoreProvider;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\CreditScore\Providers\SurepassExperianScoreProvider;

/**
 * The public CIBIL page's Experian source, used while CIBIL Settings → "Real
 * CIBIL score enabled" and "Fetch Experian instead of CIBIL" are both on: the
 * visitor's real EXPERIAN score, through the Experian
 * module's public-score provider (same Surepass call, same rules). Every check
 * it answers is tagged `source_bureau: experian`, so admins and the page always
 * know — and say — that the score is Experian's, never a CIBIL score. The
 * CIBIL bureau source is SurepassCibilScoreProvider.
 */
class ExperianCibilScoreProvider implements CreditScoreProvider
{
    public const SOURCE_BUREAU = 'experian';

    public function __construct(private readonly SurepassExperianScoreProvider $experian) {}

    public function name(): string
    {
        return $this->experian->name();
    }

    public function check(CreditScoreCheck $check): CreditCheckResult
    {
        $result = $this->experian->check($check);

        return new CreditCheckResult(
            status: $result->status,
            score: $result->score,
            reference: $result->reference,
            rawResponse: [...($result->rawResponse ?? []), 'source_bureau' => self::SOURCE_BUREAU],
            failureReason: $result->failureReason,
            insights: $result->insights,
        );
    }
}
