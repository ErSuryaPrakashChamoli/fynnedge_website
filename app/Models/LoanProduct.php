<?php

namespace App\Models;

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\Publishable;
use App\Models\Concerns\Seoable;
use App\Modules\Journey\Models\JourneyDefinition;
use Database\Factories\LoanProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'name', 'slug', 'category', 'summary', 'body', 'features',
    'eligibility_points', 'documents_required', 'process_steps',
    'calculator_key', 'status', 'published_at', 'expires_at',
    'min_amount', 'max_amount', 'min_tenure_months', 'max_tenure_months',
    'min_interest_rate', 'max_interest_rate', 'interest_rate_note',
    'calculator_explanation',
    'default_amount', 'default_tenure_months', 'default_interest_rate',
    'default_initial_tenure_months',
    'marketing_headline', 'benefits', 'image_path', 'image_alt', 'cta_label',
])]
class LoanProduct extends Model
{
    /** @use HasFactory<LoanProductFactory> */
    use Auditable, HasFactory, HasPublicId, Publishable, Seoable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'category' => LoanCategory::class,
            'status' => PublishStatus::class,
            'features' => 'array',
            'eligibility_points' => 'array',
            'documents_required' => 'array',
            'process_steps' => 'array',
            'benefits' => 'array',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'min_interest_rate' => 'decimal:2',
            'max_interest_rate' => 'decimal:2',
            'default_amount' => 'decimal:2',
            'default_interest_rate' => 'decimal:2',
        ];
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    /**
     * FynnEdge specialises in personal loans, so every customer-facing listing
     * (home, /loans, footer/header, the eligibility product picker) leads with
     * Personal Loan regardless of alphabetical order, then falls back to name.
     */
    public function scopeOrderedForDisplay(Builder $query): void
    {
        $query->orderByRaw('category != ?', [LoanCategory::PersonalLoan->value])->orderBy('name');
    }

    public function lenderProducts(): HasMany
    {
        return $this->hasMany(LenderProduct::class);
    }

    public function faqs(): MorphMany
    {
        return $this->morphMany(Faq::class, 'faqable')->orderBy('sort_order');
    }

    public function journeyDefinitions(): HasMany
    {
        return $this->hasMany(JourneyDefinition::class);
    }

    public function landingPages(): HasMany
    {
        return $this->hasMany(LoanLandingPage::class);
    }
}
