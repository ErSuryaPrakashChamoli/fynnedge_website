<?php

namespace App\Modules\CreditScore\Models;

use App\Models\Concerns\HasPublicId;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditBureau\Enums\CrifErrorCode;
use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\Equifax\Enums\EquifaxErrorCode;
use App\Modules\Experian\Enums\ExperianErrorCode;
use Database\Factories\CreditScoreCheckFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'bureau', 'mobile_number', 'mobile_verified_at', 'full_name', 'first_name', 'middle_name', 'last_name', 'date_of_birth', 'gender',
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

    /**
     * First and middle name together — what is sent to Surepass as first_name,
     * since its CRIF API has no separate middle-name field.
     */
    public function givenNames(): string
    {
        return trim(($this->first_name ?? '').' '.($this->middle_name ?? ''));
    }

    /**
     * Why a live (Surepass) check gave no score — the visitor only sees a
     * generic message, so this is what admins read to find the cause.
     */
    public function liveFailure(): CrifErrorCode|EquifaxErrorCode|ExperianErrorCode|null
    {
        $error = is_array($this->raw_response) ? ($this->raw_response['error'] ?? null) : null;

        if (! is_string($error)) {
            return null;
        }

        return match ($this->sourceBureau()) {
            BureauName::Equifax => EquifaxErrorCode::tryFrom($error),
            BureauName::Experian => ExperianErrorCode::tryFrom($error),
            default => CrifErrorCode::tryFrom($error),
        };
    }

    /**
     * The bureau whose answer this check holds — the page's own bureau, except
     * where a stand-in source answered it (the CIBIL page, by Experian, until a
     * CIBIL API exists; recorded as raw_response.source_bureau).
     */
    public function sourceBureau(): ?BureauName
    {
        $source = is_array($this->raw_response) ? ($this->raw_response['source_bureau'] ?? null) : null;

        return is_string($source) ? BureauName::tryFrom($source) : $this->bureau;
    }
}
