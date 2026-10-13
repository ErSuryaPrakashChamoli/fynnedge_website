<?php

namespace Database\Factories;

use App\Modules\MobileOtp\Enums\MobileOtpPurpose;
use App\Modules\MobileOtp\Enums\MobileOtpStatus;
use App\Modules\MobileOtp\Enums\SmsFailureReason;
use App\Modules\MobileOtp\Enums\SmsSendStatus;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<MobileOtpChallenge>
 */
class MobileOtpChallengeFactory extends Factory
{
    protected $model = MobileOtpChallenge::class;

    /**
     * A pending loan-enquiry challenge whose SMS the provider accepted, with the code 123456.
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'mobile_number' => '9'.$this->faker->numerify('#########'),
            'purpose' => MobileOtpPurpose::LoanEnquiry,
            'otp_hash' => Hash::make('123456'),
            'status' => MobileOtpStatus::Pending,
            'expires_at' => now()->addMinutes(10),
            'verified_at' => null,
            'consumed_at' => null,
            'attempts' => 0,
            'max_attempts' => 5,
            'ip_address' => $this->faker->ipv4(),
            'sms_provider' => 'areach',
            'sms_status' => SmsSendStatus::Accepted,
            'sms_provider_reference' => (string) Str::uuid(),
            'sms_http_status' => 200,
            'sms_sent_at' => now(),
        ];
    }

    public function forPurpose(MobileOtpPurpose $purpose): static
    {
        return $this->state(fn () => ['purpose' => $purpose]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subMinute()]);
    }

    public function verified(): static
    {
        return $this->state(fn () => ['status' => MobileOtpStatus::Verified, 'verified_at' => now(), 'attempts' => 1]);
    }

    public function smsFailed(): static
    {
        return $this->state(fn () => [
            'status' => MobileOtpStatus::Failed,
            'sms_status' => SmsSendStatus::Failed,
            'sms_provider_reference' => null,
            'sms_failure_reason' => SmsFailureReason::HttpError,
            'sms_http_status' => 401,
            'sms_sent_at' => null,
        ]);
    }
}
