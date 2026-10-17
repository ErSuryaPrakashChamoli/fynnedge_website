<?php

namespace App\Modules\CreditScore\Models;

use App\Models\Concerns\HasPublicId;
use App\Modules\Cibil\Enums\CibilErrorCode;
use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditBureau\Enums\CrifErrorCode;
use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\Customers\Models\Customer;
use App\Modules\Equifax\Enums\EquifaxErrorCode;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Experian\Enums\ExperianErrorCode;
use App\Modules\Experian\Models\ExperianCreditRequest;
use App\Modules\Wallet\Enums\CheckBilling;
use App\Modules\Wallet\Models\WalletTransaction;
use Database\Factories\CreditScoreCheckFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id', 'bureau', 'mobile_number', 'mobile_verified_at', 'full_name', 'first_name', 'middle_name', 'last_name', 'date_of_birth', 'gender',
    'pan_number', 'provider', 'status', 'score', 'raw_response', 'report_insights',
    'consent_given_at', 'ip_address', 'requested_at', 'completed_at',
])]
class CreditScoreCheck extends Model
{
    /** The provider name every live (real bureau) score provider reports. */
    public const LIVE_PROVIDER = 'surepass';

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
            'billing' => CheckBilling::class,
            'raw_response' => 'array',
            'report_insights' => 'encrypted:array',
            'date_of_birth' => 'date',
            'mobile_verified_at' => 'datetime',
            'consent_given_at' => 'datetime',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** The wallet charge that paid for this check, when it was a paid refresh. */
    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }

    /**
     * Checks answered by a real bureau (the live Surepass providers) — never a
     * demo score. Only these are ever shown in a customer's portal.
     *
     * @param  Builder<self>  $query
     */
    public function scopeGenuine(Builder $query): void
    {
        $query->where('provider', self::LIVE_PROVIDER);
    }

    public function isGenuine(): bool
    {
        return $this->provider === self::LIVE_PROVIDER;
    }

    /** A genuine check the bureau answered — with a score, or with "no score yet". */
    public function isSuccessful(): bool
    {
        return $this->isGenuine() && $this->status === CreditCheckStatus::Completed;
    }

    /** The earlier check this one was answered from inside the reuse period, if any. */
    public function reusedFromId(): ?string
    {
        $id = is_array($this->raw_response) ? ($this->raw_response['reused_from'] ?? null) : null;

        return is_string($id) ? $id : null;
    }

    /**
     * The bureau request holding this check's full report, when the provider
     * recorded one (CIBIL, Equifax, Experian — and the Experian stand-in on the
     * CIBIL page; CRIF's public check keeps only the score). A reused check
     * points at the original check's request. Only the id the server stored is
     * followed, never anything from a request.
     */
    public function bureauRequest(): CibilCreditRequest|EquifaxCreditRequest|ExperianCreditRequest|null
    {
        $source = $this;

        if (($originalId = $this->reusedFromId()) !== null) {
            $source = self::query()
                ->where('public_id', $originalId)
                ->where('mobile_number', $this->mobile_number)
                ->where('pan_number', $this->pan_number)
                ->first() ?? $this;
        }

        $raw = is_array($source->raw_response) ? $source->raw_response : [];

        foreach ([
            'cibil_request' => CibilCreditRequest::class,
            'experian_request' => ExperianCreditRequest::class,
            'equifax_request' => EquifaxCreditRequest::class,
        ] as $key => $model) {
            if (is_string($raw[$key] ?? null)) {
                return $model::query()->where('public_id', $raw[$key])->first();
            }
        }

        return null;
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
    public function liveFailure(): CrifErrorCode|EquifaxErrorCode|ExperianErrorCode|CibilErrorCode|null
    {
        $error = is_array($this->raw_response) ? ($this->raw_response['error'] ?? null) : null;

        if (! is_string($error)) {
            return null;
        }

        return match ($this->sourceBureau()) {
            BureauName::Equifax => EquifaxErrorCode::tryFrom($error),
            BureauName::Experian => ExperianErrorCode::tryFrom($error),
            BureauName::Cibil => CibilErrorCode::tryFrom($error),
            default => CrifErrorCode::tryFrom($error),
        };
    }

    /**
     * The bureau whose answer this check holds — the page's own bureau, except
     * where a stand-in source answered it (the CIBIL page answered by Experian;
     * recorded as raw_response.source_bureau).
     */
    public function sourceBureau(): ?BureauName
    {
        $source = is_array($this->raw_response) ? ($this->raw_response['source_bureau'] ?? null) : null;

        return is_string($source) ? BureauName::tryFrom($source) : $this->bureau;
    }
}
