<?php

namespace App\Modules\DebtConsolidation\Models;

use App\Enums\EmploymentType;
use App\Models\Concerns\Auditable;
use App\Modules\DebtConsolidation\DataTransferObjects\BusinessFinances;
use App\Modules\DebtConsolidation\DataTransferObjects\IndividualFinances;
use App\Modules\DebtConsolidation\Enums\BusinessConstitution;
use Database\Factories\DebtFinancialProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Income and employment (individuals) or cash flow and business details
 * (businesses) for one case. All figures are monthly unless named annual.
 */
#[Fillable([
    'debt_application_id', 'employment_type', 'employer_name', 'occupation', 'date_of_birth', 'net_monthly_income',
    'other_monthly_income', 'monthly_living_expenses', 'other_monthly_obligations', 'residence_type', 'declared_credit_score',
    'business_name', 'business_constitution', 'industry', 'business_vintage_months', 'annual_turnover', 'average_monthly_revenue',
    'monthly_operating_expenses', 'annual_net_profit', 'gst_registered', 'gstin', 'udyam_registered',
])]
class DebtFinancialProfile extends Model
{
    /** @use HasFactory<DebtFinancialProfileFactory> */
    use Auditable, HasFactory;

    protected static function newFactory(): DebtFinancialProfileFactory
    {
        return DebtFinancialProfileFactory::new();
    }

    protected function casts(): array
    {
        return [
            'employment_type' => EmploymentType::class,
            'business_constitution' => BusinessConstitution::class,
            'date_of_birth' => 'date',
            'net_monthly_income' => 'decimal:2',
            'other_monthly_income' => 'decimal:2',
            'monthly_living_expenses' => 'decimal:2',
            'other_monthly_obligations' => 'decimal:2',
            'annual_turnover' => 'decimal:2',
            'average_monthly_revenue' => 'decimal:2',
            'monthly_operating_expenses' => 'decimal:2',
            'annual_net_profit' => 'decimal:2',
            'gst_registered' => 'boolean',
            'udyam_registered' => 'boolean',
        ];
    }

    /**
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return ['date_of_birth', 'gstin', 'employer_name'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(DebtApplication::class, 'debt_application_id');
    }

    public function toIndividualFinances(): IndividualFinances
    {
        return new IndividualFinances(
            netMonthlyIncome: $this->net_monthly_income === null ? null : (float) $this->net_monthly_income,
            otherMonthlyIncome: (float) $this->other_monthly_income,
            monthlyLivingExpenses: $this->monthly_living_expenses === null ? null : (float) $this->monthly_living_expenses,
            otherMonthlyObligations: (float) $this->other_monthly_obligations,
        );
    }

    public function toBusinessFinances(): BusinessFinances
    {
        return new BusinessFinances(
            averageMonthlyRevenue: $this->average_monthly_revenue === null ? null : (float) $this->average_monthly_revenue,
            monthlyOperatingExpenses: $this->monthly_operating_expenses === null ? null : (float) $this->monthly_operating_expenses,
            businessVintageMonths: $this->business_vintage_months,
            annualTurnover: $this->annual_turnover === null ? null : (float) $this->annual_turnover,
            otherMonthlyObligations: (float) $this->other_monthly_obligations,
        );
    }

    public function ageInYears(): ?int
    {
        return $this->date_of_birth?->age;
    }
}
