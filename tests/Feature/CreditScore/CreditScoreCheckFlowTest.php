<?php

use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use Livewire\Livewire;

it('walks through mobile, OTP, details and result end to end', function () {
    $component = Livewire::test('credit-score-check', ['bureau' => 'cibil'])
        ->assertSet('step', 'mobile')
        ->set('mobileNumber', '9876543210')
        ->call('sendOtp')
        ->assertSet('step', 'otp')
        ->assertSet('demoOtpCode', fn (?string $code) => strlen((string) $code) === 6);

    $code = $component->get('demoOtpCode');

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
});

it('does not let an unverified OTP reach the details step', function () {
    Livewire::test('credit-score-check', ['bureau' => 'cibil'])
        ->set('mobileNumber', '9876543210')
        ->call('sendOtp')
        ->set('otpCode', '000000')
        ->call('verifyOtp')
        ->assertSet('step', 'otp')
        ->assertHasErrors('otpCode');
});

it('rejects an invalid PAN format', function () {
    $component = Livewire::test('credit-score-check', ['bureau' => 'cibil'])
        ->set('mobileNumber', '9876543210')
        ->call('sendOtp');

    $component->set('otpCode', $component->get('demoOtpCode'))
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
    $component = Livewire::test('credit-score-check', ['bureau' => 'cibil'])
        ->set('mobileNumber', '9876543210')
        ->call('sendOtp');

    $component->set('otpCode', $component->get('demoOtpCode'))
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
