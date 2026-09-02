<?php

namespace App\Modules\CreditBureau\Models;

use App\Models\Concerns\HasPublicId;
use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\Journey\Models\JourneySession;
use Database\Factories\CreditConsentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['journey_session_id', 'pan_number', 'date_of_birth', 'purpose', 'terms_version', 'ip_address', 'status', 'consented_at'])]
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
            'consented_at' => 'datetime',
            'date_of_birth' => 'date',
        ];
    }

    public function journeySession(): BelongsTo
    {
        return $this->belongsTo(JourneySession::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(CreditCheck::class);
    }
}
