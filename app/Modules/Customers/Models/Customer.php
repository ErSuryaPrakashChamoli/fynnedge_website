<?php

namespace App\Modules\Customers\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\CreditBureau\Models\CrifReport;
use App\Modules\CreditBureau\Services\PanNumber;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\Equifax\Enums\Gender;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Experian\Models\ExperianCreditRequest;
use App\Modules\Journey\Models\JourneySession;
use App\Modules\MobileOtp\Services\MobileNumber;
use App\Modules\Wallet\Models\CustomerPlan;
use App\Modules\Wallet\Models\WalletTransaction;
use Database\Factories\CustomerFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A customer — and, once a mobile number has been proved by OTP, their FynnOn
 * customer-portal account (the `customer` guard). There is no second identity
 * table: the lead record staff already work with is the account.
 *
 *  - `phone` is a contact number someone typed in (a form or staff).
 *  - `verified_mobile` is the number the customer proved by OTP; it is the
 *    login identifier and unique across accounts.
 *  - Name, date of birth, gender, PAN and address are customer-provided;
 *    `identity_verified_at` is set only by staff after KYC and locks them.
 *    Credit-bureau data is never copied in here.
 *
 * Customers sign in with an OTP only: there is no password and no "remember
 * me" token, so a stolen database row holds no credential.
 */
#[Fillable([
    'full_name', 'email', 'phone',
    'verified_mobile', 'mobile_verified_at', 'portal_activated_at', 'portal_disabled_at', 'last_login_at',
    'date_of_birth', 'gender', 'pan_encrypted', 'pan_masked', 'pan_hash', 'identity_verified_at',
    'address_line1', 'address_line2', 'city', 'state', 'pincode',
])]
#[Hidden(['pan_encrypted', 'pan_hash'])]
class Customer extends Model implements AuthenticatableContract
{
    /** @use HasFactory<CustomerFactory> */
    use Auditable, Authenticatable, HasFactory, HasPublicId;

    /**
     * The profile fields that count towards "profile completion".
     *
     * @var list<string>
     */
    public const PROFILE_FIELDS = ['full_name', 'email', 'date_of_birth', 'gender', 'pan_masked', 'address_line1', 'city', 'state', 'pincode'];

