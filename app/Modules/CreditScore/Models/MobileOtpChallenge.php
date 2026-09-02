<?php

namespace App\Modules\CreditScore\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\MobileOtpChallengeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['mobile_number', 'otp_hash', 'expires_at', 'verified_at', 'attempts', 'ip_address'])]
class MobileOtpChallenge extends Model
{
    /** @use HasFactory<MobileOtpChallengeFactory> */
    use HasFactory, HasPublicId;

    protected static function newFactory(): MobileOtpChallengeFactory
    {
        return MobileOtpChallengeFactory::new();
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
