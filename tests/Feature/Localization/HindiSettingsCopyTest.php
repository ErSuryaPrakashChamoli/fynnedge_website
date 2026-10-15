<?php

use App\Enums\LoanCategory;
use App\Filament\Pages\AboutPageSettings;
use App\Filament\Pages\CalculatorPagesSettings;
use App\Filament\Pages\CreditScorePageSettings;
use App\Filament\Pages\QuickEnquiryPageSettings;
use App\Filament\Pages\Settings;
use App\Models\MarketingSection;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Support\Calculators\CalculatorPagesContent;
use App\Support\Enquiries\EnquiryFormContent;
use App\Support\Enquiries\QuickEnquiryPageContent;
use App\Support\Localization\SiteLocale;
use App\Support\Pages\AboutPageContent;
use App\Support\Pages\CreditScorePageContent;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

beforeEach(function () {
    Page::factory()->published()->create(['slug' => 'about', 'title' => 'About FynnEdge']);
});

it('has Hindi for every built-in default of the admin-editable page copy', function () {
    $hindi = json_decode(File::get(lang_path('hi.json')), true);

    $untranslated = collect([
        AboutPageContent::defaults(),
        CreditScorePageContent::defaults(),
        QuickEnquiryPageContent::defaults(),
        CalculatorPagesContent::defaults(),
    ])
        ->flatten()
        ->filter(fn (mixed $value): bool => is_string($value) && ! str_starts_with($value, '/'))
        ->reject(fn (string $english): bool => isset($hindi[$english]))
        ->values()
        ->all();

    expect($untranslated)->toBe([]);
});

it('renders the built-in page copy in Hindi on pages nobody has reworded', function (string $url, array $hindi, array $english) {
    $this->withUnencryptedCookie(SiteLocale::COOKIE, 'hi')
        ->get($url)
        ->assertOk()
        ->assertSee($hindi)
        ->assertDontSee($english);
})->with([
    'about' => ['/about', ['लोन लेना आसान, पारदर्शी और निष्पक्ष बनाना।', 'असली ज़िम्मेदारी'], ['Make borrowing simple, transparent and fair.', 'Real ownership']],
    'quick enquiry' => ['/quick-enquiry', ['हमें बताएं कि आपको क्या चाहिए।', 'अपना लोन चुनें'], ['Tell us what you need.', 'Pick your loan']],
    'calculator' => ['/calculators/gst', ['जीएसटी कैलकुलेटर', 'मूल राशि में जीएसटी जोड़ें'], ['Add GST to a base amount']],
    'credit score' => ['/credit-score/cibil', ['अपना मुफ़्त CIBIL स्कोर और रिपोर्ट जांचें', 'FynnEdge के साथ क्यों जांचें?'], ['Check your free CIBIL score', 'Why check with FynnEdge?']],
]);

it('puts the Hindi loan name into the shared loan calculator copy', function () {
    seedCalculatorProduct(LoanCategory::HomeLoan);

    $this->withUnencryptedCookie(SiteLocale::COOKIE, 'hi')
        ->get(route('calculators.emi', 'home-loan'))
        ->assertOk()
        ->assertSee('होम लोन ईएमआई कैलकुलेटर')
        ->assertDontSee('{loan}');
});

it('words the enquiry forms in Hindi, translating an admin row that repeats the built-in wording', function () {
    MarketingSection::factory()->published()->create([
        'placement' => EnquiryFormContent::LOAN_PLACEMENT,
        'heading' => 'Apply for a :product',
        'cta_label' => 'Request a callback',
        'cta_label_hi' => 'कॉल बैक मंगाएं',
    ]);
    app()->setLocale('hi');

    expect(EnquiryFormContent::forLoanPage('होम लोन'))
        ->toMatchArray(['heading' => 'होम लोन के लिए आवेदन करें', 'eyebrow' => 'इंस्टेंट होम लोन', 'ctaLabel' => 'कॉल बैक मंगाएं'])
        ->and(EnquiryFormContent::forQuickEnquiry())
        ->toMatchArray(['heading' => 'क्विक इन्क्वायरी से शुरुआत करें', 'ctaLabel' => 'पूछताछ सबमिट करें']);
});

