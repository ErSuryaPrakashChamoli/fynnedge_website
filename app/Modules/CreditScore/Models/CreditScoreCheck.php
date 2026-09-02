<?php

namespace App\Modules\CreditScore\Models;

use App\Models\Concerns\HasPublicId;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditScore\Enums\BureauName;
use Database\Factories\CreditScoreCheckFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'bureau', 'mobile_number', 'mobile_verified_at', 'full_name', 'date_of_birth',
    'pan_number', 'provider', 'status', 'score', 'raw_response',
    'consent_given_at', 'ip_address', 'requested_at', 'completed_at',
])]
class CreditScoreCheck extends Model
{
    /** @use HasFactory<CreditScoreCheckFactory> */
    use HasFactory, HasPublicId;

    protected static function newFactory(): CreditScoreCheckFactory
    {
        return CreditScoreCheckFactory::new();
    }

    protected function casts(): array
    {
        return [
            'bureau' => BureauName::class,
            'status' => CreditCheckStatus::class,
            'raw_response' => 'array',
            'date_of_birth' => 'date',
            'mobile_verified_at' => 'datetime',
            'consent_given_at' => 'datetime',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
