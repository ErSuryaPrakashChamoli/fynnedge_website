<?php

use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\MobileOtp\Enums\MobileOtpPurpose;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

it('walks through mobile, OTP, details and result end to end', function () {
    fakeAreachSms();

    $component = Livewire::test('credit-score-check', ['bureau' => 'cibil'])
        ->assertSet('step', 'mobile')
        ->set('mobileNumber', '9876543210')
        ->call('sendOtp')
        ->assertSet('step', 'otp')
        ->assertDontSee('Demo mode')
        ->assertDontSee(lastTextedOtp());

    $code = lastTextedOtp();

    $component
        ->set('otpCode', $code)
        ->call('verifyOtp')
        ->assertSet('step', 'details')
        ->assertHasNoErrors('otpCode')
        ->set('fullName', 'Jane Doe')
        ->set('dateOfBirth', '1990-01-01')
        ->set('panNumber', 'abcde1234f')
        ->set('consent', true)
        ->call('submitDetails')
        ->assertSet('step', 'result');

    expect($component->get('resultScore'))->toBeGreaterThanOrEqual(650)->toBeLessThanOrEqual(900);

    $check = CreditScoreCheck::query()->latest('id')->first();
    expect($check->mobile_number)->toBe('9876543210');
    expect($check->pan_number)->toBe('ABCDE1234F'); // uppercased server-side
    expect($check->full_name)->toBe('Jane Doe');
    expect($check->bureau->value)->toBe('cibil');
    expect($check->status)->toBe(CreditCheckStatus::Completed);
    expect($check->score)->toBe($component->get('resultScore'));
    expect(MobileOtpChallenge::query()->sole())
        ->purpose->toBe(MobileOtpPurpose::CreditScoreCheck)
        ->consumed_at->not->toBeNull();
});

it('saves the name as per PAN with each word capitalised, however it was typed', function (string $typed, string $saved) {
    fakeAreachSms();
    $component = Livewire::test('credit-score-check', ['bureau' => 'experian'])
        ->set('mobileNumber', '9876543210')
        ->call('sendOtp');

    $component->set('otpCode', lastTextedOtp())
        ->call('verifyOtp')
        ->set('fullName', $typed)
        ->assertSet('fullName', $saved)
        ->set('dateOfBirth', '1990-01-01')
        ->set('panNumber', 'ABCDE1234F')
        ->set('consent', true)
        ->call('submitDetails')
        ->assertSet('step', 'result');

    expect(CreditScoreCheck::query()->sole()->full_name)->toBe($saved);
})->with([
    'all capitals' => ['SURYA PRAKASH CHAMOLI', 'Surya Prakash Chamoli'],
    'all small' => ['surya prakash chamoli', 'Surya Prakash Chamoli'],
    'mixed case with stray spaces' => ['  sURYA   pRAKASH chamoli ', 'Surya Prakash Chamoli'],
    'initials and joined names' => ["s.p. d'souza-RAO", "S.P. D'Souza-Rao"],
]);

it('does not let an unverified OTP reach the details step', function () {
    fakeAreachSms();

    Livewire::test('credit-score-check', ['bureau' => 'cibil'])
        ->set('mobileNumber', '9876543210')
        ->call('sendOtp')
        ->set('otpCode', '000000')
        ->call('verifyOtp')
        ->assertSet('step', 'otp')
        ->assertHasErrors('otpCode');
});

it('rejects an invalid PAN format', function () {
    fakeAreachSms();
    $component = Livewire::test('credit-score-check', ['bureau' => 'cibil'])
        ->set('mobileNumber', '9876543210')
        ->call('sendOtp');

    $component->set('otpCode', lastTextedOtp())
        ->call('verifyOtp')
        ->set('fullName', 'Jane Doe')
        ->set('dateOfBirth', '1990-01-01')
        ->set('panNumber', 'not-a-pan')
        ->set('consent', true)
        ->call('submitDetails')
        ->assertHasErrors('panNumber')
        ->assertSet('step', 'details');
});

it('requires consent before submitting', function () {
    fakeAreachSms();
    $component = Livewire::test('credit-score-check', ['bureau' => 'cibil'])
        ->set('mobileNumber', '9876543210')
        ->call('sendOtp');

    $component->set('otpCode', lastTextedOtp())
        ->call('verifyOtp')
        ->set('fullName', 'Jane Doe')
        ->set('dateOfBirth', '1990-01-01')
        ->set('panNumber', 'ABCDE1234F')
        ->set('consent', false)
        ->call('submitDetails')
        ->assertHasErrors('consent')
        ->assertSet('step', 'details');
});

it('defaults to CIBIL for an unrecognised bureau slug', function () {
    Livewire::test('credit-score-check', ['bureau' => 'not-a-real-bureau'])
        ->assertSet('bureau', 'cibil');
});

it('cannot be pushed past the OTP step from the browser', function (string $property, mixed $value) {
    Livewire::test('credit-score-check', ['bureau' => 'cibil'])->set($property, $value);
})->with([
    'step' => ['step', 'details'],
    'verified number' => ['verifiedMobileNumber', '9876543210'],
    'challenge' => ['otpChallengeId', 'any-challenge-id'],
])->throws(CannotUpdateLockedPropertyException::class);

it('does not verify a different number typed in after the code was sent', function () {
    fakeAreachSms();
    $component = Livewire::test('credit-score-check', ['bureau' => 'cibil'])
        ->set('mobileNumber', '9876543210')
        ->call('sendOtp');

    $component->set('mobileNumber', '9123456780')
        ->set('otpCode', lastTextedOtp())
        ->call('verifyOtp')
        ->assertHasErrors('otpCode')
        ->assertSet('step', 'otp');
});

it('runs the check for the verified number even if the input is changed afterwards', function () {
    fakeAreachSms();
    $component = Livewire::test('credit-score-check', ['bureau' => 'cibil'])
        ->set('mobileNumber', '9876543210')
        ->call('sendOtp');

    $component->set('otpCode', lastTextedOtp())
        ->call('verifyOtp')
        ->set('mobileNumber', '9123456780')
        ->set('fullName', 'Jane Doe')
        ->set('dateOfBirth', '1990-01-01')
        ->set('panNumber', 'ABCDE1234F')
        ->set('consent', true)
        ->call('submitDetails')
        ->assertSet('step', 'result');

    expect(CreditScoreCheck::query()->sole()->mobile_number)->toBe('9876543210');
});

it('sends the visitor back to verify once the verification has gone stale', function () {
    fakeAreachSms();
    $component = Livewire::test('credit-score-check', ['bureau' => 'cibil'])
        ->set('mobileNumber', '9876543210')
        ->call('sendOtp');
    $component->set('otpCode', lastTextedOtp())->call('verifyOtp');
    $this->travel(31)->minutes();

    $component->set('fullName', 'Jane Doe')
        ->set('dateOfBirth', '1990-01-01')
        ->set('panNumber', 'ABCDE1234F')
        ->set('consent', true)
        ->call('submitDetails')
        ->assertSet('step', 'mobile')
        ->assertHasErrors('mobileNumber');

    expect(CreditScoreCheck::query()->count())->toBe(0);
});