describe('admin Hindi wording', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    });

    it('saves the About page Hindi beside the unchanged English and shows it to Hindi visitors only', function () {
        Livewire::test(AboutPageSettings::class)
            ->fillForm([
                'mission_title' => 'Fair credit for everyone.',
                'hi' => [
                    'mission_title' => 'सबके लिए निष्पक्ष क्रेडिट।',
                    'values' => [['title' => 'ईमानदारी', 'body' => 'हम वही कहते हैं जो सच है।']],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(Setting::get(AboutPageContent::SETTING_KEY))
            ->not->toHaveKey('hi')
            ->toMatchArray(['mission_title' => 'Fair credit for everyone.'])
            ->and(Setting::get(AboutPageContent::HINDI_SETTING_KEY))
            ->toMatchArray(['mission_title' => 'सबके लिए निष्पक्ष क्रेडिट।']);

        $this->get('/about')
            ->assertSee(['Fair credit for everyone.', 'Real ownership'])
            ->assertDontSee('सबके लिए निष्पक्ष क्रेडिट।');

        $this->withUnencryptedCookie(SiteLocale::COOKIE, 'hi')
            ->get('/about')
            ->assertSee(['सबके लिए निष्पक्ष क्रेडिट।', 'ईमानदारी', 'हमारा मिशन'])
            ->assertDontSee(['Fair credit for everyone.', 'असली ज़िम्मेदारी']);
    });

    it('opens the Hindi tab with the saved Hindi and the English tab in English', function () {
        Setting::set(AboutPageContent::HINDI_SETTING_KEY, ['work_heading' => 'हमसे जुड़ें']);

        Livewire::test(AboutPageSettings::class)
            ->assertSet('data.work_heading', 'Work with us')
            ->assertSet('data.hi.work_heading', 'हमसे जुड़ें')
            ->assertSet('data.hi.mission_title', null)
            ->call('resetToDefaults')
            ->assertSet('data.hi.work_heading', null);

        expect(Setting::get(AboutPageContent::HINDI_SETTING_KEY))->toBeNull();
    });

    it('saves Hindi calculator copy with the Hindi loan name filled in', function () {
        seedCalculatorProduct(LoanCategory::HomeLoan);

        Livewire::test(CalculatorPagesSettings::class)
            ->fillForm(['hi.emi.heading' => '{loan} की ईएमआई निकालें'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(Setting::get(CalculatorPagesContent::SETTING_KEY))->not->toHaveKey('hi');

        $this->get(route('calculators.emi', 'home-loan'))->assertSee('Home Loan EMI Calculator');

        $this->withUnencryptedCookie(SiteLocale::COOKIE, 'hi')
            ->get(route('calculators.emi', 'home-loan'))
            ->assertSee('होम लोन की ईएमआई निकालें')
            // The page title was left blank in Hindi, so it keeps the built-in Hindi.
            ->assertSee('<title>होम लोन ईएमआई कैलकुलेटर</title>', false);
    });

    it('keeps each bureau page\'s Hindi to that bureau', function () {
        Livewire::withQueryParams(['bureau' => 'experian'])->test(CreditScorePageSettings::class)
            ->fillForm(['hi.heading' => 'अपना {bureau} स्कोर आज देखें'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->withUnencryptedCookie(SiteLocale::COOKIE, 'hi')
            ->get('/credit-score/experian')
            ->assertSee('अपना Experian स्कोर आज देखें');

        $this->withUnencryptedCookie(SiteLocale::COOKIE, 'hi')
            ->get('/credit-score/cibil')
            ->assertSee('अपना मुफ़्त CIBIL स्कोर और रिपोर्ट जांचें')
            ->assertDontSee('आज देखें');
    });

    it('saves Quick Enquiry Hindi without touching the shared lender settings', function () {
        Livewire::test(QuickEnquiryPageSettings::class)
            ->fillForm(['lenders_limit' => 5, 'hi' => ['heading' => 'बताइए, आपको क्या चाहिए।']])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(Setting::get(QuickEnquiryPageContent::SETTING_KEY))->toMatchArray(['lenders_limit' => 5])->not->toHaveKey('hi');

        $this->withUnencryptedCookie(SiteLocale::COOKIE, 'hi')
            ->get('/quick-enquiry')
            ->assertSee('बताइए, आपको क्या चाहिए।')
            ->assertDontSee('हमें बताएं कि आपको क्या चाहिए।');
    });

    it('saves the Hindi version of a visitor text setting beside its English', function () {
        Livewire::test(Settings::class)
            ->fillForm(['site_tagline' => 'Simple loans', 'site_tagline_hi' => 'आसान लोन', 'founder_name_hi' => 'राहुल शर्मा'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(Setting::get('site_tagline'))->toBe('Simple loans')
            ->and(Setting::get('founder_name_hi'))->toBe('राहुल शर्मा');

        app()->setLocale('hi');

        expect(Setting::localized('site_tagline'))->toBe('आसान लोन');
    });
});

it('drops an unsafe Hindi card link like an English one', function () {
    Setting::set(QuickEnquiryPageContent::HINDI_SETTING_KEY, ['explore_links' => [
        ['label' => 'खतरनाक लिंक', 'url' => 'javascript:alert(1)'],
        ['label' => 'पात्रता जांचें', 'url' => '/eligibility'],
    ]]);
    app()->setLocale('hi');

    expect(QuickEnquiryPageContent::resolve()['explore_links'])
        ->toHaveCount(1)
        ->sequence(fn ($link) => $link->url->toBe('/eligibility'));
});

it('keeps a list hidden in Hindi when the English page hides it', function () {
    Setting::set(QuickEnquiryPageContent::SETTING_KEY, ['steps' => []]);
    Setting::set(QuickEnquiryPageContent::HINDI_SETTING_KEY, ['steps' => [['title' => 'पहला चरण', 'body' => '']]]);
    app()->setLocale('hi');

    expect(QuickEnquiryPageContent::resolve()['steps'])->toBe([]);
});
