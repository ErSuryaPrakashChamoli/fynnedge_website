<?php

use App\Enums\LenderStatus;
use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Models\Banner;
use App\Models\CompanyPhoto;
use App\Models\GrievanceLevel;
use App\Models\JobOpening;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LoanProduct;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

it('renders the homepage', function () {
    $this->get('/')->assertOk()->assertSee('Simplifying loans');
});

it('shows active lenders in the homepage trust marquee, but not inactive ones', function () {
    Lender::factory()->create(['name' => 'Alpha Finance', 'status' => LenderStatus::Active]);
    Lender::factory()->create(['name' => 'Dormant Capital', 'status' => LenderStatus::Inactive]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Trusted by leading banks &amp; NBFCs', false)
        ->assertSee('Alpha Finance')
        ->assertDontSee('Dormant Capital');
});

it('omits the trust marquee when there are no active lenders', function () {
    $this->get('/')->assertOk()->assertDontSee('Trusted by leading banks');
});

it('shows the admin-configured contact details in the footer', function () {
    Setting::set('contact_address', 'Plot No. 135P, Sector 44, Gurgaon-122001');
    Setting::set('contact_phone', '1800-258-5616');
    Setting::set('contact_email', 'care@fynnedge.com');

    $this->get('/')
        ->assertOk()
        ->assertSee('Plot No. 135P, Sector 44, Gurgaon-122001')
        ->assertSee('1800-258-5616')
        ->assertSee('care@fynnedge.com');
});

it('applies the admin-configured appearance settings to the public page head', function () {
    Setting::set('theme_header_bg_color', '#112233');
    Setting::set('theme_footer_font_color', '#aabbcc');

    $this->get('/')
        ->assertOk()
        ->assertSee('header{--color-bg:#112233;}', false)
        ->assertSee('footer{--color-ink:#aabbcc;}', false);
});

it('omits the footer contact block when no contact details are configured', function () {
    Setting::set('contact_address', '');
    Setting::set('contact_phone', '');
    Setting::set('contact_email', '');

    $this->get('/')->assertOk()->assertDontSee('tel:');
});

it('shows only published banners in the homepage carousel', function () {
    Banner::factory()->published()->create(['heading' => 'Live Diwali Offer']);
    Banner::factory()->create(['heading' => 'Unpublished Draft Banner']);

    $this->get('/')
        ->assertOk()
        ->assertSee('Live Diwali Offer')
        ->assertDontSee('Unpublished Draft Banner');
});

it('renders the loans index with only published products', function () {
    $published = LoanProduct::factory()->published()->create(['name' => 'Home Loan', 'category' => LoanCategory::HomeLoan]);
    $draft = LoanProduct::factory()->create(['name' => 'Draft Product', 'status' => PublishStatus::Draft]);

    $response = $this->get('/loans');

    $response->assertOk();
    $response->assertSee($published->name);
    $response->assertDontSee($draft->name);
});

it('lists Personal Loan first on the loans index, ahead of alphabetically earlier products', function () {
    LoanProduct::factory()->published()->create(['name' => 'Business Loan', 'category' => LoanCategory::BusinessLoan]);
    LoanProduct::factory()->published()->create(['name' => 'Zzz Loan', 'category' => LoanCategory::HomeLoan]);
    LoanProduct::factory()->published()->create(['name' => 'Personal Loan', 'category' => LoanCategory::PersonalLoan]);

    $response = $this->get('/loans')->assertOk()->getContent();

    $personalPosition = strpos($response, 'Personal Loan');
    $businessPosition = strpos($response, 'Business Loan');

    expect($personalPosition)->not->toBeFalse()->and($businessPosition)->not->toBeFalse();
    expect($personalPosition)->toBeLessThan($businessPosition);
});

it('lists Personal Loan first on the homepage and in the footer, regardless of alphabetical order', function () {
    LoanProduct::factory()->published()->create(['name' => 'Business Loan', 'category' => LoanCategory::BusinessLoan]);
    LoanProduct::factory()->published()->create(['name' => 'Personal Loan', 'category' => LoanCategory::PersonalLoan]);

    $response = $this->get('/')->assertOk()->getContent();

    expect(strpos($response, 'Personal Loan'))->toBeLessThan(strpos($response, 'Business Loan'));
});

it('excludes Gold, Two Wheeler, Term, Tractor and Mudra loans from the footer Loans column', function () {
    LoanProduct::factory()->published()->create(['name' => 'Home Loan', 'category' => LoanCategory::HomeLoan]);
    LoanProduct::factory()->published()->create(['name' => 'Gold Loan', 'category' => LoanCategory::GoldLoan]);
    LoanProduct::factory()->published()->create(['name' => 'Two Wheeler Loan', 'category' => LoanCategory::TwoWheelerLoan]);
    LoanProduct::factory()->published()->create(['name' => 'Term Loan', 'category' => LoanCategory::TermLoan]);
    LoanProduct::factory()->published()->create(['name' => 'Tractor Loan', 'category' => LoanCategory::TractorLoan]);
    LoanProduct::factory()->published()->create(['name' => 'Mudra Loan', 'category' => LoanCategory::MudraLoan]);

    $response = $this->get('/contact')->assertOk()->getContent();
    preg_match('#<footer.*?</footer>#s', $response, $matches);
    $footerHtml = $matches[0];

    expect($footerHtml)
        ->toContain('Home Loan')
        ->not->toContain('Gold Loan')
        ->not->toContain('Two Wheeler Loan')
        ->not->toContain('Term Loan')
        ->not->toContain('Tractor Loan')
        ->not->toContain('Mudra Loan');
});

it('renders a published loan product by slug', function () {
    $product = LoanProduct::factory()->published()->create(['slug' => 'business-loan']);

    $this->get('/loans/business-loan')->assertOk()->assertSee($product->name);
});

it('404s for a draft loan product on the public site', function () {
    LoanProduct::factory()->create(['slug' => 'draft-product', 'status' => PublishStatus::Draft]);

    $this->get('/loans/draft-product')->assertNotFound();
});

it('shows an initials avatar for a lender with no logo uploaded', function () {
    $product = LoanProduct::factory()->published()->create(['slug' => 'lender-avatar-test']);
    $lender = Lender::factory()->create(['name' => 'Alpha Finance', 'logo_path' => null, 'status' => LenderStatus::Active]);
    LenderProduct::factory()->create(['loan_product_id' => $product->id, 'lender_id' => $lender->id, 'status' => LenderStatus::Active]);

    $this->get('/loans/lender-avatar-test')
        ->assertOk()
        ->assertSee('Alpha Finance')
        ->assertSee('AF');
});

it('shows the uploaded logo image for a lender that has one', function () {
    Storage::fake('public');
    Storage::disk('public')->put('lenders/logo.png', 'fake-image-content');

    $product = LoanProduct::factory()->published()->create(['slug' => 'lender-logo-test']);
    $lender = Lender::factory()->create(['logo_path' => 'lenders/logo.png', 'status' => LenderStatus::Active]);
    LenderProduct::factory()->create(['loan_product_id' => $product->id, 'lender_id' => $lender->id, 'status' => LenderStatus::Active]);

    $this->get('/loans/lender-logo-test')
        ->assertOk()
        ->assertSee(Storage::disk('public')->url('lenders/logo.png'), false);
});

it('renders the about page from a published CMS page', function () {
    Page::factory()->published()->create(['slug' => 'about', 'title' => 'About FynnEdge']);

    $this->get('/about')->assertOk()->assertSee('About FynnEdge');
});

it('404s the about page when no about content is published', function () {
    Page::query()->delete();

    $this->get('/about')->assertNotFound();
});

it('shows only published company photos in the Life at FynnEdge marquee', function () {
    Page::factory()->published()->create(['slug' => 'about']);
    $published = CompanyPhoto::factory()->published()->create(['photo_path' => 'company-photos/team-outing.jpg']);
    $draft = CompanyPhoto::factory()->create(['photo_path' => 'company-photos/unpublished.jpg']);

    $response = $this->get('/about')->assertOk();

    $response->assertSee($published->photoUrl(), false);
    $response->assertDontSee($draft->photoUrl(), false);
});

it('omits the Life at FynnEdge marquee entirely when there are no photos yet', function () {
    Page::factory()->published()->create(['slug' => 'about']);

    $this->get('/about')->assertOk()->assertDontSee('company-photos/');
});

it('renders the calculators directory page', function () {
    $this->get('/calculators')->assertOk()->assertSee('All calculators')->assertSee('Fixed Deposit Calculator');
});

it('renders the EMI calculator page with a tab for every EMI-style loan type', function () {
    foreach ([LoanCategory::PersonalLoan, LoanCategory::HomeLoan, LoanCategory::CarLoan, LoanCategory::LoanAgainstProperty, LoanCategory::BusinessLoan] as $category) {
        seedCalculatorProduct($category);
    }

    $response = $this->get('/calculators/emi/personal-loan')->assertOk()->assertSee('EMI Calculator');

    $response->assertSee('Personal Loan')->assertSee('Home Loan')->assertSee('Car Loan')->assertSee('Business Loan')->assertSee('Loan Against Property');
});

it('404s the EMI calculator page for a category with no published product', function () {
    $this->get('/calculators/emi/gold-loan')->assertNotFound();
});

it('embeds a matching calculator on a loan product page for each EMI-style category', function (LoanCategory $category) {
    seedCalculatorProduct($category, ['slug' => "calc-test-{$category->value}"]);

    $this->get("/loans/calc-test-{$category->value}")
        ->assertOk()
        ->assertSee('EMI calculator')
        ->assertSee('Full breakdown, starting this month');
})->with([
    LoanCategory::PersonalLoan,
    LoanCategory::HomeLoan,
    LoanCategory::CarLoan,
    LoanCategory::BusinessLoan,
    LoanCategory::LoanAgainstProperty,
]);

it('does not embed an EMI calculator on the credit card product page', function () {
    LoanProduct::factory()->published()->create(['category' => LoanCategory::CreditCard, 'slug' => 'calc-test-credit-card']);

    $this->get('/loans/calc-test-credit-card')
        ->assertOk()
        ->assertDontSee('EMI calculator')
        ->assertDontSee('Full breakdown, starting this month');
});

it('renders each legal/company page from a published CMS page by slug', function (string $slug) {
    Page::factory()->published()->create(['slug' => $slug, 'title' => 'Test Title']);

    $this->get("/{$slug}")->assertOk()->assertSee('Test Title');
})->with(['grievance', 'privacy-policy', 'terms', 'disclaimer', 'credit-report-terms']);

it('404s a legal/company page when it has no published content', function (string $slug) {
    Page::query()->where('slug', $slug)->delete();

    $this->get("/{$slug}")->assertNotFound();
})->with(['grievance', 'privacy-policy', 'terms', 'disclaimer', 'credit-report-terms']);

it('shows the grievance redressal matrix on the grievance page, published rows only', function () {
    Page::factory()->published()->create(['slug' => 'grievance']);
    GrievanceLevel::factory()->published()->create([
        'level' => 'Level 1',
        'contact_name' => 'Anand Singh Yadav',
        'designation' => 'Manager',
    ]);
    GrievanceLevel::factory()->create(['contact_name' => 'Unpublished Officer']);

    $this->get('/grievance')
        ->assertOk()
        ->assertSee('Level 1')
        ->assertSee('Anand Singh Yadav')
        ->assertSee('Manager')
        ->assertDontSee('Unpublished Officer');
});

it('omits the grievance redressal matrix table when no levels are configured', function () {
    Page::factory()->published()->create(['slug' => 'grievance']);

    $this->get('/grievance')->assertOk()->assertDontSee('Turn-around Time');
});

it('does not show the grievance matrix on other legal pages', function () {
    Page::factory()->published()->create(['slug' => 'terms']);
    GrievanceLevel::factory()->published()->create(['level' => 'Level 1']);

    $this->get('/terms')->assertOk()->assertDontSee('Turn-around Time');
});

it('renders the careers page from a published CMS page', function () {
    Page::factory()->published()->create(['slug' => 'careers', 'title' => 'Careers']);

    $this->get('/careers')->assertOk()->assertSee('Careers');
});

it('404s the careers page when no careers content is published', function () {
    Page::query()->where('slug', 'careers')->delete();

    $this->get('/careers')->assertNotFound();
});

it('lists published job openings on the careers page, ordered, but not draft ones', function () {
    Page::factory()->published()->create(['slug' => 'careers']);
    JobOpening::factory()->published()->create(['title' => 'Second Role', 'sort_order' => 2]);
    JobOpening::factory()->published()->create(['title' => 'First Role', 'sort_order' => 1]);
    JobOpening::factory()->create(['title' => 'Draft Role']);

    $response = $this->get('/careers')->assertOk();

    $response->assertSeeInOrder(['First Role', 'Second Role']);
    $response->assertDontSee('Draft Role');
});

it('shows a fallback message on the careers page when there are no open positions', function () {
    Page::factory()->published()->create(['slug' => 'careers']);

    $this->get('/careers')->assertOk()->assertSee("don't have any open positions", false);
});

it('activates the footer links once the legal pages are published', function () {
    foreach (['careers', 'grievance', 'privacy-policy', 'terms', 'disclaimer', 'credit-report-terms'] as $slug) {
        Page::factory()->published()->create(['slug' => $slug]);
    }

    $response = $this->get('/')->assertOk();

    foreach (['careers', 'grievance', 'privacy-policy', 'terms', 'disclaimer', 'credit-report-terms'] as $slug) {
        $response->assertSee(route($slug), false);
    }
});

it('shows all four free credit score bureau links in the header', function () {
    $response = $this->get('/')->assertOk();

    $response->assertSee('Free CIBIL Score')
        ->assertSee('Free Experian Score')
        ->assertSee('Free Equifax Score')
        ->assertSee('Free CRIF Score');

    foreach (['cibil', 'experian', 'equifax', 'crif'] as $bureau) {
        $response->assertSee(route('credit-score.show', ['bureau' => $bureau]), false);
    }
});

it('renders the credit score check page for a valid bureau', function (string $bureau, string $label) {
    $this->get("/credit-score/{$bureau}")->assertOk()->assertSee("Free {$label} Score");
})->with([
    ['cibil', 'CIBIL'],
    ['experian', 'Experian'],
    ['equifax', 'Equifax'],
    ['crif', 'CRIF'],
]);

it('404s the credit score page for an unrecognised bureau', function () {
    $this->get('/credit-score/not-a-real-bureau')->assertNotFound();
});
