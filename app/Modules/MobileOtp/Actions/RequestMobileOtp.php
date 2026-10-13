<?php

namespace App\Modules\MobileOtp\Actions;

use App\Modules\MobileOtp\Contracts\SmsProvider;
use App\Modules\MobileOtp\DataTransferObjects\SmsSendResult;
use App\Modules\MobileOtp\Enums\MobileOtpPurpose;
use App\Modules\MobileOtp\Enums\MobileOtpStatus;
use App\Modules\MobileOtp\Enums\SmsFailureReason;
use App\Modules\MobileOtp\Enums\SmsSendStatus;
use App\Modules\MobileOtp\Exceptions\MobileOtpThrottled;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use App\Modules\MobileOtp\Services\MobileNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Throwable;

/**
 * Issues a code for a number and texts it. The single entry point for every
 * OTP on the site — the public API, the Quick Enquiry box, the loan journey
 * and the credit-score check all come through here.
 *
 * Order matters:
 *  1. Rate limits (per number, per IP) and the resend cooldown are checked
 *     under a short per-number lock, so two simultaneous requests cannot both
 *     slip under the cooldown and text the visitor twice.
 *  2. Older pending codes for the same number + purpose are superseded and the
 *     new challenge is written in one short transaction.
 *  3. Only then is the SMS sent — outside any transaction or lock, because the
 *     provider can take up to services.areach.timeout seconds to answer.
 *
 * The code never leaves this class except inside the SMS. Livewire actions
 * reach this without any route throttle middleware, which is why the limits
 * live here rather than only on routes.
 */
class RequestMobileOtp
{
    public function __construct(private SmsProvider $sms) {}

    /**
     * @param  string  $mobileNumber  Already normalised by MobileNumber::normalize() and validated.
     *
     * @throws MobileOtpThrottled
     */
    public function handle(string $mobileNumber, MobileOtpPurpose $purpose, ?string $ipAddress = null): MobileOtpChallenge
    {
        if (! MobileNumber::isValid($mobileNumber)) {
            throw new InvalidArgumentException('RequestMobileOtp expects a normalised 10-digit mobile number.');
        }

        $lock = Cache::lock("mobile-otp-request:{$mobileNumber}", 10);

        if (! $lock->get()) {
            throw MobileOtpThrottled::cooldown((int) config('mobile_otp.resend_cooldown_seconds'));
        }

        try {
            $this->guardLimits($mobileNumber, $purpose, $ipAddress);

            $code = $this->generateCode();
            $challenge = $this->storeChallenge($mobileNumber, $purpose, $code, $ipAddress);
        } finally {
            $lock->release();
        }

        $this->recordDelivery($challenge, $this->deliver($mobileNumber, $code));

        return $challenge;
    }

    /**
     * @throws MobileOtpThrottled
     */
    private function guardLimits(string $mobileNumber, MobileOtpPurpose $purpose, ?string $ipAddress): void
    {
        $numberKey = "otp-request:{$mobileNumber}";
        $ipKey = $ipAddress ? "otp-request-ip:{$ipAddress}" : null;

        if (RateLimiter::tooManyAttempts($numberKey, (int) config('mobile_otp.max_sends_per_number_per_hour'))) {
            throw MobileOtpThrottled::tooManyForNumber(RateLimiter::availableIn($numberKey));
        }

        if ($ipKey && RateLimiter::tooManyAttempts($ipKey, (int) config('mobile_otp.max_sends_per_ip_per_hour'))) {
            throw MobileOtpThrottled::tooManyFromAddress(RateLimiter::availableIn($ipKey));
        }

        // A send the provider definitely rejected does not start a cooldown —
        // the visitor never got a code, so making them wait would only punish them.
        // The hourly per-number cap above still bounds how often that can repeat.
        $latest = MobileOtpChallenge::query()
            ->where('mobile_number', $mobileNumber)
            ->where('purpose', $purpose)
            ->where('sms_status', '!=', SmsSendStatus::Failed)
            ->latest('created_at')
            ->orderByDesc('id')
            ->first();

        if ($latest && ($wait = $latest->resendAvailableInSeconds()) > 0) {
            throw MobileOtpThrottled::cooldown($wait);
        }

        RateLimiter::hit($numberKey, 3600);

        if ($ipKey) {
            RateLimiter::hit($ipKey, 3600);
        }
    }

