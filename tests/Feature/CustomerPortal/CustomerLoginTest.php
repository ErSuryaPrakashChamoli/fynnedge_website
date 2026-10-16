<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\MobileOtp\Enums\MobileOtpPurpose;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    fakeAreachSms();
});

function requestLoginCode(string $mobile): Testable
{
    return Livewire::test('customer-login')->set('mobileNumber', $mobile)->call('sendOtp');
}

function surepassCallCount(): int
{
    return collect(Http::recorded())->filter(fn (array $pair): bool => str_contains($pair[0]->url(), 'surepass'))->count();
}

it('signs an existing customer in with a one-time code and opens their dashboard', function () {
    $customer = Customer::factory()->portalAccount('9000000001')->create();

    requestLoginCode('9000000001')
        ->assertSet('step', 'otp')
        ->set('otpCode', lastTextedOtp())
        ->call('verifyOtp')
        ->assertHasNoErrors()
        ->assertRedirect(route('portal.dashboard'));

    expect(Auth::guard('customer')->id())->toBe($customer->id)
        ->and($customer->fresh()->last_login_at)->not->toBeNull()
        ->and(AuditLog::query()->where('auditable_id', $customer->id)->where('action', 'portal_login')->exists())->toBeTrue()
        ->and(MobileOtpChallenge::query()->sole()->purpose)->toBe(MobileOtpPurpose::CustomerLogin);
});

it('answers every number the same way before the code is verified, so it never reveals who has an account', function () {
    Customer::factory()->portalAccount('9000000001')->create();

    $registered = requestLoginCode('9000000001');
    $unknown = requestLoginCode('9000000002');

    $registered->assertSet('step', 'otp')->assertSee('If this number is registered with us');
    $unknown->assertSet('step', 'otp')->assertSee('If this number is registered with us');
    Http::assertSentCount(2);
});

it('tells a visitor who proved their number that it has no profile yet, and opens none', function () {
    requestLoginCode('9000000002')
        ->set('otpCode', lastTextedOtp())
        ->call('verifyOtp')
        ->assertSet('step', 'no_account')
        ->assertSee('There is no profile for this number yet');

    expect(Customer::query()->count())->toBe(0)
        ->and(Auth::guard('customer')->check())->toBeFalse();
});

it('activates the one existing customer record that holds the verified number instead of duplicating it', function () {
    $lead = Customer::factory()->create(['phone' => '9000000003']);

    requestLoginCode('9000000003')->set('otpCode', lastTextedOtp())->call('verifyOtp')->assertRedirect(route('portal.dashboard'));

    expect(Customer::query()->count())->toBe(1)
        ->and($lead->fresh())->verified_mobile->toBe('9000000003')->portal_activated_at->not->toBeNull()
        ->and(Auth::guard('customer')->id())->toBe($lead->id);
});

it('activates no record when several customers share the number', function () {
    Customer::factory()->count(2)->create(['phone' => '9000000003']);

    requestLoginCode('9000000003')->set('otpCode', lastTextedOtp())->call('verifyOtp')->assertSet('step', 'no_account');

    expect(Customer::query()->whereNotNull('verified_mobile')->count())->toBe(0);
});

it('refuses a wrong or expired code', function () {
    Customer::factory()->portalAccount('9000000001')->create();

    requestLoginCode('9000000001')
        ->set('otpCode', lastTextedOtp() === '000000' ? '111111' : '000000')
        ->call('verifyOtp')
        ->assertHasErrors(['otpCode' => 'That code is incorrect or has expired. You can resend a new one.']);

    $component = requestLoginCode('9000000004');
    $this->travel(config('mobile_otp.expiry_minutes') + 1)->minutes();
    $component->set('otpCode', lastTextedOtp())->call('verifyOtp')->assertHasErrors('otpCode');

    expect(Auth::guard('customer')->check())->toBeFalse();
});

