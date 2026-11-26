<?php

namespace App\Modules\DebtConsolidation\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\LenderProduct;
use App\Modules\DebtConsolidation\DataTransferObjects\ScenarioInput;
use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Enums\FinancingRoute;
use Database\Factories\DebtScenarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A proposed consolidation loan to compare against keeping the existing
 * debts. Rates and fees here are assumptions until a lender sanctions.
 */
#[Fillable([
    'debt_application_id', 'label', 'financing_route', 'lender_product_id', 'annual_interest_rate', 'tenure_months',
    'processing_fee_percent', 'processing_fee_fixed', 'other_upfront_costs', 'fees_deducted_from_disbursal',
    'closure_costs_financed', 'loan_amount_override', 'liability_overrides', 'is_preferred', 'created_by_type',
    'created_by', 'sort_order',
])]
class DebtScenario extends Model
{
    /** @use HasFactory<DebtScenarioFactory> */
    use Auditable, HasFactory, HasPublicId;

    protected static function newFactory(): DebtScenarioFactory
    {
        return DebtScenarioFactory::new();
    }

    protected function casts(): array
    {
        return [
            'financing_route' => FinancingRoute::class,
            'annual_interest_rate' => 'decimal:4',
            'processing_fee_percent' => 'decimal:4',
            'processing_fee_fixed' => 'decimal:2',
            'other_upfront_costs' => 'decimal:2',
            'fees_deducted_from_disbursal' => 'boolean',
            'closure_costs_financed' => 'boolean',
            'loan_amount_override' => 'decimal:2',
            'liability_overrides' => 'array',
            'is_preferred' => 'boolean',
            'created_by_type' => ActorType::class,
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(DebtApplication::class, 'debt_application_id');
    }

    public function lenderProduct(): BelongsTo
    {
        return $this->belongsTo(LenderProduct::class);
    }

    public function toEngineInput(): ScenarioInput
    {
        return new ScenarioInput(
            key: $this->public_id,
            label: $this->label,
            annualInterestRate: (float) $this->annual_interest_rate,
            tenureMonths: (int) $this->tenure_months,
            processingFeePercent: (float) $this->processing_fee_percent,
            processingFeeFixed: (float) $this->processing_fee_fixed,
            otherUpfrontCosts: (float) $this->other_upfront_costs,
            feesDeductedFromDisbursal: (bool) $this->fees_deducted_from_disbursal,
            closureCostsFinanced: (bool) $this->closure_costs_financed,
            loanAmountOverride: $this->loan_amount_override === null ? null : (float) $this->loan_amount_override,
            route: $this->financing_route,
            liabilityOverrides: $this->liability_overrides ?? [],
        );
    }
}