    /**
     * Fields a customer may not change themselves once staff have verified
     * their identity.
     *
     * @var list<string>
     */
    public const IDENTITY_FIELDS = ['full_name', 'date_of_birth', 'gender'];

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }

    protected function casts(): array
    {
        return [
            'mobile_verified_at' => 'datetime',
            'portal_activated_at' => 'datetime',
            'portal_disabled_at' => 'datetime',
            'last_login_at' => 'datetime',
            'date_of_birth' => 'date',
            'gender' => Gender::class,
            'pan_encrypted' => 'encrypted',
            'identity_verified_at' => 'datetime',
        ];
    }

    public function journeySessions(): HasMany
    {
        return $this->hasMany(JourneySession::class);
    }

    public function creditScoreChecks(): HasMany
    {
        return $this->hasMany(CreditScoreCheck::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(CustomerPlan::class);
    }

    public function crifReports(): HasMany
    {
        return $this->hasMany(CrifReport::class);
    }

    public function latestCrifReport(): HasOne
    {
        return $this->hasOne(CrifReport::class)->latestOfMany();
    }

    public function equifaxRequests(): HasMany
    {
        return $this->hasMany(EquifaxCreditRequest::class);
    }

    public function latestEquifaxRequest(): HasOne
    {
        return $this->hasOne(EquifaxCreditRequest::class)->latestOfMany();
    }

    public function experianRequests(): HasMany
    {
        return $this->hasMany(ExperianCreditRequest::class);
    }

    public function latestExperianRequest(): HasOne
    {
        return $this->hasOne(ExperianCreditRequest::class)->latestOfMany();
    }

    public function cibilRequests(): HasMany
    {
        return $this->hasMany(CibilCreditRequest::class);
    }

    public function latestCibilRequest(): HasOne
    {
        return $this->hasOne(CibilCreditRequest::class)->latestOfMany();
    }

    /**
     * Customer accounts never issue "remember me" tokens (there is no column):
     * a session ends with the browser session or its lifetime.
     */
    public function getRememberTokenName(): string
    {
        return '';
    }

    /** Activated (mobile proved by OTP) and not disabled by staff. */
    public function hasPortalAccess(): bool
    {
        return $this->verified_mobile !== null
            && $this->portal_activated_at !== null
            && $this->portal_disabled_at === null;
    }

    public function isIdentityVerified(): bool
    {
        return $this->identity_verified_at !== null;
    }

    public function maskedMobile(): ?string
    {
        $mobile = $this->verified_mobile ?? $this->phone;

        return filled($mobile) ? MobileNumber::mask($mobile) : null;
    }

    /** The PAN the customer gave, for prefilling their own forms — never for display. */
    public function pan(): ?string
    {
        return filled($this->pan_encrypted) ? (string) $this->pan_encrypted : null;
    }

    /**
     * Stores a customer-provided PAN encrypted, with its masked copy and hash.
     */
    public function setPan(string $pan): void
    {
        $pan = PanNumber::normalize($pan);

        $this->forceFill([
            'pan_encrypted' => $pan,
            'pan_masked' => PanNumber::mask($pan),
            'pan_hash' => PanNumber::hash($pan),
        ]);
    }

    /**
     * Fills BLANK profile fields with details the customer typed into a credit
     * check the bureau then matched (name as on PAN, date of birth, gender,
     * PAN). They stay customer-provided — never "verified" — and nothing the
     * customer or staff already saved is overwritten. Returns the fields filled.
     *
     * @param  array{full_name?: ?string, date_of_birth?: mixed, gender?: ?string, pan_number?: ?string}  $details
     * @return list<string>
     */
    public function fillBlankProfileDetails(array $details): array
    {
        if ($this->isIdentityVerified()) {
            return [];
        }

        $filled = [];

        foreach (['full_name', 'date_of_birth', 'gender'] as $field) {
            if (blank($this->getAttribute($field)) && filled($details[$field] ?? null)) {
                $this->setAttribute($field, $field === 'gender' ? Gender::tryFrom((string) $details[$field]) : $details[$field]);
                $filled[] = $field;
            }
        }

        if (blank($this->pan_hash) && PanNumber::isValid(PanNumber::normalize($details['pan_number'] ?? ''))) {
            $this->setPan((string) $details['pan_number']);
            $filled[] = 'pan';
        }

        if ($filled !== []) {
            $this->save();
            $this->recordEvent('profile_prefilled_from_credit_check', ['fields' => implode(', ', $filled)]);
        }

        return $filled;
    }

    /**
     * Share of PROFILE_FIELDS that hold a value, 0–100.
     */
    public function profileCompletion(): int
    {
        $filled = collect(self::PROFILE_FIELDS)->filter(fn (string $field): bool => filled($this->getAttribute($field)))->count();

        return (int) round($filled / count(self::PROFILE_FIELDS) * 100);
    }

    /**
     * @return list<string>
     */
    public function missingProfileFields(): array
    {
        return collect(self::PROFILE_FIELDS)
            ->reject(fn (string $field): bool => filled($this->getAttribute($field)))
            ->values()
            ->all();
    }

    /**
     * Records an account event (activation, login, profile change…) in the
     * same audit_logs table the Auditable trait writes to. Pass field NAMES
     * and outcomes only — never a mobile number, PAN, address or OTP.
     *
     * @param  array<string, scalar|null>  $details
     */
    public function recordEvent(string $action, array $details = [], ?int $userId = null): void
    {
        $this->auditLogs()->create([
            'user_id' => $userId,
            'action' => $action,
            'changes' => collect($details)->map(fn ($value) => ['old' => null, 'new' => $value])->all() ?: null,
        ]);
    }

    /**
     * Personal and identity data never enters the audit trail; account events
     * name the fields that changed instead (recordEvent()).
     *
     * @return array<int, string>
     */
    protected function auditExcept(): array
    {
        return [
            'full_name', 'email', 'phone', 'verified_mobile', 'date_of_birth', 'gender',
            'pan_encrypted', 'pan_masked', 'pan_hash',
            'address_line1', 'address_line2', 'city', 'state', 'pincode',
            'last_login_at',
        ];
    }
}
