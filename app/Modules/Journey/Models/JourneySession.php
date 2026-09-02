<?php

namespace App\Modules\Journey\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\LoanProduct;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\Customers\Models\Customer;
use App\Modules\Eligibility\Models\EligibilityResult;
use App\Modules\Journey\Enums\JourneySessionStatus;
use Database\Factories\JourneySessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'loan_product_id', 'customer_id', 'journey_definition_id', 'current_step_id', 'status',
    'phone_number', 'phone_verified_at',
    'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
    'referrer', 'landing_page', 'completed_at',
])]
class JourneySession extends Model
{
    /** @use HasFactory<JourneySessionFactory> */
    use HasFactory, HasPublicId;

    protected static function newFactory(): JourneySessionFactory
    {
        return JourneySessionFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => JourneySessionStatus::class,
            'phone_verified_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function journeyDefinition(): BelongsTo
    {
        return $this->belongsTo(JourneyDefinition::class);
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(JourneyStep::class, 'current_step_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(JourneyResponse::class);
    }

    public function eligibilityResults(): HasMany
    {
        return $this->hasMany(EligibilityResult::class);
    }

    public function creditConsent(): HasOne
    {
        return $this->hasOne(CreditConsent::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function responsesByKey(): array
    {
        return $this->responses()->pluck('value', 'field_key')->all();
    }
}
