<?php

use App\Modules\MobileOtp\Actions\RequestMobileOtp;
use App\Modules\MobileOtp\Actions\VerifyMobileOtp;
use App\Modules\MobileOtp\Contracts\SmsProvider;
use App\Modules\MobileOtp\Enums\MobileOtpPurpose;
use App\Modules\MobileOtp\Enums\MobileOtpStatus;
use App\Modules\MobileOtp\Enums\MobileOtpVerificationResult;
use App\Modules\MobileOtp\Enums\SmsFailureReason;
use App\Modules\MobileOtp\Enums\SmsSendStatus;
use App\Modules\MobileOtp\Exceptions\MobileOtpThrottled;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

function requestOtp(string $mobile = '9876543210', MobileOtpPurpose $purpose = MobileOtpPurpose::LoanEnquiry, ?string $ip = '127.0.0.1'): MobileOtpChallenge
{
    return app(RequestMobileOtp::class)->handle($mobile, $purpose, $ip);
}

it('creates a challenge with a hashed code and a 10-minute expiry', function () {
    fakeAreachSms();
    $this->freezeSecond();

    $challenge = requestOtp();

    expect($challenge)
        ->mobile_number->toBe('9876543210')
        ->purpose->toBe(MobileOtpPurpose::LoanEnquiry)
        ->status->toBe(MobileOtpStatus::Pending)
        ->ip_address->toBe('127.0.0.1')
        ->max_attempts->toBe(5);
    expect($challenge->expires_at->equalTo(now()->addMinutes(10)))->toBeTrue();
    expect(Hash::check(lastTextedOtp(), $challenge->otp_hash))->toBeTrue();
});

it('never stores the OTP in plaintext', function () {
    fakeAreachSms();

    $challenge = requestOtp();

    expect($challenge->otp_hash)->not->toBe(lastTextedOtp())
        ->and($challenge->fresh()->toJson())->not->toContain(lastTextedOtp())
        ->and($challenge->fresh()->toArray())->not->toHaveKey('otp_hash');
});

it('generates a numeric code of the configured length', function (int $length) {
    fakeAreachSms();
    config(['mobile_otp.length' => $length]);

    requestOtp();

    expect(lastTextedOtp())->toMatch('/^\d{'.$length.'}$/');
})->with([6, 8]);

it('keeps the leading zeroes of a small random draw', function () {
    fakeAreachSms();
    app()->bind(RequestMobileOtp::class, fn () => new class(app(SmsProvider::class)) extends RequestMobileOtp
    {
        protected function randomNumberBelow(int $ceiling): int
        {
            return 42;
        }
    });

    $challenge = requestOtp();

    expect(lastTextedOtp())->toBe('000042')
        ->and(Hash::check('000042', $challenge->otp_hash))->toBeTrue();
});

it('expires the code after the configured number of minutes', function () {
    fakeAreachSms();
    config(['mobile_otp.expiry_minutes' => 3]);
    $this->freezeSecond();

    $challenge = requestOtp();

    expect($challenge->expires_at->equalTo(now()->addMinutes(3)))->toBeTrue();
    Http::assertSent(fn (Request $request) => str_contains($request['text'], 'Valid for 3 minutes'));
});

it('builds the sms text from the configured template', function () {
    fakeAreachSms();
    config(['mobile_otp.message' => 'Code :code, valid :minutes min.']);

    requestOtp();

    Http::assertSent(fn (Request $request) => $request['text'] === 'Code '.lastTextedOtp().', valid 10 min.');
});

it('throws after five requests for the same number within an hour', function () {
    fakeAreachSms();

    for ($i = 0; $i < 5; $i++) {
        requestOtp();
        $this->travel(61)->seconds();
    }

    expect(fn () => requestOtp())->toThrow(MobileOtpThrottled::class, 'Too many OTP requests for this number.');
});

it('rate-limits per mobile number, not globally', function () {
    fakeAreachSms();

    for ($i = 0; $i < 5; $i++) {
        requestOtp();
        $this->travel(61)->seconds();
    }

    // A different number is unaffected by the first number's limit.
    expect(requestOtp('9123456780'))->toBeInstanceOf(MobileOtpChallenge::class);
});

it('caps how many codes one ip address can request across numbers', function () {
    fakeAreachSms();
    config(['mobile_otp.max_sends_per_ip_per_hour' => 2]);

    requestOtp('9000000001');
    requestOtp('9000000002');

    expect(fn () => requestOtp('9000000003'))->toThrow(MobileOtpThrottled::class, 'Too many OTP requests.');
    Http::assertSentCount(2);
});

