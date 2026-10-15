<?php

namespace App\Modules\CreditBureau\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\User;
use App\Modules\CreditBureau\Enums\ConsentMethod;
use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\Customers\Models\Customer;
use App\Modules\Journey\Models\JourneySession;
use Database\Factories\CreditConsentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The consent record for a credit bureau pull. Either captured by the website
 * journey's checkbox (journey_session_id, method OnlineJourney) or recorded by
 * an employee for a customer (customer_id, recorded_by, evidence_reference) —
 * the latter is what authorises an employee-initiated CRIF report.
 */
#[Fillable([
    'journey_session_id', 'customer_id', 'pan_number', 'date_of_birth', 'purpose', 'method',
    'evidence_reference', 'recorded_by', 'terms_version', 'ip_address', 'status', 'consented_at',
])]
class CreditConsent extends Model
{
    /** @use HasFactory<CreditConsentFactory> */
    use HasFactory, HasPublicId;

    protected static function newFactory(): CreditConsentFactory
    {
        return CreditConsentFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => ConsentStatus::class,
            'method' => ConsentMethod::class,
            'consented_at' => 'datetime',
            'date_of_birth' => 'date',
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

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function checks(): HasMany
    {
        return $this->hasMany(CreditCheck::class);
    }

    public function crifReports(): HasMany
    {
        return $this->hasMany(CrifReport::class);
    }
}
