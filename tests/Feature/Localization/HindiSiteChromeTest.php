<?php

use App\Enums\LoanCategory;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Support\Formatting\IndianNumberFormatter;
use App\Support\Localization\SiteLocale;

beforeEach(function () {
    $this->withUnencryptedCookie(SiteLocale::COOKIE, 'hi');
});

it('renders the header navigation and its champagne Credit Score pill in Hindi', function () {
    $html = $this->get('/')
        ->assertOk()
        ->assertSee('कैलकुलेटर')
        ->assertSee('लोन ईएमआई कैलकुलेटर')
        ->assertSee('होम लोन ईएमआई कैलकुलेटर')
        ->assertSee('सभी कैलकुलेटर देखें →')
        ->assertSee('मुफ़्त CIBIL स्कोर')
        ->assertSee('हमसे बात करें')
        ->assertSee('पात्रता जांचें')
        ->assertDontSee('View all calculators')
        ->assertDontSee('Toggle navigation menu')
        ->getContent();

    expect(preg_match_all('/<button[^>]*class="[^"]*nav-champagne[^"]*"[^>]*>\s*<span class="nav-champagne-badge[^"]*">मुफ़्त<\/span>\s*(<span[^>]*>)?क्रेडिट स्कोर/u', $html))->toBe(2);
});

it('lays out the By Location group of the Loans mega menu as a city grid in Hindi', function () {
    $product = LoanProduct::factory()->published()->create(['slug' => 'personal-loan', 'category' => LoanCategory::PersonalLoan]);
    LoanLandingPage::factory()->published()->inCity('Delhi')->for($product, 'loanProduct')->create([
        'slug' => 'personal-loan-in-delhi',
        'title' => 'Personal Loan in Delhi',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('पर्सनल लोन')
        ->assertSee('शहर के अनुसार')
        ->assertSee('class="mt-2 grid grid-cols-2 gap-x-2"', false)
        ->assertDontSee('By Location');
});

it('renders the footer chrome and its default settings copy in Hindi', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('लोन को आसान, भरोसे को मज़बूत')
        ->assertSee('इस साइट पर दिखाए गए पात्रता नतीजे सिर्फ़ अनुमान हैं, मंज़ूरी की गारंटी नहीं।')
        ->assertSee('सर्वाधिकार सुरक्षित।')
        ->assertDontSee('Simplifying Loan, Amplifying Trust')
        ->assertDontSee('All rights reserved.');
});

it('prefers the admin Hindi wording of a footer setting and keeps brand names as set', function () {
    Setting::set('footer_legal_name', 'Acme Loans Advisory Pvt Ltd');
    Setting::set('site_tagline', 'Loans made simple');
    Setting::set('site_tagline_hi', 'लोन हुआ आसान');
    Setting::set('contact_address', 'Sector 2, Noida');
    Setting::set('contact_address_hi', 'सेक्टर 2, नोएडा');

    $this->get('/')
        ->assertOk()
        ->assertSee('लोन हुआ आसान')
        ->assertSee('सेक्टर 2, नोएडा')
        ->assertSee('Acme Loans Advisory Pvt Ltd')
        ->assertDontSee('Loans made simple')
        ->assertDontSee('Sector 2, Noida');
});

it('asks for cookie consent in Hindi with one whole sentence', function () {
    Setting::set('cookie_consent_enabled', true);
    Setting::set('analytics_consent_required', true);
    Setting::set('cookie_policy_url', 'https://example.com/cookies');

    $this->get('/')
        ->assertOk()
        ->assertSee('हम इस साइट को चलाने के लिए ज़रूरी कुकीज़ का इस्तेमाल करते हैं। आपकी अनुमति से हम एनालिटिक्स कुकीज़ का भी इस्तेमाल करते हैं')
        ->assertSee('सभी स्वीकार करें')
        ->assertSee('सिर्फ़ ज़रूरी')
        ->assertSee('>कुकी नीति<', false)
        ->assertDontSee('Accept all');
});

it('renders the shared marketing components in Hindi', function () {
    app()->setLocale('hi');

    $this->blade('<x-site.repayment-stages :initial-tenure-months="12" :subsequent-tenure-months="60" />')
        ->assertSee('आपका भुगतान कैसे बदलता है')
        ->assertSee('महीने 1–12')
        ->assertSee('महीने 13–72')
        ->assertSee('महीना 72')
        ->assertDontSee('Months');

    $this->blade('<x-site.why-fynnedge />')
        ->assertSee('FynnEdge क्यों?')
        ->assertSee('आप सीधे बैंक भी जा सकते हैं। फिर हम क्यों?')
        ->assertSee('कोई सर्विस चार्ज नहीं');
});

it('writes compact amounts with the Hindi scale words while keeping English unchanged', function () {
    expect(IndianNumberFormatter::compact(5000000))->toBe('50 Lakh')
        ->and(IndianNumberFormatter::compact(15000000))->toBe('1.5 Crore');

    app()->setLocale('hi');

    expect(IndianNumberFormatter::compact(5000000))->toBe('50 लाख')
        ->and(IndianNumberFormatter::compact(15000000))->toBe('1.5 करोड़')
        ->and(IndianNumberFormatter::compact(75000))->toBe('75,000');
});
