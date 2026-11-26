<?php

use App\Enums\PublishStatus;
use App\Models\Page;
use App\Models\Setting;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings;
use App\Support\Localization\SiteLocale;
use Livewire\Livewire;

beforeEach(function () {
    Setting::set(DebtConsolidationSettings::KEY, ['enabled' => true]);
    Page::query()->where('slug', 'debt-consolidation')->update(['status' => PublishStatus::Published, 'published_at' => now()->subMinute()]);
});

it('renders the hub\'s shared sections and enquiry form in Hindi', function () {
    $this->withUnencryptedCookie(SiteLocale::COOKIE, 'hi')
        ->get('/debt-consolidation')
        ->assertOk()
        ->assertSee('मुफ़्त आकलन, कोई बाध्यता नहीं')
        ->assertSee('डेट कंसॉलिडेशन सलाहकार से बात करें')
        ->assertDontSee('Free, no-obligation assessment');
});

it('runs the calculator in Hindi', function () {
    app()->setLocale('hi');

    Livewire::test('debt-consolidation-calculator')
        ->set('liabilities.0.outstanding', '500000')
        ->set('liabilities.0.rate', '18')
        ->set('liabilities.0.months', '36')
        ->assertSee('आपके मौजूदा लोन और कार्ड')
        ->assertDontSee('Your existing loans and cards');
});

it('stores the enquiry in English for a Hindi visitor', function () {
    fakeAreachSms();
    app()->setLocale('hi');

    Livewire::test('debt-consolidation-enquiry')
        ->set('fullName', 'आशा राव')->set('mobileNumber', '9876500001')->set('consent', true)
        ->call('sendOtp')->set('otpCode', lastTextedOtp())->call('verifyOtp');

    $application = DebtApplication::query()->sole();
    expect($application->contactEnquiry->enquiry_source)->toBe('Personal Debt Consolidation')
        ->and($application->consents()->sole()->consent_text)->toStartWith('I agree that FynnEdge may contact me');
});
