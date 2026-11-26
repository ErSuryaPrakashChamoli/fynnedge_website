<?php

namespace App\Modules\DebtConsolidation\Services\Finance;

use App\Modules\DebtConsolidation\DataTransferObjects\Assumptions;
use App\Modules\DebtConsolidation\DataTransferObjects\FlagRaised;
use App\Modules\DebtConsolidation\DataTransferObjects\LiabilityClassification;
use App\Modules\DebtConsolidation\DataTransferObjects\LiabilityInput;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Enums\FinancingRoute;
use App\Modules\DebtConsolidation\Enums\RiskFlag;

/**
 * Separates "recorded" from "consolidatable now". A liability cannot be
 * consolidated now when it is non-performing, settled, written off or
 * disputed; when it is 90+ days past due; when its foreclosure lock-in has
 * not ended; or when no financing route for this applicant type takes over
 * that kind of debt (config debt_consolidation.route_liability_types).
 */
final class LiabilityClassifier
{
    /**
     * @param  array<string, list<string>>  $routeLiabilityTypes  route value => liability type values
     */
    public function classify(LiabilityInput $liability, ApplicantType $applicantType, array $routeLiabilityTypes, Assumptions $assumptions): LiabilityClassification
    {
        $routes = array_values(array_filter(
            FinancingRoute::cases(),
            fn (FinancingRoute $route): bool => in_array($applicantType, $route->applicantTypes(), true)
                && in_array($liability->type->value, $routeLiabilityTypes[$route->value] ?? [], true),
        ));

        $blockers = [];

        if ($liability->accountStatus->blocksConsolidation()) {
            $blockers[] = new FlagRaised(RiskFlag::NotConsolidatable, $liability->key, ['reason' => $liability->accountStatus->value]);
        } elseif ($liability->daysPastDue >= $assumptions->nonPerformingDaysPastDue) {
            $blockers[] = new FlagRaised(RiskFlag::NotConsolidatable, $liability->key, ['reason' => 'days_past_due', 'days' => $liability->daysPastDue]);
        }

        if ($liability->lockInEndsOn !== null && $liability->lockInEndsOn > $assumptions->asOf) {
            $blockers[] = new FlagRaised(RiskFlag::ForeclosureLockIn, $liability->key, ['until' => $liability->lockInEndsOn]);
        }

        if ($routes === []) {
            $blockers[] = new FlagRaised(RiskFlag::NoEligibleRoute, $liability->key, ['type' => $liability->type->value]);
        }

        return new LiabilityClassification($liability->key, $routes, $blockers);
    }
}
