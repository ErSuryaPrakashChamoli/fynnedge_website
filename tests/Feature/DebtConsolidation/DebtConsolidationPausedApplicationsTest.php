<?php

use App\Enums\PublishStatus;
use App\Models\Page;
use App\Models\Setting;
use App\Modules\Customers\Models\Customer;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings;
use App\Support\Pages\DebtConsolidationPages;
use Livewire\Livewire;

/*
 * The production pause of 27 Nov 2026: the service stays on (pages live) and
 * both applicant types are switched off, so no new request is accepted.
 */
beforeEach(function () {
    Setting::set(DebtConsolidationSettings::KEY, ['enabled' => true, 'individual_enabled' => false, 'business_enabled' => false]);
    Page::query()->whereIn('slug', DebtConsolidationPages::slugs())->update(['status' => PublishStatus::Published, 'published_at' => now()->subMinute()]);
    fakeAreachSms();
});

it('keeps the debt consolidation pages live while new applications are paused, and says so on the assessment', function () {
    $this->get(route(DebtConsolidationPages::route('hub')))->assertOk();
    $this->get(route(DebtConsolidationPages::route('assessment')))
        ->assertOk()
        ->assertSee('Online assessments are paused')
        ->assertDontSee('Who are you consolidating debt for?');
});

it('refuses a new enquiry and a new application while paused, and creates nothing', function () {
    $enquiry = Livewire::test('debt-consolidation-enquiry', ['source' => 'hub']);

    foreach (['fullName' => 'Asha Rao', 'mobileNumber' => '9876543210', 'city' => 'Pune', 'totalOutstanding' => '800000', 'monthlyEmi' => '32000', 'consent' => true] as $field => $value) {
        $enquiry->set($field, $value);
    }

    $enquiry->call('sendOtp')->assertHasErrors('application')->assertNotSet('step', 'otp');

    Livewire::test('debt-consolidation-application')
        ->call('chooseType', 'individual')->assertHasErrors('application')
        ->call('chooseType', 'business')->assertHasErrors('application');

    expect(DebtApplication::query()->count())->toBe(0);
});

it('still shows an existing customer their case while paused', function () {
    $customer = Customer::factory()->portalAccount('9811100001')->create();
    $case = DebtApplication::factory()->forCustomer($customer)->submitted()->create();

    $this->actingAs($customer, 'customer')
        ->get(route('portal.debt.show', $case))
        ->assertOk();
});
