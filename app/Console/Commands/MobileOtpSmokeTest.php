<?php

namespace App\Console\Commands;

use App\Modules\MobileOtp\Actions\RequestMobileOtp;
use App\Modules\MobileOtp\Actions\VerifyMobileOtp;
use App\Modules\MobileOtp\Contracts\SmsProvider;
use App\Modules\MobileOtp\Enums\MobileOtpPurpose;
use App\Modules\MobileOtp\Enums\SmsSendStatus;
use App\Modules\MobileOtp\Exceptions\MobileOtpThrottled;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use App\Modules\MobileOtp\Providers\AreachSmsProvider;
use App\Modules\MobileOtp\Services\MobileNumber;
use Illuminate\Console\Command;

/**
 * The one sanctioned way to send a REAL OTP outside the website: a single SMS
 * to a number the operator is authorised to text, through exactly the code path
 * the site uses, then verification of the code they receive.
 *
 * Three locks before anything is sent: MOBILE_OTP_LIVE_TEST_ENABLED=true, the
 * areach provider with a key configured, and an interactive "yes" (or
 * --confirm). It sends once and never retries — if the outcome is uncertain it
 * stops and says so rather than texting again. The code is never printed; the
 * API key is never printed.
 */
class MobileOtpSmokeTest extends Command
{
    protected $signature = 'mobile-otp:smoke-test
                            {mobile : The 10-digit Indian mobile number you are authorised to send a test SMS to}
                            {--purpose=loan_enquiry : Purpose to issue the challenge under (loan_enquiry, loan_application, credit_score_check)}
                            {--confirm : Skip the interactive confirmation (still requires MOBILE_OTP_LIVE_TEST_ENABLED=true)}';

    protected $description = 'Send ONE real OTP SMS through the configured gateway and verify the code received (live, opt-in)';

    public function handle(SmsProvider $provider, VerifyMobileOtp $verify): int
    {
        if (! $this->preflightPasses()) {
            return self::FAILURE;
        }

        $mobile = MobileNumber::normalize($this->argument('mobile'));
        $purpose = MobileOtpPurpose::tryFrom((string) $this->option('purpose'));

        if (! MobileNumber::isValid($mobile) || ! $purpose) {
            $this->components->error('Give a valid 10-digit Indian mobile number and a known --purpose.');

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Provider', $provider->name());
        $this->components->twoColumnDetail('Endpoint', rtrim((string) config('services.areach.base_url'), '/').'/sms');
        $this->components->twoColumnDetail('Sender ID', (string) config('services.areach.sender'));
        $this->components->twoColumnDetail('Recipient', '+91 '.MobileNumber::mask($mobile));
        $this->components->twoColumnDetail('Purpose', $purpose->value);

        if (! $this->option('confirm') && ! $this->confirm('This sends ONE real SMS to the number above. Continue?', false)) {
            $this->components->info('Cancelled. Nothing was sent.');

            return self::FAILURE;
        }

        try {
            // Same action the website and API use, holding this provider instance so
            // its response shape can be reported afterwards.
            $challenge = app()->makeWith(RequestMobileOtp::class, ['sms' => $provider])->handle($mobile, $purpose);
        } catch (MobileOtpThrottled $exception) {
            $this->components->error("Refused before sending: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->reportSend($challenge, $provider);

        if ($challenge->sms_status === SmsSendStatus::Failed) {
            $this->components->error('The provider did not accept the request. Not retrying. See docs/mobile-otp-integration.md → Troubleshooting.');

            return self::FAILURE;
        }

        if ($challenge->sms_status === SmsSendStatus::Unconfirmed) {
            $this->components->warn('The send is UNCONFIRMED. Do not run this again until you know whether the SMS arrived — a second run texts a second code.');
        }

        return $this->verifyReceivedCode($challenge, $verify);
    }

    private function preflightPasses(): bool
    {
        $blocked = array_filter([
            config('mobile_otp.live_test_enabled') ? null : 'MOBILE_OTP_LIVE_TEST_ENABLED is not true.',
            config('mobile_otp.sms_provider') === 'areach' ? null : 'MOBILE_OTP_SMS_PROVIDER is not "areach".',
            filled(config('services.areach.key')) ? null : 'AREACH_API_KEY is not set.',
            filled(config('services.areach.sender')) ? null : 'AREACH_SENDER is not set.',
        ]);

        foreach ($blocked as $reason) {
            $this->components->error("Blocked: {$reason} Nothing was sent.");
        }

        return $blocked === [];
    }

    private function reportSend(MobileOtpChallenge $challenge, SmsProvider $provider): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('Challenge ID', $challenge->public_id);
        $this->components->twoColumnDetail('SMS status', $challenge->sms_status->getLabel());
        $this->components->twoColumnDetail('Provider HTTP status', (string) ($challenge->sms_http_status ?? '—'));
        $this->components->twoColumnDetail('Provider reference', $challenge->sms_provider_reference ?? '—');
        $this->components->twoColumnDetail('Failure category', $challenge->sms_failure_reason?->getLabel() ?? '—');

        if ($provider instanceof AreachSmsProvider && $provider->lastResponseShape() !== []) {
            $shape = collect($provider->lastResponseShape())->map(fn (string $type, string $key) => "{$key}: {$type}")->implode(', ');
            $this->components->twoColumnDetail('Response shape (types only)', $shape);
        }

        $this->components->info('"Accepted" means Areach took the request. It does not prove the handset received the SMS.');
    }

    private function verifyReceivedCode(MobileOtpChallenge $challenge, VerifyMobileOtp $verify): int
    {
        $code = trim((string) $this->secret('Enter the code that arrived on the phone (leave blank to verify later through the API instead)'));

        if ($code === '') {
            $this->components->info('Verify through the real endpoint with the code that arrived (replace <OTP>):');
            $this->line(sprintf(
                "curl --location '%s' --header 'Content-Type: application/json' --header 'Accept: application/json' --data '%s'",
                route('api.v1.mobile-otp.verify'),
                json_encode([
                    'challenge_id' => $challenge->public_id,
                    'mobile_number' => $challenge->mobile_number,
                    'country_code' => 'IN',
                    'purpose' => $challenge->purpose->value,
                    'otp' => '<OTP>',
                ]),
            ));

            return self::SUCCESS;
        }

        $result = $verify->handle($challenge, $code);
        $challenge->refresh();

        $this->components->twoColumnDetail('Verification result', $result->value);
        $this->components->twoColumnDetail('Stored status', $challenge->effectiveStatus()->getLabel());
        $this->components->twoColumnDetail('Verified at', $challenge->verified_at?->toIso8601String() ?? '—');

        if (! $result->isVerified()) {
            $this->components->error($result->message());

            return self::FAILURE;
        }

        $this->components->info('Live round trip passed: SMS accepted by Areach, code received and verified, status stored as Verified.');

        return self::SUCCESS;
    }
}
