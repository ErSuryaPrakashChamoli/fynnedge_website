<?php

namespace App\Modules\MobileOtp\Models;

use App\Models\Concerns\HasPublicId;
use App\Modules\MobileOtp\Enums\MobileOtpPurpose;
use App\Modules\MobileOtp\Enums\MobileOtpStatus;
use App\Modules\MobileOtp\Enums\SmsFailureReason;
use App\Modules\MobileOtp\Enums\SmsSendStatus;
use App\Modules\MobileOtp\Services\MobileNumber;
use Database\Factories\MobileOtpChallengeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One code issued to one number for one purpose. Only the bcrypt hash of the
 * code is stored; the plaintext exists only inside RequestMobileOtp and the SMS.
 *
 * Two independent state fields, never merged: `status` is whether the visitor
 * proved they hold the number (see MobileOtpStatus), `sms_status` is what the
 * gateway said about sending (see SmsSendStatus). An accepted SMS is not a
 * verified number.
 *
 * `public_id` is the only identifier that leaves the server; `consumed_at`
 * marks the one protected action (lead, journey step, credit check) that a
 * verified challenge has already been spent on.
 */
#[Fillable([
    'mobile_number', 'purpose', 'otp_hash', 'status', 'expires_at', 'verified_at', 'consumed_at',
    'attempts', 'max_attempts', 'ip_address', 'sms_provider', 'sms_status', 'sms_provider_reference',
    'sms_failure_reason', 'sms_http_status', 'sms_sent_at',
])]
#[Hidden(['otp_hash'])]
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
            'purpose' => MobileOtpPurpose::class,
            'status' => MobileOtpStatus::class,
            'sms_status' => SmsSendStatus::class,
            'sms_failure_reason' => SmsFailureReason::class,
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'consumed_at' => 'datetime',
            'sms_sent_at' => 'datetime',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'sms_http_status' => 'integer',
        ];
    }

    /**
     * Digits per code, from config('mobile_otp.length') clamped to 4–8. The one
     * place generation, validation rules and the forms' maxlength all read it from.
     */
    public static function codeLength(): int
    {
        return max(4, min(8, (int) config('mobile_otp.length')));
    }

    /**
     * The lookup every verifier uses: id, number AND purpose must all match, so a
     * challenge issued to one number or for one form can never stand in for another.
     */
    public static function findForVerification(?string $publicId, ?string $mobileNumber, MobileOtpPurpose $purpose): ?self
    {
        if (blank($publicId) || blank($mobileNumber)) {
            return null;
        }

        return static::query()
            ->where('public_id', $publicId)
            ->where('mobile_number', $mobileNumber)
            ->where('purpose', $purpose)
            ->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->status === MobileOtpStatus::Verified && $this->verified_at !== null;
    }

    /** The stored status, with a pending challenge past its expiry reported as Expired. */
    public function effectiveStatus(): MobileOtpStatus
    {
        if ($this->status === MobileOtpStatus::Pending && $this->isExpired()) {
            return MobileOtpStatus::Expired;
        }

        return $this->status;
    }

    public function maskedMobileNumber(): string
    {
        return MobileNumber::mask((string) $this->mobile_number);
    }

    public function attemptsRemaining(): int
    {
        return max(0, $this->max_attempts - $this->attempts);
    }

    public function expiresInSeconds(): int
    {
        return max(0, $this->expires_at->getTimestamp() - now()->getTimestamp());
    }

    /** Seconds until RequestMobileOtp will issue another code for this number and purpose. */
    public function resendAvailableInSeconds(): int
    {
        if ($this->sms_status === SmsSendStatus::Failed) {
            return 0;
        }

        $cooldown = (int) config('mobile_otp.resend_cooldown_seconds');

        return max(0, $this->created_at->getTimestamp() + $cooldown - now()->getTimestamp());
    }

    /**
     * Spends this verified challenge on one protected action. A single
     * conditional UPDATE, so two simultaneous submissions with the same
     * challenge cannot both succeed — exactly one sees a row change.
     */
    public function consumeFor(string $mobileNumber, MobileOtpPurpose $purpose): bool
    {
        $consumedAt = now();

        $consumed = static::query()
            ->whereKey($this->getKey())
            ->where('mobile_number', $mobileNumber)
            ->where('purpose', $purpose)
            ->where('status', MobileOtpStatus::Verified)
            ->whereNull('consumed_at')
            ->where('verified_at', '>=', $consumedAt->copy()->subMinutes((int) config('mobile_otp.verified_ttl_minutes')))
            ->update(['consumed_at' => $consumedAt, 'updated_at' => $consumedAt]);

        if ($consumed === 1) {
            $this->forceFill(['consumed_at' => $consumedAt])->syncOriginalAttribute('consumed_at');
        }

        return $consumed === 1;
    }

    /**
     * Filters by the status an admin sees (effectiveStatus), which for Pending and
     * Expired depends on expires_at rather than the stored column alone.
     *
     * @param  Builder<MobileOtpChallenge>  $query
     */
    public function scopeWhereEffectiveStatus(Builder $query, MobileOtpStatus $status): void
    {
        match ($status) {
            MobileOtpStatus::Pending => $query->where('status', MobileOtpStatus::Pending)->where('expires_at', '>', now()),
            MobileOtpStatus::Expired => $query->where('status', MobileOtpStatus::Pending)->where('expires_at', '<=', now()),
            default => $query->where('status', $status),
        };
    }
}
