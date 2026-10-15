<?php

use App\Enums\LoanCategory;
use App\Models\ContactEnquiry;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Support\Localization\SiteLocale;
use App\Support\Options\CityOptions;
use App\Support\Rates\RateBook;

function hindiLoanProduct(array $attributes = []): LoanProduct
{
    return LoanProduct::factory()->published()->withCalculatorLimits()->create([
        'category' => LoanCategory::PersonalLoan,
        'slug' => 'hindi-personal-loan',
        'name' => 'Personal Loan',
        'name_hi' => 'पर्सनल लोन',
        ...$attributes,
    ]);
}

function hindiLenderOffer(LoanProduct $product, string $lenderName = 'Hindi Test Bank'): LenderProduct
{
    return LenderProduct::factory()
        ->for($product, 'loanProduct')
        ->for(Lender::factory()->state(['name' => $lenderName]))
        ->create();
}

beforeEach(function () {
    $this->withUnencryptedCookie(SiteLocale::COOKIE, 'hi');
});

it('renders a loan product page, its enquiry form and the lender chart in Hindi', function () {
    $this->travelTo('2026-10-13 12:00:00');
    $product = hindiLoanProduct();
    publishRate(hindiLenderOffer($product), 10.25, 21.0);

    $this->get("/loans/{$product->slug}")
        ->assertSee('पर्सनल लोन')
        ->assertSee('ईएमआई कैलकुलेट करें')
        ->assertSee('शुरुआती ब्याज दर')
        ->assertSee('<span class="text-accent" x-text="selected?.rate">10.25% प्रति वर्ष</span> से शुरू', false)
        ->assertSee('लेंडरों की साथ-साथ तुलना करें')
        ->assertSee('ब्याज दर 13 अक्टू. 2026 को सत्यापित')
        ->assertDontSee('Compare lenders side by side')
        ->assertDontSee('Calculate EMI')
        ->assertDontSee('Rate verified');
});

it('shows a lender rate\'s Hindi notes in Hindi without caching one language for the other', function () {
    $product = hindiLoanProduct();
    publishRate(hindiLenderOffer($product), 10.25, null, [
        'borrower_segment' => 'Women borrowers',
        'borrower_segment_hi' => 'महिला ग्राहक',
        'eligibility_notes' => 'Salaried applicants only.',
        'eligibility_notes_hi' => 'सिर्फ़ वेतनभोगी आवेदक।',
    ]);

    $this->get("/loans/{$product->slug}")
        ->assertSee('सिर्फ़ इनके लिए: महिला ग्राहक')
        ->assertSee('सिर्फ़ वेतनभोगी आवेदक।')
        ->assertDontSee('Salaried applicants only.');

    $this->withUnencryptedCookie(SiteLocale::COOKIE, 'en')
        ->get("/loans/{$product->slug}")
        ->assertSee('Only for: Women borrowers')
        ->assertSee('Salaried applicants only.')
        ->assertDontSee('सिर्फ़ वेतनभोगी आवेदक।');
});

it('keeps a rate for all borrowers when only its Hindi segment is filled', function () {
    $product = hindiLoanProduct();
    publishRate(hindiLenderOffer($product), 10.25, null, ['borrower_segment' => null, 'borrower_segment_hi' => 'महिला ग्राहक']);
    app()->setLocale('hi');

    expect(RateBook::headlineFor($product)?->startingLabel())->toBe('10.25%');
});

it('expands rate tokens inside a Hindi body into Hindi phrases ending in a purna viram', function () {
    $product = hindiLoanProduct([
        'body' => '<p>Rates start [rate:hindi-personal-loan].</p>',
        'body_hi' => '<p>ब्याज दरें [rate:hindi-personal-loan].</p><p>सभी लेंडर [rate-range:hindi-personal-loan].</p><p>कार लोन [rate:car-loan].</p>',
    ]);
    publishRate(hindiLenderOffer($product), 10.25, 21.0);

    $this->get("/loans/{$product->slug}")
        ->assertSee('ब्याज दरें 10.25% प्रति वर्ष से शुरू।', false)
        ->assertSee('सभी लेंडर लेंडरों की प्रकाशित ब्याज दरों 10.25%–21.00% प्रति वर्ष पर।', false)
        ->assertSee('कार लोन हर लेंडर द्वारा तय ब्याज दरों पर।', false)
        ->assertDontSee('from 10.25% p.a.');
});

