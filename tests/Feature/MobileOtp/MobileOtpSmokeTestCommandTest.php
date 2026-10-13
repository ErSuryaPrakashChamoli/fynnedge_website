<?php

use App\Modules\MobileOtp\Actions\RequestMobileOtp;
use App\Modules\MobileOtp\Contracts\SmsProvider;
use App\Modules\MobileOtp\Enums\MobileOtpStatus;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

/**
 * The command asks for the code that "arrived on the phone", so the test pins
 * the generator to a known draw and answers with it.
 */
function pinOtpDraw(int $draw): void
{
    app()->bind(RequestMobileOtp::class, fn ($app, array $parameters) => new class($parameters['sms'] ?? app(SmsProvider::class), $draw) extends RequestMobileOtp
    {
        public function __construct(SmsProvider $sms, private int $draw)
        {
            parent::__construct($sms);
        }

        protected function randomNumberBelow(int $ceiling): int
        {
            return $this->draw;
        }
    });
}

it('is blocked and sends nothing unless every live-test gate is open', function (array $config, string $reason) {
    fakeAreachSms();
    config(['mobile_otp.live_test_enabled' => true, ...$config]);

    $this->artisan('mobile-otp:smoke-test', ['mobile' => '9876543210', '--confirm' => true])
        ->expectsOutputToContain($reason)
        ->assertFailed();

    Http::assertNothingSent();
    expect(MobileOtpChallenge::query()->count())->toBe(0);
})->with([
    'flag off' => [['mobile_otp.live_test_enabled' => false], 'MOBILE_OTP_LIVE_TEST_ENABLED is not true'],
    'log provider' => [['mobile_otp.sms_provider' => 'log'], 'MOBILE_OTP_SMS_PROVIDER is not "areach"'],
    'no key' => [['services.areach.key' => ''], 'AREACH_API_KEY is not set'],
]);

it('sends nothing when the operator does not confirm', function () {
    fakeAreachSms();
    config(['mobile_otp.live_test_enabled' => true]);

    $this->artisan('mobile-otp:smoke-test', ['mobile' => '9876543210'])
        ->expectsConfirmation('This sends ONE real SMS to the number above. Continue?', 'no')
        ->assertFailed();

    Http::assertNothingSent();
});

it('sends one sms and verifies the code the operator received', function () {
    fakeAreachSms();
    config(['mobile_otp.live_test_enabled' => true]);
    pinOtpDraw(4242);

    $this->artisan('mobile-otp:smoke-test', ['mobile' => '9876543210', '--confirm' => true])
        ->expectsQuestion('Enter the code that arrived on the phone (leave blank to verify later through the API instead)', '004242')
        ->expectsOutputToContain('Live round trip passed')
        ->assertSuccessful();

    Http::assertSentCount(1);
    expect(MobileOtpChallenge::query()->sole())
        ->status->toBe(MobileOtpStatus::Verified)
        ->verified_at->not->toBeNull();
});

it('stops after a rejected send without retrying', function () {
    fakeAreachSms(Http::response(['status' => 'error'], 401));
    config(['mobile_otp.live_test_enabled' => true]);

    $this->artisan('mobile-otp:smoke-test', ['mobile' => '9876543210', '--confirm' => true])
        ->expectsOutputToContain('Not retrying')
        ->assertFailed();

    Http::assertSentCount(1);
});

it('never prints the code or the api key, and offers the api verify call instead', function () {
    fakeAreachSms();
    config(['mobile_otp.live_test_enabled' => true]);
    pinOtpDraw(918273);

    Artisan::call('mobile-otp:smoke-test', ['mobile' => '9876543210', '--confirm' => true, '--no-interaction' => true]);

    expect(Artisan::output())
        ->toContain('/api/v1/mobile-otp/verify')
        ->toContain('<OTP>')
        ->not->toContain('918273')
        ->not->toContain('test-areach-key');
});
