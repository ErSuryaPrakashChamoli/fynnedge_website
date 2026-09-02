<?php

namespace App\Modules\Applications\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\LenderProduct;
use App\Modules\Applications\Enums\ApplicationStatus;
use App\Modules\Applications\Enums\AssistancePreference;
use App\Modules\Customers\Models\Customer;
use App\Modules\Eligibility\Models\EligibilityResult;
use App\Modules\Journey\Models\JourneySession;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable(['journey_session_id', 'customer_id', 'lender_product_id', 'eligibility_result_id', 'status', 'assistance_preference', 'assistance_requested_at', 'submitted_at'])]
class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use Auditable, HasFactory, HasPublicId;

    protected static function newFactory(): ApplicationFactory
    {
        return ApplicationFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'assistance_preference' => AssistancePreference::class,
            'assistance_requested_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function journeySession(): BelongsTo
    {
        return $this->belongsTo(JourneySession::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lenderProduct(): BelongsTo
    {
        return $this->belongsTo(LenderProduct::class);
    }

    public function eligibilityResult(): BelongsTo
    {
        return $this->belongsTo(EligibilityResult::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    /**
     * All document slots a customer may fill in for this application — both
     * required and optional (e.g. "Other document"), in display order.
     *
     * @return Collection<int, LenderProductDocumentRequirement>
     */
    public function documentRequirements(): Collection
    {
        return LenderProductDocumentRequirement::query()
            ->where('lender_product_id', $this->lender_product_id)
            ->with('documentType')
            ->orderBy('order')
            ->get();
    }

    /**
     * @return Collection<int, LenderProductDocumentRequirement>
     */
    public function requiredDocuments(): Collection
    {
        return $this->documentRequirements()->where('is_required', true)->values();
    }

    public function hasAllRequiredDocuments(): bool
    {
        $uploadedCountsByType = $this->documents()->get()->countBy('document_type_id');

        foreach ($this->requiredDocuments() as $requirement) {
            $uploadedCount = $uploadedCountsByType->get($requirement->document_type_id, 0);

            if ($uploadedCount < max($requirement->min_slots, 1)) {
                return false;
            }
        }

        return true;
    }
}
