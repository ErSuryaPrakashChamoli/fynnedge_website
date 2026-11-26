<?php

namespace App\Modules\DebtConsolidation\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\ContactEnquiry;
use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Enums\DebtApplicationStatus;
use App\Modules\DebtConsolidation\Models\Concerns\HasStatusHistory;
use App\Modules\MobileOtp\Services\MobileNumber;
use Database\Factories\DebtApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * One debt consolidation case, from the first enquiry to verified closure of
 * the existing debts. The reference (e.g. DC-7K3M9QX2) is what the customer
 * and staff quote; it is not an access token — every customer route also
 * checks the signed-in customer's verified mobile.
 */
#[Fillable([
    'customer_id', 'contact_enquiry_id', 'applicant_type', 'status', 'journey_step', 'full_name', 'email', 'mobile',
    'mobile_verified_at', 'city', 'state', 'pincode', 'declared_total_outstanding', 'declared_monthly_emi', 'source_url',
    'utm_source', 'utm_medium', 'utm_campaign', 'assigned_to', 'follow_up_at', 'enquiry_submitted_at', 'submitted_at',
    'last_customer_activity_at', 'closed_at', 'closure_reason',
])]
class DebtApplication extends Model
{
    /** @use HasFactory<DebtApplicationFactory> */
    use Auditable, HasFactory, HasPublicId, HasStatusHistory, SoftDeletes;

    private const REFERENCE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * The mobile stored once a case's personal data is anonymised (the column
     * is required). No OTP can prove it, so the case can never be reclaimed.
     */
    public const ANONYMISED_MOBILE = '0000000000';

    protected static function newFactory(): DebtApplicationFactory
    {
        return DebtApplicationFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (self $application): void {
            $application->reference ??= self::newReference();
        });
    }

    public static function newReference(): string
    {
        do {
            $reference = 'DC-'.collect(range(1, 8))->map(fn (): string => self::REFERENCE_ALPHABET[random_int(0, strlen(self::REFERENCE_ALPHABET) - 1)])->implode('');
        } while (self::withTrashed()->where('reference', $reference)->exists());

        return $reference;
    }

    protected function casts(): array
    {
        return [
            'applicant_type' => ApplicantType::class,
            'status' => DebtApplicationStatus::class,
            'mobile_verified_at' => 'datetime',
            'declared_total_outstanding' => 'decimal:2',
            'declared_monthly_emi' => 'decimal:2',
            'follow_up_at' => 'datetime',
            'enquiry_submitted_at' => 'datetime',
            'submitted_at' => 'datetime',
            'last_customer_activity_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Identity details never go into audit rows; figures and statuses do, so
     * corrections stay traceable (audit history is super-admin only).
     *
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return ['full_name', 'email', 'mobile', 'pincode'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contactEnquiry(): BelongsTo
    {
        return $this->belongsTo(ContactEnquiry::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function financialProfile(): HasOne
    {
        return $this->hasOne(DebtFinancialProfile::class);
    }

    public function liabilities(): HasMany
    {
        return $this->hasMany(DebtLiability::class)->orderBy('sort_order')->orderBy('id');
    }

    public function collaterals(): HasMany
    {
        return $this->hasMany(DebtCollateral::class);
    }

    public function scenarios(): HasMany
    {
        return $this->hasMany(DebtScenario::class)->orderBy('sort_order')->orderBy('id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(DebtCalculationSnapshot::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(DebtCalculationSnapshot::class)->ofMany(['id' => 'max'], fn (Builder $query) => $query->where('kind', DebtCalculationSnapshot::KIND_ASSESSMENT));
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DebtDocument::class)->latest('id');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(DebtConsent::class)->latest('id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(DebtNote::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function lenderMatches(): HasMany
    {
        return $this->hasMany(DebtLenderMatch::class);
    }

    public function lenderApplications(): HasMany
    {
        return $this->hasMany(DebtLenderApplication::class)->latest('id');
    }

    public function closures(): HasMany
    {
        return $this->hasMany(DebtClosure::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(DebtReport::class)->orderByDesc('generated_at')->orderByDesc('id');
    }

    /**
     * Cases a signed-in customer may see: linked to their account AND opened
     * from their OTP-verified mobile. A case only linked by a typed detail is
     * never enough.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOwnedBy(Builder $query, Customer $customer): void
    {
        $query->where('customer_id', $customer->getKey())
            ->where('mobile', (string) $customer->verified_mobile)
            ->whereNotNull('mobile_verified_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNotIn('status', [DebtApplicationStatus::Completed, DebtApplicationStatus::NotProceeding, DebtApplicationStatus::Withdrawn]);
    }

    public function isOwnedBy(Customer $customer): bool
    {
        return $this->customer_id === $customer->getKey()
            && $this->mobile === (string) $customer->verified_mobile
            && $this->mobile_verified_at !== null;
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    /**
     * Whether AnonymiseDebtApplication has removed this case's personal data.
     */
    public function isAnonymised(): bool
    {
        return $this->mobile === self::ANONYMISED_MOBILE;
    }

    /**
     * The customer may still change liabilities and finances: before
     * submission, or while staff have asked for more information.
     */
    public function isEditableByCustomer(): bool
    {
        return match ($this->status) {
            DebtApplicationStatus::Draft, DebtApplicationStatus::InformationPending => true,
            DebtApplicationStatus::EnquiryReceived => ! $this->isSubmitted(),
            default => false,
        };
    }

    public function maskedMobile(): string
    {
        return MobileNumber::mask($this->mobile);
    }

    public function displayName(): string
    {
        return $this->financialProfile?->business_name
            ?: ($this->full_name ?: Str::of($this->reference)->toString());
    }

    /**
     * An activity event for the case timeline (document views, report
     * downloads, consents, assessments). Never put PII in $details.
     *
     * @param  array<string, scalar|null>  $details
     */
    public function recordEvent(string $action, array $details = [], ?int $userId = null): void
    {
        $this->auditLogs()->create([
            'user_id' => $userId,
            'action' => $action,
            'changes' => collect($details)->map(fn ($value): array => ['old' => null, 'new' => $value])->all() ?: null,
        ]);
    }
}
