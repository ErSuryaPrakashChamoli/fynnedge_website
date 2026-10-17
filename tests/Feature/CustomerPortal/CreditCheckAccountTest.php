<?php

use App\Models\Setting;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\Customers\Models\Customer;
use App\Modules\Equifax\Enums\Gender;
use App\Modules\Experian\Models\ExperianCreditRequest;
use App\Modules\Experian\Services\ExperianSettings;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The public credit-score check opening (or reusing) a customer-portal account
 * for the OTP-verified mobile, with Surepass Experian and the SMS gateway faked.
 */
function portalExperianLive(mixed $response = null): void
{
    fakeAreachSms();
    fakeSurepassExperian($response);
    Setting::set(ExperianSettings::PUBLIC_SCORE_ENABLED, true);
}

function runPortalExperianCheck(string $mobile = '9000000001', string $pan = 'AAAPZ9999Z', string $context = 'public'): Testable
{
    return Livewire::test('credit-score-check', ['bureau' => 'experian', 'context' => $context])
        ->set('mobileNumber', $mobile)
        ->call('sendOtp')
        ->set('otpCode', lastTextedOtp())
        ->call('verifyOtp')
        ->set('fullName', 'Test Fixture')
        ->set('dateOfBirth', '1990-01-01')
        ->set('panNumber', $pan)
        ->set('consent', true)
        ->call('submitDetails');
}

it('opens an account for a new visitor, saves the check to it and signs them in', function () {
    portalExperianLive();

    runPortalExperianCheck()
        ->assertSet('resultKind', 'live')
        ->assertSet('accountStatus', 'signed_in')
        ->assertSee('Saved to your FynnOn by Markedge Technologies profile')
        ->assertSeeHtml('href="'.route('portal.dashboard').'" target="_blank" rel="noopener"');

    $customer = Customer::query()->sole();
    $check = CreditScoreCheck::query()->sole();

    expect($customer)
        ->verified_mobile->toBe('9000000001')
        ->portal_activated_at->not->toBeNull()
        ->email->toBeNull()
        ->and(Auth::guard('customer')->id())->toBe($customer->id)
        ->and($check->customer_id)->toBe($customer->id)
        ->and(ExperianCreditRequest::query()->sole()->customer_id)->toBe($customer->id);
});

it('remembers the details the bureau matched as customer-provided profile data, PAN encrypted', function () {
    portalExperianLive();

    runPortalExperianCheck();

    $customer = Customer::query()->sole();
    expect($customer)
        ->full_name->toBe('Test Fixture')
        ->date_of_birth->toDateString()->toBe('1990-01-01')
        ->pan_masked->toBe('XXXXXX999Z')
        ->identity_verified_at->toBeNull()
        ->and($customer->pan())->toBe('AAAPZ9999Z')
        ->and($customer->getRawOriginal('pan_encrypted'))->not->toContain('AAAPZ9999Z');
});

it('never overwrites what the profile already holds, nor a verified identity', function () {
    portalExperianLive();
    $customer = Customer::factory()->portalAccount('9000000001')->create([
        'full_name' => 'Asha Rao',
        'gender' => Gender::Female,
        'identity_verified_at' => now(),
    ]);

    runPortalExperianCheck();

    expect($customer->fresh())->full_name->toBe('Asha Rao')->date_of_birth->toBeNull()->pan_masked->toBeNull();
});

it('keeps one account per verified mobile across repeat checks', function () {
    portalExperianLive();

    runPortalExperianCheck();
    Auth::guard('customer')->logout();
    $this->travel(2)->minutes();
    runPortalExperianCheck();

    expect(Customer::query()->count())->toBe(1)
        ->and(CreditScoreCheck::query()->pluck('customer_id')->unique()->all())->toBe([Customer::query()->sole()->id]);
});

it('records a second person on a shared browser against their own account, never the signed-in one', function () {
    portalExperianLive();
    $first = Customer::factory()->portalAccount('9000000001')->create();
    Auth::guard('customer')->login($first);

    runPortalExperianCheck('9000000002', 'BBBPZ9999Z')->assertSet('accountStatus', 'signed_in');

    $second = Customer::query()->where('verified_mobile', '9000000002')->sole();
    expect(CreditScoreCheck::query()->sole()->customer_id)->toBe($second->id)
        ->and(Auth::guard('customer')->id())->toBe($second->id);
});

it('shows the signed-in customer\'s details only once their own number is verified', function () {
    portalExperianLive();
    $customer = Customer::factory()->portalAccount('9000000001')->create(['full_name' => 'Asha Rao']);
    $customer->setPan('AAAPZ9999Z');
    $customer->save();
    Auth::guard('customer')->login($customer);

    $page = Livewire::test('credit-score-check', ['bureau' => 'experian'])
        ->assertSet('mobileNumber', '')
        ->assertSet('panNumber', '');

    $page->set('mobileNumber', '9000000001')->call('sendOtp')->set('otpCode', lastTextedOtp())->call('verifyOtp')
        ->assertSet('fullName', 'Asha Rao')
        ->assertSet('panNumber', 'AAAPZ9999Z');
});

it('does not save a demo score to the profile as a real one', function () {
    fakeAreachSms();

    Livewire::test('credit-score-check', ['bureau' => 'experian'])
        ->set('mobileNumber', '9000000001')->call('sendOtp')->set('otpCode', lastTextedOtp())->call('verifyOtp')
        ->set('fullName', 'Test Fixture')->set('dateOfBirth', '1990-01-01')->set('panNumber', 'AAAPZ9999Z')->set('consent', true)
        ->call('submitDetails')
        ->assertSet('resultKind', 'demo')
        ->assertDontSee('Saved to your');

    $customer = Customer::query()->sole();
    expect($customer->pan_masked)->toBeNull();

    $this->actingAs($customer, 'customer')
        ->get(route('portal.reports.index'))
        ->assertOk()
        ->assertSee('No credit reports yet');
});

it('keeps a failed check out of the profile details but in the customer\'s history', function () {
    portalExperianLive(Http::response(['success' => false, 'message_code' => 'no_record_found'], 422));

    runPortalExperianCheck()->assertSet('resultKind', 'failed')->assertSet('accountStatus', 'signed_in');

    $customer = Customer::query()->sole();
    expect($customer->full_name)->toBeNull()
        ->and($customer->creditScoreChecks()->sole()->status->value)->toBe('failed');
});

it('sends a portal refresh to the registered mobile whatever the browser posts', function () {
    portalExperianLive();
    $customer = Customer::factory()->portalAccount('9000000001')->create();
    Auth::guard('customer')->login($customer);

    Livewire::test('credit-score-check', ['bureau' => 'experian', 'context' => 'portal'])
        ->assertSee('+91 ******0001')
        ->set('mobileNumber', '9000000099')
        ->call('sendOtp')
        ->assertSet('step', 'otp');

    expect(MobileOtpChallenge::query()->sole()->mobile_number)->toBe('9000000001');
});