    /**
     * random_int() is a CSPRNG. Drawing from 0..10^n-1 and left-padding keeps
     * codes that start with 0 — the old 100000..999999 range never issued them,
     * which quietly took a tenth of the code space away.
     */
    private function generateCode(): string
    {
        $length = MobileOtpChallenge::codeLength();

        return str_pad((string) $this->randomNumberBelow(10 ** $length), $length, '0', STR_PAD_LEFT);
    }

    /** The CSPRNG draw, separate only so a test can pin it to prove the zero-padding. */
    protected function randomNumberBelow(int $ceiling): int
    {
        return random_int(0, $ceiling - 1);
    }

    private function storeChallenge(string $mobileNumber, MobileOtpPurpose $purpose, string $code, ?string $ipAddress): MobileOtpChallenge
    {
        return DB::transaction(function () use ($mobileNumber, $purpose, $code, $ipAddress): MobileOtpChallenge {
            // Only the latest code for a number + purpose is ever valid.
            MobileOtpChallenge::query()
                ->where('mobile_number', $mobileNumber)
                ->where('purpose', $purpose)
                ->where('status', MobileOtpStatus::Pending)
                ->update(['status' => MobileOtpStatus::Superseded, 'updated_at' => now()]);

            return MobileOtpChallenge::query()->create([
                'mobile_number' => $mobileNumber,
                'purpose' => $purpose,
                'otp_hash' => Hash::make($code),
                'status' => MobileOtpStatus::Pending,
                'expires_at' => now()->addMinutes((int) config('mobile_otp.expiry_minutes')),
                'attempts' => 0,
                'max_attempts' => max(1, (int) config('mobile_otp.max_attempts')),
                'ip_address' => $ipAddress,
                'sms_provider' => $this->sms->name(),
                'sms_status' => SmsSendStatus::Pending,
            ]);
        });
    }

    private function deliver(string $mobileNumber, string $code): SmsSendResult
    {
        $message = strtr((string) config('mobile_otp.message'), [
            ':code' => $code,
            ':minutes' => (string) config('mobile_otp.expiry_minutes'),
        ]);

        try {
            return $this->sms->sendOtp(MobileNumber::international($mobileNumber), $message);
        } catch (Throwable $exception) {
            // Providers are meant to return a result rather than throw. If one does
            // anyway, record only the exception class: its message may quote the text.
            Log::error('Mobile OTP: SMS provider threw unexpectedly.', ['exception' => $exception::class]);

            return SmsSendResult::failed(SmsFailureReason::UnexpectedError);
        }
    }

    private function recordDelivery(MobileOtpChallenge $challenge, SmsSendResult $result): void
    {
        $challenge->forceFill([
            'sms_status' => $result->status,
            'sms_provider_reference' => $result->providerReference,
            'sms_failure_reason' => $result->failureReason,
            'sms_http_status' => $result->httpStatus,
            'sms_sent_at' => $result->status === SmsSendStatus::Accepted ? now() : null,
            // Nobody holds the code of a send the provider rejected, so the challenge
            // cannot be verified and the visitor is told to ask again.
            'status' => $result->isFailure() ? MobileOtpStatus::Failed : $challenge->status,
        ])->save();

        if ($result->status !== SmsSendStatus::Accepted) {
            Log::warning('Mobile OTP: SMS not confirmed as accepted.', [
                'challenge' => $challenge->public_id,
                'purpose' => $challenge->purpose->value,
                'mobile' => $challenge->maskedMobileNumber(),
                'provider' => $challenge->sms_provider,
                'sms_status' => $result->status->value,
                'reason' => $result->failureReason?->value,
                'http_status' => $result->httpStatus,
            ]);
        }
    }
}