it('thanks a Hindi visitor in Hindi but stores the enquiry source under the English product name', function () {
    $product = hindiLoanProduct();

    $this->withCredentials()->postJson("/loans/{$product->slug}/enquiry", [
        'name' => 'Rahul Sharma',
        'phone' => '9876543210',
        'loan_amount' => 500000,
    ])
        ->assertCreated()
        ->assertJsonPath('title', 'धन्यवाद!')
        ->assertJsonPath('message', 'आपकी पूछताछ सफलतापूर्वक सबमिट हो गई है। हमारी टीम जल्द ही आपसे संपर्क करेगी।');

    expect(ContactEnquiry::query()->sole()->enquiry_source)->toBe('Personal Loan Page');
});

it('rejects an out-of-range loan amount with a Hindi message', function () {
    $product = hindiLoanProduct(['min_amount' => 50000, 'max_amount' => 4000000]);

    $this->withCredentials()->postJson("/loans/{$product->slug}/enquiry", [
        'name' => 'Rahul Sharma',
        'phone' => '9876543210',
        'loan_amount' => 1000,
    ])->assertJsonValidationErrors(['loan_amount' => '₹50,000 से ₹40,00,000 के बीच की राशि डालें।']);

    expect(ContactEnquiry::query()->count())->toBe(0);
});

it('asks a Hindi visitor for a verification code in Hindi on the quick enquiry box', function () {
    $this->withCredentials()->postJson('/quick-enquiry', ['phone' => '9876543210'])
        ->assertJsonValidationErrors(['otp_challenge_id' => 'कृपया पहले सत्यापन कोड मंगवाएं।']);
});

it('renders the quick enquiry page form in Hindi', function () {
    $product = hindiLoanProduct();
    publishRate(hindiLenderOffer($product), 10.49);

    $this->get('/quick-enquiry')
        ->assertSee('लोन का प्रकार चुनें')
        ->assertSee('पर्सनल लोन · 10.49% प्रति वर्ष से शुरू')
        ->assertSee('ज़रूरत की लोन राशि')
        ->assertDontSee('Select Loan Type')
        ->assertDontSee('Loan Amount Required');
});

it('renders the flexi hybrid hero and comparison in Hindi', function () {
    $product = hindiLoanProduct([
        'category' => LoanCategory::FlexiHybridTermLoan,
        'slug' => 'hindi-flexi-hybrid',
        'name' => 'Flexi Hybrid Term Loan',
        'name_hi' => 'फ्लेक्सी हाइब्रिड टर्म लोन',
    ]);
    hindiLenderOffer($product);

    $this->get("/loans/{$product->slug}")
        ->assertSee('शुरुआत में कम ईएमआई। फ्लेक्सिबल एक्सेस।')
        ->assertSee('फ्लेक्सी हाइब्रिड टर्म लोन बनाम पर्सनल लोन — क्या फ़र्क है?')
        ->assertDontSee('Lower Initial EMI')
        ->assertDontSee('Repayment structure');
});

it('renders a location landing page in Hindi', function () {
    $product = hindiLoanProduct();
    $delhi = LoanLandingPage::factory()->published()->for($product, 'loanProduct')->inCity('Delhi')
        ->create(['slug' => 'personal-loan-in-delhi', 'title' => 'Personal Loan in Delhi', 'title_hi' => 'दिल्ली में पर्सनल लोन']);
    LoanLandingPage::factory()->published()->for($product, 'loanProduct')->inCity('Pune')
        ->create(['slug' => 'personal-loan-in-pune', 'title' => 'Personal Loan in Pune', 'title_hi' => 'पुणे में पर्सनल लोन']);

    $this->get("/loans/{$product->slug}/{$delhi->slug}")
        ->assertSee('दिल्ली में पर्सनल लोन')
        ->assertSee('पर्सनल लोन की जानकारी')
        ->assertSee('दूसरे शहरों में पर्सनल लोन')
        ->assertDontSee('in other cities');
});

it('renders the empty loan listing in Hindi', function () {
    $this->get('/loans')
        ->assertSee('अपने लिए सही लोन खोजें')
        ->assertSee('लोन प्रोडक्ट जोड़े जा रहे हैं। कृपया जल्द ही दोबारा देखें।')
        ->assertDontSee('Find the right loan for you');
});

it('labels the bundled cities in Hindi while keeping their English values', function () {
    app()->setLocale('hi');

    expect(CityOptions::all())->toContain(['value' => 'Mumbai', 'label' => 'मुंबई']);
});

it('reports the English product name to analytics from a Hindi enquiry form', function () {
    hindiLoanProduct();

    $this->get(route('loans.show', 'hindi-personal-loan'))
        ->assertOk()
        ->assertSee('analyticsName\\u0022:\\u0022Personal Loan\\u0022', false);
});