it('refuses a resend inside the cooldown and allows it once the cooldown has passed', function () {
    fakeAreachSms();
    requestOtp();
    $this->travel(30)->seconds();

    expect(fn () => requestOtp())->toThrow(MobileOtpThrottled::class, 'Please wait 30 seconds');

    $this->travel(31)->seconds();

    expect(requestOtp())->toBeInstanceOf(MobileOtpChallenge::class);
    Http::assertSentCount(2);
});

it('does not start a cooldown when the provider rejected the send', function () {
    fakeAreachSms(Http::sequence()->push(['status' => 'error'], 200)->push(['status' => 'success'], 200));

    $failed = requestOtp();
    $retried = requestOtp();

    expect($failed->status)->toBe(MobileOtpStatus::Failed)
        ->and($retried->sms_status)->toBe(SmsSendStatus::Accepted);
});

it('supersedes the earlier pending code for the same number and purpose only', function () {
    fakeAreachSms();
    $first = requestOtp();
    $otherPurpose = requestOtp(purpose: MobileOtpPurpose::CreditScoreCheck);
    $this->travel(61)->seconds();

    $latest = requestOtp();

    expect($first->fresh()->status)->toBe(MobileOtpStatus::Superseded)
        ->and($otherPurpose->fresh()->status)->toBe(MobileOtpStatus::Pending)
        ->and($latest->status)->toBe(MobileOtpStatus::Pending);
});

it('sends nothing while another request for the same number holds the lock', function () {
    fakeAreachSms();
    $lock = Cache::lock('mobile-otp-request:9876543210', 10);
    $lock->get();

    expect(fn () => requestOtp())->toThrow(MobileOtpThrottled::class);

    expect(MobileOtpChallenge::query()->count())->toBe(0);
    Http::assertNothingSent();
    $lock->release();
});

it('records an accepted send with the provider reference', function () {
    fakeAreachSms();
    $this->freezeSecond();

    $challenge = requestOtp()->fresh();

    expect($challenge)
        ->status->toBe(MobileOtpStatus::Pending)
        ->sms_provider->toBe('areach')
        ->sms_status->toBe(SmsSendStatus::Accepted)
        ->sms_provider_reference->toBe('areach-msg-1')
        ->sms_http_status->toBe(200)
        ->sms_failure_reason->toBeNull();
    expect($challenge->sms_sent_at->equalTo(now()))->toBeTrue();
});

it('records a rejected send as failed and the code can never verify', function () {
    fakeAreachSms(Http::response(['message' => 'Invalid API key'], 401));

    $challenge = requestOtp()->fresh();

    expect($challenge)
        ->status->toBe(MobileOtpStatus::Failed)
        ->sms_status->toBe(SmsSendStatus::Failed)
        ->sms_failure_reason->toBe(SmsFailureReason::HttpError)
        ->sms_http_status->toBe(401)
        ->sms_sent_at->toBeNull();
    expect(app(VerifyMobileOtp::class)->handle($challenge, lastTextedOtp()))->toBe(MobileOtpVerificationResult::NotSent);
});

it('keeps a timed-out send pending as unconfirmed, without retrying', function () {
    fakeAreachSms(Http::failedConnection('cURL error 28: Operation timed out after 10001 milliseconds'));

    $challenge = requestOtp()->fresh();

    expect($challenge)
        ->status->toBe(MobileOtpStatus::Pending)
        ->sms_status->toBe(SmsSendStatus::Unconfirmed)
        ->sms_failure_reason->toBe(SmsFailureReason::Timeout);
    Http::assertSentCount(1);
});

it('logs a failed send without the code, the number or the api key', function () {
    fakeAreachSms(Http::response(['status' => 'error'], 500));
    Log::spy();

    requestOtp();

    Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) {
        $logged = $message.json_encode($context);

        return $context['mobile'] === '******3210'
            && $context['reason'] === 'http_error'
            && ! str_contains($logged, lastTextedOtp())
            && ! str_contains($logged, '9876543210')
            && ! str_contains($logged, 'test-areach-key');
    });
});

it('rejects a number that was not normalised and validated first', function () {
    expect(fn () => requestOtp('+91 98765 43210'))->toThrow(InvalidArgumentException::class);
});
