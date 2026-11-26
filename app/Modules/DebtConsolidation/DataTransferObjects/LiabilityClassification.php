<?php

namespace App\Modules\DebtConsolidation\DataTransferObjects;

use App\Modules\DebtConsolidation\Enums\FinancingRoute;

/**
 * Whether one liability can be consolidated now, and through which routes.
 * Every liability is assessable (it counts in the portfolio and in
 * affordability); being on a route list only means that kind of loan can
 * take it over in principle — each lender's policy still decides.
 */
final readonly class LiabilityClassification
{
    /**
     * @param  list<FinancingRoute>  $eligibleRoutes
     * @param  list<FlagRaised>  $blockers
     */
    public function __construct(
        public string $liabilityKey,
        public array $eligibleRoutes,
        public array $blockers,
    ) {}

    public function consolidatableNow(): bool
    {
        return $this->blockers === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'liability_key' => $this->liabilityKey,
            'consolidatable_now' => $this->consolidatableNow(),
            'eligible_routes' => array_map(fn (FinancingRoute $route): string => $route->value, $this->eligibleRoutes),
            'blockers' => array_map(fn (FlagRaised $flag): array => $flag->toArray(), $this->blockers),
        ];
    }
}
