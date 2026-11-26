<?php

use App\Enums\EnquiryType;
use App\Enums\PublishStatus;
use App\Models\ContactEnquiry;
use App\Models\Page;
use App\Models\Setting;
use App\Modules\DebtConsolidation\Enums\ConsentPurpose;
use App\Modules\DebtConsolidation\Enums\DebtApplicationStatus;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Notifications\DebtCaseUpdate;
use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Setting::set(DebtConsolidationSettings::KEY, ['enabled' => true]);
    fakeAreachSms();
});

function fillDebtEnquiry(array $overrides = []): Testable
{
    $component = Livewire::test('debt-consolidation-enquiry', ['source' => 'hub']);

    foreach (['fullName' => 'Asha Rao', 'mobileNumber' => '9876543210', 'city' => 'Pune', 'totalOutstanding' => '800000', 'monthlyEmi' => '32000', 'consent' => true, ...$overrides] as $field => $value) {
        $component->set($field, $value);
    }

    return $component;
}

it('stores nothing until the mobile number is verified', function () {
    fillDebtEnquiry()->call('sendOtp')->assertSet('step', 'otp');

    expect(DebtApplication::query()->count())->toBe(0)
        ->and(ContactEnquiry::query()->count())->toBe(0);
});

it('opens a case, records the enquiry and the contact consent, and signs the customer in once verified', function () {
    $component = fillDebtEnquiry()->call('sendOtp');

    $component->set('otpCode', lastTextedOtp())->call('verifyOtp')->assertSet('step', 'done');

    $application = DebtApplication::query()->sole();
    expect($application)
        ->status->toBe(DebtApplicationStatus::EnquiryReceived)
        ->mobile->toBe('9876543210')
        ->full_name->toBe('Asha Rao')
        ->and((float) $application->declared_total_outstanding)->toBe(800000.0)
        ->and($application->contactEnquiry)->enquiry_type->toBe(EnquiryType::DebtConsolidation)
        ->and($application->contactEnquiry->phone_verified_at)->not->toBeNull()
        ->and($application->consents()->sole()->purpose)->toBe(ConsentPurpose::Contact)
        ->and(Auth::guard('customer')->user()?->is($application->customer))->toBeTrue();
    $component->assertSee($application->reference);
});

it('continues the open case instead of opening a second one for the same number', function () {
    fillDebtEnquiry()->call('sendOtp')->set('otpCode', lastTextedOtp())->call('verifyOtp');
    $this->travel(2)->minutes();

    fillDebtEnquiry(['totalOutstanding' => '900000'])->call('sendOtp')->set('otpCode', lastTextedOtp())->call('verifyOtp');

    expect(DebtApplication::query()->count())->toBe(1)
        ->and((float) DebtApplication::query()->sole()->declared_total_outstanding)->toBe(900000.0)
        ->and(ContactEnquiry::query()->sole()->enquiry_count)->toBe(2);
});

it('refuses a wrong code without opening a case', function () {
    fillDebtEnquiry()->call('sendOtp')->set('otpCode', '000000')->call('verifyOtp')->assertHasErrors('otpCode');

    expect(DebtApplication::query()->count())->toBe(0);
});

it('requires a name, a valid mobile and the contact consent', function () {
    fillDebtEnquiry(['fullName' => '', 'mobileNumber' => '12345', 'consent' => false])
        ->call('sendOtp')
        ->assertHasErrors(['fullName', 'mobileNumber', 'consent']);
});

it('only offers applicant types that are switched on', function () {
    Setting::set(DebtConsolidationSettings::KEY, ['enabled' => true, 'business_enabled' => false]);

    fillDebtEnquiry(['applicantType' => 'business'])->call('sendOtp')->assertHasErrors('applicantType');
});

it('emails case updates to the address given in the enquiry', function () {
    Notification::fake();

    fillDebtEnquiry(['email' => 'asha@example.com'])->call('sendOtp')->set('otpCode', lastTextedOtp())->call('verifyOtp')->assertSet('step', 'done');

    $customer = DebtApplication::query()->sole()->customer;
    Notification::assertSentTo($customer, DebtCaseUpdate::class, fn (DebtCaseUpdate $update, array $channels, object $notifiable): bool => in_array('mail', $channels, true) && $notifiable->routeNotificationFor('mail', $update) === 'asha@example.com');
});

it('checks the details again when the code is verified, so the browser cannot change them in between', function () {
    $component = fillDebtEnquiry()->call('sendOtp')->assertSet('step', 'otp');

    $component->set('consent', false)->set('email', 'not-an-email')->set('applicantType', 'nonsense')
        ->set('otpCode', lastTextedOtp())->call('verifyOtp')
        ->assertHasErrors(['consent', 'email', 'applicantType'])
        ->assertNotSet('step', 'done');

    expect(DebtApplication::query()->count())->toBe(0);
});

it('refuses an enquiry already on screen once the service is switched off, and says so', function () {
    $component = fillDebtEnquiry()->call('sendOtp')->assertSet('step', 'otp');
    Setting::set(DebtConsolidationSettings::KEY, ['enabled' => false]);

    $component->set('otpCode', lastTextedOtp())->call('verifyOtp')->assertHasErrors('application');
    fillDebtEnquiry()->call('sendOtp')->assertHasErrors('application')->assertSee('not accepting new debt consolidation requests');

    expect(DebtApplication::query()->count())->toBe(0);
});

it('offers the detailed assessment after an enquiry only while that page is published', function () {
    $done = fn (string $mobile) => fillDebtEnquiry(['mobileNumber' => $mobile])->call('sendOtp')->set('otpCode', lastTextedOtp())->call('verifyOtp')->assertSet('step', 'done');

    $done('9876500001')->assertDontSee(route('debt-consolidation.assessment'));

    Page::query()->where('slug', 'debt-consolidation-assessment')->update(['status' => PublishStatus::Published, 'published_at' => now()->subMinute()]);

    $done('9876500002')->assertSee(route('debt-consolidation.assessment'));
});
