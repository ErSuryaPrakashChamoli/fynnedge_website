<?php

use App\Modules\MobileOtp\Enums\MobileOtpPurpose;
use App\Modules\MobileOtp\Enums\MobileOtpStatus;
use App\Modules\MobileOtp\Enums\SmsFailureReason;
use App\Modules\MobileOtp\Enums\SmsSendStatus;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function otpSendPayload(array $overrides = []): array
{
    return ['mobile_number' => '9876543210', 'country_code' => 'IN', 'purpose' => 'loan_enquiry', ...$overrides];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function otpVerifyPayload(MobileOtpChallenge $challenge, string $otp, array $overrides = []): array
{
    return [
        'challenge_id' => $challenge->public_id,
        'mobile_number' => $challenge->mobile_number,
        'country_code' => 'IN',
        'purpose' => $challenge->purpose->value,
        'otp' => $otp,
        ...$overrides,
    ];
}

function pendingChallenge(string $code = '123456', array $attributes = []): MobileOtpChallenge
{
    return MobileOtpChallenge::factory()->create(['mobile_number' => '9876543210', 'otp_hash' => Hash::make($code), ...$attributes]);
}

describe('send', function () {
    it('texts a code and answers with a safe challenge reference', function () {
        fakeAreachSms();
        $this->freezeSecond();

        $response = $this->postJson('/api/v1/mobile-otp/send', otpSendPayload());

        $challenge = MobileOtpChallenge::query()->sole();
        $response->assertCreated()->assertExactJson([
            'success' => true,
            'message' => 'Verification code sent if delivery was accepted.',
            'data' => [
                'challenge_id' => $challenge->public_id,
                'mobile_number_masked' => '******3210',
                'purpose' => 'loan_enquiry',
                'status' => 'pending',
                'sms_status' => 'accepted',
                'expires_at' => now()->addMinutes(10)->toIso8601String(),
                'expires_in_seconds' => 600,
                'resend_after_seconds' => 60,
                'verified' => false,
                'verified_at' => null,
            ],
        ]);
        expect($challenge)->purpose->toBe(MobileOtpPurpose::LoanEnquiry)->status->toBe(MobileOtpStatus::Pending);
        Http::assertSent(fn (Request $request) => $request['to'] === '919876543210');
    });

    it('never puts the code, its hash or the api key in the response', function () {
        fakeAreachSms();

        $body = $this->postJson('/api/v1/mobile-otp/send', otpSendPayload())->assertCreated()->getContent();

        expect($body)
            ->not->toContain(lastTextedOtp())
            ->not->toContain('otp_hash')
            ->not->toContain('test-areach-key')
            ->not->toContain('9876543210');
    });

    it('normalises the number before validating and storing it', function () {
        fakeAreachSms();

        $this->postJson('/api/v1/mobile-otp/send', otpSendPayload(['mobile_number' => '+91 98765-43210']))->assertCreated();

        expect(MobileOtpChallenge::query()->sole()->mobile_number)->toBe('9876543210');
    });

    it('rejects a malformed number before calling the provider', function (mixed $mobile) {
        fakeAreachSms();

        $this->postJson('/api/v1/mobile-otp/send', otpSendPayload(['mobile_number' => $mobile]))
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonPath('errors.mobile_number.0', 'Please enter a valid 10-digit mobile number.');

        expect(MobileOtpChallenge::query()->count())->toBe(0);
        Http::assertNothingSent();
    })->with(['12345', '5876543210', '98765432101', 'abcdefghij', [['9876543210']]]);

    it('reports every missing field at once', function () {
        fakeAreachSms();

        $this->postJson('/api/v1/mobile-otp/send', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mobile_number', 'purpose']);

        Http::assertNothingSent();
    });

    it('refuses an unsupported country or purpose', function (array $overrides, string $field, string $message) {
        fakeAreachSms();

        $this->postJson('/api/v1/mobile-otp/send', otpSendPayload($overrides))
            ->assertUnprocessable()
            ->assertJsonPath("errors.{$field}.0", $message);

        Http::assertNothingSent();
    })->with([
        'country' => [['country_code' => 'US'], 'country_code', 'Only Indian mobile numbers (country_code "IN") are supported.'],
        'purpose' => [['purpose' => 'password_reset'], 'purpose', 'The purpose is not recognised.'],
    ]);

    it('holds a resend until the cooldown has passed', function () {
        fakeAreachSms();
        $this->postJson('/api/v1/mobile-otp/send', otpSendPayload())->assertCreated();
        $this->travel(20)->seconds();

        $this->postJson('/api/v1/mobile-otp/send', otpSendPayload())
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', '40')
            ->assertJson([
                'success' => false,
                'error_code' => 'resend_cooldown',
                'data' => ['retry_after_seconds' => 40],
            ]);

        $this->travel(41)->seconds();
        $this->postJson('/api/v1/mobile-otp/send', otpSendPayload())->assertCreated();
        Http::assertSentCount(2);
    });

    it('stops texting a number after the hourly cap', function () {
        fakeAreachSms();
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/mobile-otp/send', otpSendPayload())->assertCreated();
            $this->travel(61)->seconds();
        }

        $this->postJson('/api/v1/mobile-otp/send', otpSendPayload())
            ->assertTooManyRequests()
            ->assertJsonPath('error_code', 'too_many_requests');

        Http::assertSentCount(5);
    });

    it('records a failed send and says no code was sent', function () {
        fakeAreachSms(Http::response(['status' => 'error'], 500));

        $this->postJson('/api/v1/mobile-otp/send', otpSendPayload())
            ->assertServiceUnavailable()
            ->assertExactJson([
                'success' => false,
                'message' => 'We could not send a verification code right now. Please try again shortly.',
                'error_code' => 'sms_not_sent',
            ]);

        expect(MobileOtpChallenge::query()->sole())
            ->status->toBe(MobileOtpStatus::Failed)
            ->sms_status->toBe(SmsSendStatus::Failed)
            ->sms_failure_reason->toBe(SmsFailureReason::HttpError);
    });

    it('reports an unconfirmed send honestly rather than as delivered', function () {
        fakeAreachSms(Http::failedConnection('cURL error 28: Operation timed out'));

        $this->postJson('/api/v1/mobile-otp/send', otpSendPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.sms_status', 'unconfirmed');
    });

    it('is throttled per ip and exempt from csrf', function () {
        $route = app('router')->getRoutes()->getByName('api.v1.mobile-otp.send');

        expect($route->uri())->toBe('api/v1/mobile-otp/send')
            ->and($route->gatherMiddleware())->toContain('api')->toContain('throttle:mobile-otp-send')->not->toContain('web');
    });
});

describe('verify', function () {
    it('verifies the right code and records when', function () {
        $challenge = pendingChallenge();
        $this->freezeSecond();

        $response = $this->postJson('/api/v1/mobile-otp/verify', otpVerifyPayload($challenge, '123456'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Mobile number verified successfully.')
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.status', 'verified')
            ->assertJsonPath('data.mobile_number_masked', '******3210')
            ->assertJsonPath('data.verified_at', now()->toIso8601String());
        expect($challenge->fresh())->status->toBe(MobileOtpStatus::Verified)->verified_at->not->toBeNull();
    });

    it('verifies a code with a leading zero', function () {
        $challenge = pendingChallenge('012345');

        $this->postJson('/api/v1/mobile-otp/verify', otpVerifyPayload($challenge, '012345'))->assertOk();
    });

    it('rejects a wrong code and counts the attempt', function () {
        $challenge = pendingChallenge();

        $this->postJson('/api/v1/mobile-otp/verify', otpVerifyPayload($challenge, '000000'))
            ->assertUnprocessable()
            ->assertExactJson([
                'success' => false,
                'message' => 'That code is incorrect or has expired. You can request a new one.',
                'error_code' => 'invalid_otp',
                'data' => ['attempts_remaining' => 4],
            ]);

        expect($challenge->fresh())->attempts->toBe(1)->verified_at->toBeNull();
    });

    it('refuses an expired, reused, superseded or exhausted code', function (array $attributes, string $errorCode) {
        $challenge = pendingChallenge(attributes: $attributes);

        $this->postJson('/api/v1/mobile-otp/verify', otpVerifyPayload($challenge, '123456'))
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', $errorCode)
            ->assertJsonMissingPath('data');

        expect($challenge->fresh()->attempts)->toBe($attributes['attempts'] ?? 0);
    })->with([
        'expired' => [['expires_at' => now()->subMinute(), 'status' => MobileOtpStatus::Pending], 'otp_expired'],
        'already used' => [['status' => MobileOtpStatus::Verified, 'verified_at' => now()->subMinute()], 'otp_already_used'],
        'superseded' => [['status' => MobileOtpStatus::Superseded], 'otp_superseded'],
        'attempts exhausted' => [['status' => MobileOtpStatus::Pending, 'attempts' => 5], 'too_many_attempts'],
    ]);

    it('accepts a correct code only once', function () {
        $challenge = pendingChallenge();
        $this->postJson('/api/v1/mobile-otp/verify', otpVerifyPayload($challenge, '123456'))->assertOk();

        $this->postJson('/api/v1/mobile-otp/verify', otpVerifyPayload($challenge, '123456'))
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'otp_already_used');
    });

    it('locks the challenge after the maximum wrong attempts, even for the right code', function () {
        $challenge = pendingChallenge();
        foreach (range(1, 4) as $attempt) {
            $this->postJson('/api/v1/mobile-otp/verify', otpVerifyPayload($challenge, '000000'))->assertJsonPath('error_code', 'invalid_otp');
        }

        $this->postJson('/api/v1/mobile-otp/verify', otpVerifyPayload($challenge, '000000'))->assertJsonPath('error_code', 'too_many_attempts');
        $this->postJson('/api/v1/mobile-otp/verify', otpVerifyPayload($challenge, '123456'))->assertJsonPath('error_code', 'too_many_attempts');

        expect($challenge->fresh())->status->toBe(MobileOtpStatus::Exhausted)->verified_at->toBeNull();
    });

    it('answers a mismatched number, purpose or unknown id exactly like a wrong code, without touching the challenge', function (array $overrides) {
        $challenge = pendingChallenge();

        $this->postJson('/api/v1/mobile-otp/verify', otpVerifyPayload($challenge, '123456', $overrides))
            ->assertUnprocessable()
            ->assertExactJson([
                'success' => false,
                'message' => 'That code is incorrect or has expired. You can request a new one.',
                'error_code' => 'invalid_otp',
            ]);

        expect($challenge->fresh())->attempts->toBe(0)->status->toBe(MobileOtpStatus::Pending);
    })->with([
        'another number' => [['mobile_number' => '9123456780']],
        'another purpose' => [['purpose' => 'loan_application']],
        'unknown challenge' => [['challenge_id' => '0b9a3f4e-1c2d-4e5f-8a9b-0c1d2e3f4a5b']],
    ]);

    it('validates every field', function () {
        $this->postJson('/api/v1/mobile-otp/verify', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['challenge_id', 'mobile_number', 'purpose', 'otp']);
    });

    it('rejects a code that is not exactly six digits', function (string $otp) {
        $challenge = pendingChallenge();

        $this->postJson('/api/v1/mobile-otp/verify', otpVerifyPayload($challenge, $otp))
            ->assertUnprocessable()
            ->assertJsonPath('errors.otp.0', 'Please enter the 6-digit code we sent you.');

        expect($challenge->fresh()->attempts)->toBe(0);
    })->with(['12345', '1234567', '12a456']);

    it('verifies a code issued by the send endpoint, end to end', function () {
        fakeAreachSms();
        $challengeId = $this->postJson('/api/v1/mobile-otp/send', otpSendPayload())->json('data.challenge_id');

        $this->postJson('/api/v1/mobile-otp/verify', [...otpSendPayload(), 'challenge_id' => $challengeId, 'otp' => lastTextedOtp()])
            ->assertOk()
            ->assertJsonPath('data.verified', true);
    });
});
