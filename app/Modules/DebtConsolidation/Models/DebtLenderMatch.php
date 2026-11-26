<?php

namespace App\Modules\DebtConsolidation\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\LenderProduct;
use App\Models\User;
use App\Modules\DebtConsolidation\Enums\AdvisorReviewStatus;
use App\Modules\DebtConsolidation\Enums\LenderMatchStatus;
use Database\Factories\DebtLenderMatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How one case compares with one lender offer's debt consolidation policy:
 * reasons it fits, reasons it does not, what is missing, and the advisor's
 * review. A potential match is never an approval or an offer.
 */
#[Fillable([
    'debt_application_id', 'debt_lender_policy_id', 'lender_product_id', 'debt_calculation_snapshot_id', 'match_status',
    'indicative_loan_amount', 'indicative_rate_min', 'indicative_rate_max', 'reasons', 'missing_information',
    'eligibility_status', 'advisor_status', 'advisor_note', 'reviewed_by', 'reviewed_at', 'evaluated_at',
])]
class DebtLenderMatch extends Model
{
    /** @use HasFactory<DebtLenderMatchFactory> */
    use Auditable, HasFactory, HasPublicId;

    protected static function newFactory(): DebtLenderMatchFactory
    {
        return DebtLenderMatchFactory::new();
    }

    protected function casts(): array
    {
        return [
            'match_status' => LenderMatchStatus::class,
            'advisor_status' => AdvisorReviewStatus::class,
            'indicative_loan_amount' => 'decimal:2',
            'indicative_rate_min' => 'decimal:4',
            'indicative_rate_max' => 'decimal:4',
            'reasons' => 'array',
            'missing_information' => 'array',
            'reviewed_at' => 'datetime',
            'evaluated_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(DebtApplication::class, 'debt_application_id');
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(DebtLenderPolicy::class, 'debt_lender_policy_id')->withTrashed();
    }

    public function lenderProduct(): BelongsTo
    {
        return $this->belongsTo(LenderProduct::class);
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(DebtCalculationSnapshot::class, 'debt_calculation_snapshot_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return list<array{code: string, passed: bool, message: string}>
     */
    public function failedReasons(): array
    {
        return array_values(array_filter($this->reasons ?? [], fn (array $reason): bool => ! $reason['passed']));
    }
}
