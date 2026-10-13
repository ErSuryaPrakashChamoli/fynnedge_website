<?php

use App\Modules\MobileOtp\Contracts\SmsProvider;
use App\Modules\MobileOtp\DataTransferObjects\SmsSendResult;
use App\Modules\MobileOtp\Enums\SmsFailureReason;
use App\Modules\MobileOtp\Enums\SmsSendStatus;
use App\Modules\MobileOtp\Providers\AreachSmsProvider;
use App\Modules\MobileOtp\Providers\LogSmsProvider;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

function sendAreachOtp(string $message = 'Your verification code is 123456.'): SmsSendResult
{
    return (new AreachSmsProvider)->sendOtp('919876543210', $message);
}

it('posts the json payload to the configured endpoint with the apikey header', function () {
    fakeAreachSms();
    config(['services.areach.sender' => 'TESTID']);

    sendAreachOtp('Welcome to FynnEdge! Your verification code is 012345.');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://api.areach.in/v1/sms'
        && $request->hasHeader('apikey', 'test-areach-key')
        && $request->hasHeader('Content-Type', 'application/json')
        && $request->data() === [
            'sender' => 'TESTID',
            'to' => '919876543210',
            'text' => 'Welcome to FynnEdge! Your verification code is 012345.',
            'type' => 'OTP',
        ]);
});

it('follows the configured base url', function () {
    fakeAreachSms();
    config(['services.areach.base_url' => 'https://api.areach.in/v2/']);

    sendAreachOtp();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.areach.in/v2/sms');
});

it('applies the configured timeout', function () {
    $options = null;
    fakeAreachSms(function (Request $request, array $requestOptions) use (&$options): PromiseInterface {
        $options = $requestOptions;

        return Http::response(['status' => 'success']);
    });
    config(['services.areach.timeout' => 7]);

    sendAreachOtp();

    expect($options)->timeout->toBe(7)->connect_timeout->toBe(5);
});

it('interprets the provider answer without claiming delivery', function (mixed $stub, SmsSendStatus $status, ?SmsFailureReason $reason, ?string $reference, ?int $httpStatus) {
    fakeAreachSms($stub);

    $result = sendAreachOtp();

    expect($result)
        ->status->toBe($status)
        ->failureReason->toBe($reason)
        ->providerReference->toBe($reference)
        ->httpStatus->toBe($httpStatus);
})->with([
    'explicit success with an id' => [fn () => Http::response(['status' => 'success', 'message_id' => 'msg-77']), SmsSendStatus::Accepted, null, 'msg-77', 200],
    'success flag with a nested id' => [fn () => Http::response(['success' => true, 'data' => ['id' => 991]]), SmsSendStatus::Accepted, null, '991', 200],
    'explicit rejection in a 200' => [fn () => Http::response(['status' => 'error', 'message' => 'DLT template mismatch']), SmsSendStatus::Failed, SmsFailureReason::ProviderRejected, null, 200],
    'error key in a 200' => [fn () => Http::response(['error' => 'Invalid sender']), SmsSendStatus::Failed, SmsFailureReason::ProviderRejected, null, 200],
    'unauthorised' => [fn () => Http::response(['message' => 'Invalid API key'], 401), SmsSendStatus::Failed, SmsFailureReason::HttpError, null, 401],
    'server error' => [fn () => Http::response('Bad gateway', 502), SmsSendStatus::Failed, SmsFailureReason::HttpError, null, 502],
    'not json' => [fn () => Http::response('OK', 200), SmsSendStatus::Unconfirmed, SmsFailureReason::InvalidResponse, null, 200],
    'json without a success marker' => [fn () => Http::response(['queued_at' => '2026-10-13T10:00:00Z', 'id' => 'r-1']), SmsSendStatus::Unconfirmed, SmsFailureReason::UnrecognisedResponse, 'r-1', 200],
    'timeout' => [fn () => Http::failedConnection('cURL error 28: Operation timed out after 10000 milliseconds'), SmsSendStatus::Unconfirmed, SmsFailureReason::Timeout, null, null],
    'host unreachable' => [fn () => Http::failedConnection(), SmsSendStatus::Failed, SmsFailureReason::ConnectionFailed, null, null],
]);

it('sends nothing when the api key is not configured', function () {
    fakeAreachSms();
    config(['services.areach.key' => null]);

    $result = sendAreachOtp();

    expect($result)->status->toBe(SmsSendStatus::Failed)->failureReason->toBe(SmsFailureReason::NotConfigured);
    Http::assertNothingSent();
});

it('makes exactly one attempt when the provider fails', function () {
    fakeAreachSms(Http::response(['status' => 'error'], 503));

    sendAreachOtp();

    Http::assertSentCount(1);
});

it('reports the response shape as types only, never values', function () {
    fakeAreachSms(Http::response(['status' => 'success', 'text' => 'Your verification code is 123456.', 'id' => 7]));
    $provider = new AreachSmsProvider;

    $provider->sendOtp('919876543210', 'Your verification code is 123456.');

    expect($provider->lastResponseShape())->toBe(['status' => 'string', 'text' => 'string', 'id' => 'int']);
});

it('resolves the configured provider from the container', function () {
    config(['mobile_otp.sms_provider' => 'areach']);

    expect(app(SmsProvider::class))->toBeInstanceOf(AreachSmsProvider::class);
});

it('refuses an unknown provider name instead of falling back', function () {
    config(['mobile_otp.sms_provider' => 'demo']);

    expect(fn () => app(SmsProvider::class))->toThrow(InvalidArgumentException::class, 'Unknown mobile OTP SMS provider [demo]');
});

it('lets the log provider run only in a local or testing environment', function () {
    Log::spy();
    app()->detectEnvironment(fn () => 'production');

    $result = (new LogSmsProvider)->sendOtp('919876543210', 'Your verification code is 123456.');

    expect($result)->status->toBe(SmsSendStatus::Failed)->failureReason->toBe(SmsFailureReason::NotConfigured);
    Log::shouldNotHaveReceived('info');
    Log::shouldHaveReceived('critical')->withArgs(fn (string $message) => ! str_contains($message, '123456'));
});

it('keeps any real areach key out of the test environment', function () {
    expect(env('AREACH_API_KEY'))->toBeEmpty()
        ->and(config('mobile_otp.sms_provider'))->toBe('log');
});
