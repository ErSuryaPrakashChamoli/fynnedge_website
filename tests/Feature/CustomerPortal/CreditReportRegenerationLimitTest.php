<?php

use App\Models\Setting;
use App\Models\User;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\Customers\Models\Customer;
use App\Modules\Equifax\Services\EquifaxSettings;
use App\Modules\Experian\Services\ExperianSettings;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Every check is free; a person gets one NEW bureau report every 3 months and
 * at most 4 a year, per customer and per PAN, across all bureaus (user
 * decision 2026-10-17). Surepass and the SMS gateway are faked.
 */
beforeEach(function () {
    fakeAreachSms();
    fakeSurepassExperian();
    fakeSurepassEquifax();
    Setting::set(ExperianSettings::PUBLIC_SCORE_ENABLED, true);
    Setting::set(EquifaxSettings::PUBLIC_SCORE_ENABLED, true);
});

function limitedCheck(string $bureau = 'experian', string $mobile = '9000000001', string $pan = 'AAAPZ9999Z'): Testable
{
    Auth::guard('customer')->logout();

    return Livewire::test('credit-score-check', ['bureau' => $bureau])
        ->set('mobileNumber', $mobile)->call('sendOtp')
        ->set('otpCode', lastTextedOtp())->call('verifyOtp')
        ->set('fullName', 'Test Fixture')->set('dateOfBirth', '1990-01-01')->set('panNumber', $pan)->set('gender', 'male')->set('consent', true)
        ->call('submitDetails');
}

function bureauPulls(): int
{
    return collect(Http::recorded())->filter(fn (array $pair): bool => str_contains($pair[0]->url(), 'kyc-api.surepass.app'))->count();
}

it('shows the saved score on the same bureau inside the limit, with no new pull', function () {
    limitedCheck();
    $this->travel(40)->days();

    limitedCheck()
        ->assertSet('resultKind', 'live')
        ->assertSet('resultScore', 792)
        ->assertSee('This is your saved Experian score from '.now()->subDays(40)->translatedFormat('d M Y'))
        ->assertSee('your next one is available on '.now()->subDays(40)->addMonths(3)->translatedFormat('d M Y'));

    expect(bureauPulls())->toBe(1);
});

it('refuses another bureau inside the limit before asking it, and keeps the code usable', function () {
    limitedCheck('experian');
    $this->travel(40)->days();

    $page = limitedCheck('equifax')
        ->assertSet('resultKind', null)
        ->assertSee('A new credit report can be generated once every 3 months, up to 4 times a year, across all bureaus.')
        ->assertSee('Your last new report was from Experian on '.now()->subDays(40)->translatedFormat('d M Y'))
        ->assertSee(route('portal.reports.show', CreditScoreCheck::query()->sole()->public_id));

    expect(bureauPulls())->toBe(1)
        ->and(CreditScoreCheck::query()->count())->toBe(1)
        ->and(MobileOtpChallenge::query()->latest('id')->first()->consumed_at)->toBeNull()
        ->and(Auth::guard('customer')->check())->toBeTrue();

    $page->assertSet('step', 'details');
});

it('allows a new report from any bureau once 3 months have passed', function () {
    limitedCheck('experian');
    $this->travel(3)->months();
    $this->travel(1)->days();

    limitedCheck('equifax')->assertSet('resultKind', 'live');

    expect(bureauPulls())->toBe(2);
});

it('caps new reports at the yearly maximum even when the gap allows one', function () {
    config(['customer_portal.regeneration.every_months' => 1, 'customer_portal.regeneration.max_per_year' => 4]);

    foreach (range(1, 4) as $report) {
        limitedCheck();
        $this->travel(1)->months();
        $this->travel(1)->days();
    }

    limitedCheck('equifax')->assertSee('A new credit report can be generated once every 1 months, up to 4 times a year');
    expect(bureauPulls())->toBe(4);

    $this->travel(9)->months();
    limitedCheck('equifax')->assertSet('resultKind', 'live');
    expect(bureauPulls())->toBe(5);
});

it('does not count a check the bureau could not answer', function () {
    Http::swap(new HttpFactory);
    fakeAreachSms();
    fakeSurepassExperian(Http::response(['success' => false, 'message_code' => 'no_record_found'], 422));
    fakeSurepassEquifax();

    limitedCheck('experian')->assertSet('resultKind', 'failed');
    $this->travel(2)->minutes();

    limitedCheck('equifax')->assertSet('resultKind', 'live');
});

it('never limits demo scores', function () {
    Setting::set(ExperianSettings::PUBLIC_SCORE_ENABLED, false);

    limitedCheck();
    $this->travel(2)->minutes();
    limitedCheck()->assertSet('resultKind', 'demo')->assertDontSee('A new credit report can be generated');
});

it('tells a returning customer when their next new report is due as soon as their number is verified', function () {
    limitedCheck();
    $this->travel(40)->days();
    Auth::guard('customer')->logout();

    Livewire::test('credit-score-check', ['bureau' => 'equifax'])
        ->set('mobileNumber', '9000000001')->call('sendOtp')
        ->set('otpCode', lastTextedOtp())->call('verifyOtp')
        ->assertSee('Your next new report is available on '.now()->subDays(40)->addMonths(3)->translatedFormat('d M Y'));
});

it('shows staff when the customer can next get a new report', function () {
    limitedCheck();
    $customer = Customer::query()->sole();

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get("/admin/customers/{$customer->public_id}/edit")
        ->assertOk()
        ->assertSee(['Next new credit report', now()->addMonths(3)->format('d M Y')]);
});

it('keeps every check free: nothing asks for payment', function () {
    limitedCheck()->assertDontSee(['wallet', 'GST', '₹99'], false);
});
