<?php

namespace App\Modules\CreditBureau\Models;

use App\Models\Concerns\HasPublicId;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use Database\Factories\CreditCheckFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['credit_consent_id', 'provider', 'reference', 'status', 'score', 'raw_response', 'requested_at', 'completed_at'])]
class CreditCheck extends Model
{
    /** @use HasFactory<CreditCheckFactory> */
    use HasFactory, HasPublicId;

    protected static function newFactory(): CreditCheckFactory
    {
        return CreditCheckFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => CreditCheckStatus::class,
            'raw_response' => 'array',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function consent(): BelongsTo
    {
        return $this->belongsTo(CreditConsent::class, 'credit_consent_id');
    }
}