it('locks the form after too many wrong codes from one address', function () {
    config(['customer_portal.login_max_attempts' => 2]);
    $customer = Customer::factory()->portalAccount('9000000001')->create();
    $component = requestLoginCode('9000000001');
    $wrong = lastTextedOtp() === '000000' ? '111111' : '000000';

    $component->set('otpCode', $wrong)->call('verifyOtp');
    $component->set('otpCode', $wrong)->call('verifyOtp');
    $component->set('otpCode', lastTextedOtp())->call('verifyOtp')
        ->assertHasErrors(['otpCode'])
        ->assertSee('Too many attempts');

    expect(Auth::guard('customer')->check())->toBeFalse()
        ->and($customer->fresh()->last_login_at)->toBeNull();
});

it('refuses an account staff have disabled', function () {
    Customer::factory()->portalAccount('9000000001')->portalDisabled()->create();

    requestLoginCode('9000000001')->set('otpCode', lastTextedOtp())->call('verifyOtp')
        ->assertHasErrors(['otpCode' => 'Access to your profile is switched off. Please contact us to restore it.']);

    expect(Auth::guard('customer')->check())->toBeFalse();
});

it('signs in repeatedly without ever contacting a credit bureau', function () {
    $customer = Customer::factory()->portalAccount('9000000001')->create();

    foreach (range(1, 2) as $attempt) {
        $this->travel(2)->minutes();
        requestLoginCode('9000000001')->set('otpCode', lastTextedOtp())->call('verifyOtp')->assertRedirect(route('portal.dashboard'));
        $this->get(route('portal.dashboard'))->assertOk();
        Auth::guard('customer')->logout();
    }

    expect(surepassCallCount())->toBe(0)
        ->and(AuditLog::query()->where('auditable_id', $customer->id)->where('action', 'portal_login')->count())->toBe(2);
});

it('sends a signed-out visitor to the Existing Customer page and back to the page they asked for', function () {
    Customer::factory()->portalAccount('9000000001')->create();

    $this->get(route('portal.reports.index'))->assertRedirect(route('customer.login'));

    requestLoginCode('9000000001')->set('otpCode', lastTextedOtp())->call('verifyOtp')
        ->assertRedirect(route('portal.reports.index'));
});

it('never follows a stored destination outside the portal', function () {
    Customer::factory()->portalAccount('9000000001')->create();
    session()->put('url.intended', 'https://evil.example/my-profile');

    requestLoginCode('9000000001')->set('otpCode', lastTextedOtp())->call('verifyOtp')
        ->assertRedirect(route('portal.dashboard'));
});

it('sends a signed-in customer from the sign-in page to their dashboard', function () {
    $this->actingAs(Customer::factory()->portalAccount()->create(), 'customer')
        ->get(route('customer.login'))
        ->assertRedirect(route('portal.dashboard'));
});

it('signs out completely, so the portal is closed afterwards', function () {
    $customer = Customer::factory()->portalAccount()->create();

    $this->actingAs($customer, 'customer')
        ->post(route('portal.logout'))
        ->assertRedirect(route('customer.login'));

    $this->assertGuest('customer');
    $this->get(route('portal.dashboard'))->assertRedirect(route('customer.login'));
});

it('signs a disabled account out at its next request', function () {
    $customer = Customer::factory()->portalAccount()->create();
    Auth::guard('customer')->login($customer);
    $this->get(route('portal.dashboard'))->assertOk();

    Customer::query()->whereKey($customer->id)->update(['portal_disabled_at' => now()]);
    // A real request reloads the account from the session; drop the in-memory copy.
    Auth::guard('customer')->forgetUser();

    $this->get(route('portal.dashboard'))->assertRedirect(route('customer.login'));
    $this->assertGuest('customer');
});

it('keeps the portal private: never cached by the browser and never indexed', function () {
    $this->actingAs(Customer::factory()->portalAccount()->create(), 'customer')
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('content="noindex, nofollow"', false);

    expect($this->get(route('portal.dashboard'))->headers->get('Cache-Control'))->toContain('no-store');
});

it('refuses customer-login codes through the public OTP API', function () {
    $this->postJson(route('api.v1.mobile-otp.send'), [
        'mobile_number' => '9000000001',
        'country_code' => 'IN',
        'purpose' => MobileOtpPurpose::CustomerLogin->value,
    ])->assertUnprocessable()->assertJsonValidationErrors('purpose');

    Http::assertNothingSent();
});

it('never lets a staff session open the customer portal', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get(route('portal.dashboard'))
        ->assertRedirect(route('customer.login'));
});
