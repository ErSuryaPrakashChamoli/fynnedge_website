<?php

namespace App\Models;

use App\Enums\EmploymentType;
use App\Enums\LenderStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Modules\Applications\Models\LenderProductDocumentRequirement;
use App\Modules\Eligibility\Enums\EligibilityRuleSetStatus;
use App\Modules\Eligibility\Models\EligibilityRuleSet;
use Database\Factories\LenderProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'lender_id', 'loan_product_id', 'min_amount', 'max_amount',
    'min_tenure_months', 'max_tenure_months', 'initial_tenure_months', 'interest_rate_from',
    'interest_rate_to', 'processing_fee_note', 'processing_fee_percent_min', 'processing_fee_percent_max',
    'processing_fee_flat_amount_min', 'processing_fee_flat_amount_max', 'processing_fee_gst_extra',
    'min_age', 'max_age', 'min_credit_score', 'min_monthly_income',
    'min_employment_vintage_months', 'employment_types', 'status',
])]
class LenderProduct extends Model
{
    /** @use HasFactory<LenderProductFactory> */
    use Auditable, HasFactory, HasPublicId;

    protected function casts(): array
    {
        return [
            'status' => LenderStatus::class,
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'interest_rate_from' => 'decimal:2',
            'interest_rate_to' => 'decimal:2',
            'processing_fee_percent_min' => 'decimal:2',
            'processing_fee_percent_max' => 'decimal:2',
            'processing_fee_flat_amount_min' => 'decimal:2',
            'processing_fee_flat_amount_max' => 'decimal:2',
            'processing_fee_gst_extra' => 'boolean',
            'min_monthly_income' => 'decimal:2',
            'employment_types' => 'array',
        ];
    }

    /**
     * Human-readable processing fee. Lenders quote this either as a flat
     * rupee amount (itself a range/slab, e.g. "₹500–₹2,000") or as a
     * percentage (often a range/slab rather than one flat percentage, e.g.
     * "1%–3% of loan amount") — a flat amount takes precedence if both are
     * set, since admins are expected to fill in only whichever applies to
     * that lender. Appends "+ GST" when flagged. Falls back to null when no
     * fee figure is set at all, so callers can fall through to
     * processing_fee_note for slab specifics.
     */
    public function processingFeeDisplay(): ?string
    {
        $flatMin = $this->processing_fee_flat_amount_min;
        $flatMax = $this->processing_fee_flat_amount_max;
        $min = $this->processing_fee_percent_min;
        $max = $this->processing_fee_percent_max;

        $amount = match (true) {
            $flatMin !== null && $flatMax !== null && (float) $flatMin === (float) $flatMax => '₹'.number_format((float) $flatMin),
            $flatMin !== null && $flatMax !== null => '₹'.number_format((float) $flatMin).'–₹'.number_format((float) $flatMax),
            $flatMax !== null => 'Up to ₹'.number_format((float) $flatMax),
            $flatMin !== null => 'From ₹'.number_format((float) $flatMin),
            $min !== null && $max !== null && (float) $min === (float) $max => "{$min}%",
            $min !== null && $max !== null => "{$min}%–{$max}%",
            $max !== null => "Up to {$max}%",
            $min !== null => "From {$min}%",
            default => null,
        };

        if ($amount === null) {
            return null;
        }

        return $this->processing_fee_gst_extra ? "{$amount} + GST" : $amount;
    }

    /**
     * Informational "who typically qualifies" bullet points derived from
     * whichever descriptive eligibility fields are set on this offer. These
     * are purely for public display — they do not feed the eligibility rule
     * engine in app/Modules/Eligibility.
     *
     * @return array<int, string>
     */
    public function eligibilitySummaryPoints(): array
    {
        return array_values(array_filter([
            $this->min_age || $this->max_age
                ? 'Age '.($this->min_age ?? '—').'–'.($this->max_age ?? '—').' yrs'
                : null,
            $this->min_monthly_income
                ? 'Min. income ₹'.number_format((float) $this->min_monthly_income).'/mo'
                : null,
            $this->min_credit_score
                ? "Credit score {$this->min_credit_score}+"
                : null,
            $this->min_employment_vintage_months
                ? number_format($this->min_employment_vintage_months).'+ months in current employment/business'
                : null,
            $this->employment_types
                ? collect($this->employment_types)
                    ->map(fn (string $value) => EmploymentType::tryFrom($value)?->getLabel())
                    ->filter()
                    ->implode(' or ')
                : null,
        ]));
    }

    /**
     * This offer's configured initial (interest-only) tenure for a
     * hybrid-structured product, falling back to the product-level default
     * (loan_products.default_initial_tenure_months) when this lender hasn't
     * set its own. Null when neither is configured — callers must treat
     * that as "not enough configuration to run the hybrid calculator for
     * this lender", never assume a value.
     */
    public function effectiveInitialTenureMonths(): ?int
    {
        return $this->initial_tenure_months ?? $this->loanProduct?->default_initial_tenure_months;
    }

    /**
     * The remaining principal+interest stage of a hybrid loan's tenure, given
     * a chosen total tenure. Null if there isn't a configured initial tenure
     * to subtract, or if it would leave nothing (or a negative amount) for
     * the subsequent stage — never silently clamped to a made-up value.
     */
    public function subsequentTenureMonths(int $totalTenureMonths): ?int
    {
        $initial = $this->effectiveInitialTenureMonths();

        if ($initial === null || $totalTenureMonths <= $initial) {
            return null;
        }

        return $totalTenureMonths - $initial;
    }

    public function lender(): BelongsTo
    {
        return $this->belongsTo(Lender::class);
    }

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function eligibilityRuleSets(): HasMany
    {
        return $this->hasMany(EligibilityRuleSet::class);
    }

    public function activeEligibilityRuleSet(): ?EligibilityRuleSet
    {
        return $this->eligibilityRuleSets()
            ->where('status', EligibilityRuleSetStatus::Active)
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', now()))
            ->latest('version')
            ->first();
    }

    public function documentRequirements(): HasMany
    {
        return $this->hasMany(LenderProductDocumentRequirement::class)->orderBy('order');
    }
}
