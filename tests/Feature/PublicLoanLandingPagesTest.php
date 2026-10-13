<?php

use App\Enums\LandingPageGroup;
use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Models\Faq;
use App\Models\LenderProduct;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Models\Testimonial;
use App\Support\Loans\LoanMegaMenu;
use Illuminate\Support\Str;

it('renders a published loan landing page', function () {
    $product = LoanProduct::factory()->published()->create(['slug' => 'personal-loan-menu-test']);
    $page = LoanLandingPage::factory()->published()->for($product, 'loanProduct')->create([
        'slug' => '5-lakh-personal-loan-menu-test',
        'title' => '5 Lakh Personal Loan',
    ]);

    $this->get("/loans/{$product->slug}/{$page->slug}")
        ->assertOk()
        ->assertSee('5 Lakh Personal Loan');
});

it('404s for a draft loan landing page', function () {
    $product = LoanProduct::factory()->published()->create(['slug' => 'draft-page-product']);
    $page = LoanLandingPage::factory()->for($product, 'loanProduct')->create([
        'slug' => 'draft-landing-page',
        'status' => PublishStatus::Draft,
    ]);

    $this->get("/loans/{$product->slug}/{$page->slug}")->assertNotFound();
});

it('404s when a landing page slug is requested under the wrong loan product', function () {
    $productA = LoanProduct::factory()->published()->create(['slug' => 'product-a']);
    $productB = LoanProduct::factory()->published()->create(['slug' => 'product-b']);
    $page = LoanLandingPage::factory()->published()->for($productA, 'loanProduct')->create(['slug' => 'belongs-to-a']);

    $this->get("/loans/{$productB->slug}/{$page->slug}")->assertNotFound();
});

it('shows a published landing page link in the Loans mega menu', function () {
    $product = LoanProduct::factory()->published()->create(['slug' => 'personal-loan', 'category' => LoanCategory::PersonalLoan]);
    LoanLandingPage::factory()->published()->for($product, 'loanProduct')->create([
        'slug' => 'menu-visible-page',
        'title' => 'Menu Visible Page',
    ]);

    $this->get('/')->assertOk()->assertSee('Menu Visible Page');
});

it('shows the calculator, lenders, comparison table, why fynnedge, testimonials and faqs on a sub-loan-type page', function () {
    $product = LoanProduct::factory()->published()->state(['category' => LoanCategory::PersonalLoan])->withCalculatorLimits()->create([
        'slug' => 'personal-loan-sub-page-test',
    ]);
    $page = LoanLandingPage::factory()->published()->for($product, 'loanProduct')->create([
        'slug' => '5-lakh-personal-loan-sub-page-test',
        'title' => '5 Lakh Personal Loan',
    ]);
    LenderProduct::factory()->for($product, 'loanProduct')->create();
    $faq = Faq::factory()->for($product, 'faqable')->create();
    $testimonial = Testimonial::factory()->create(['loan_category' => LoanCategory::PersonalLoan]);

    $this->get("/loans/{$product->slug}/{$page->slug}")
        ->assertOk()
        ->assertSee('5 Lakh Personal Loan EMI calculator')
        ->assertSee('Lenders offering this product')
        ->assertSee('Apply Now')
        ->assertSee('Compare lenders side by side')
        ->assertSee('Why FynnEdge?')
        ->assertSee($testimonial->quote)
        ->assertSee($faq->question);
});

it('labels a Loans mega menu link by its city only on a location page that has one', function (LandingPageGroup $group, ?string $city, string $label) {
    $product = LoanProduct::factory()->published()->create(['slug' => 'personal-loan', 'category' => LoanCategory::PersonalLoan]);
    LoanLandingPage::factory()->published()->for($product, 'loanProduct')->create([
        'group' => $group,
        'city' => $city,
        'slug' => 'personal-loan-in-delhi',
        'title' => 'Personal Loan in Delhi',
    ]);

    $groups = LoanMegaMenu::categories()[0]['groups'];

    expect($groups)->toBe([$group->getLabel() => [[
        'label' => $label,
        'title' => 'Personal Loan in Delhi',
        'route' => 'loans.landing-pages.show',
        'params' => ['loanProduct' => 'personal-loan', 'landingPage' => 'personal-loan-in-delhi'],
    ]]]);
})->with([
    'location page' => [LandingPageGroup::ByLocation, 'Delhi', 'Delhi'],
    'location page without a city' => [LandingPageGroup::ByLocation, null, 'Personal Loan in Delhi'],
    'need page' => [LandingPageGroup::ByNeed, 'Delhi', 'Personal Loan in Delhi'],
]);

it('gives a city link in the Loans mega menu its full page title as the accessible name', function () {
    $product = LoanProduct::factory()->published()->create(['slug' => 'personal-loan', 'category' => LoanCategory::PersonalLoan]);
    LoanLandingPage::factory()->published()->inCity('Delhi')->for($product, 'loanProduct')->create([
        'slug' => 'personal-loan-in-delhi',
        'title' => 'Personal Loan in Delhi',
    ]);

    $this->get('/')->assertSee('aria-label="Personal Loan in Delhi"', false);
});

it('links a location page to only the other published city pages of the same loan product', function () {
    $product = LoanProduct::factory()->published()->create(['name' => 'Personal Loan', 'slug' => 'personal-loan', 'category' => LoanCategory::PersonalLoan]);
    LoanLandingPage::factory()->published()->inCity('Delhi')->for($product, 'loanProduct')->create(['slug' => 'personal-loan-in-delhi']);
    LoanLandingPage::factory()->published()->inCity('Mumbai')->for($product, 'loanProduct')->create(['slug' => 'personal-loan-in-mumbai']);
    LoanLandingPage::factory()->inCity('Pune')->for($product, 'loanProduct')->create(['slug' => 'personal-loan-in-pune', 'status' => PublishStatus::Draft]);
    LoanLandingPage::factory()->published()->for($product, 'loanProduct')->create(['group' => LandingPageGroup::ByNeed, 'slug' => 'personal-loan-for-travel']);
    $homeLoan = LoanProduct::factory()->published()->create(['slug' => 'home-loan', 'category' => LoanCategory::HomeLoan]);
    LoanLandingPage::factory()->published()->inCity('Chennai')->for($homeLoan, 'loanProduct')->create(['slug' => 'home-loan-in-chennai']);

    $html = $this->get('/loans/personal-loan/personal-loan-in-delhi')->getContent();

    preg_match_all('/href="([^"]+)"/', Str::of($html)->after('aria-label="Personal Loan in other cities"')->before('</nav>'), $links);
    expect($links[1])->toBe([route('loans.landing-pages.show', ['loanProduct' => 'personal-loan', 'landingPage' => 'personal-loan-in-mumbai'])]);
});

it('does not list city pages on a landing page that is not a location page', function () {
    $product = LoanProduct::factory()->published()->create(['name' => 'Personal Loan', 'slug' => 'personal-loan', 'category' => LoanCategory::PersonalLoan]);
    LoanLandingPage::factory()->published()->for($product, 'loanProduct')->create([
        'group' => LandingPageGroup::ByNeed,
        'slug' => 'personal-loan-for-travel',
        'title' => 'Personal Loan for Travel',
    ]);
    LoanLandingPage::factory()->published()->inCity('Mumbai')->for($product, 'loanProduct')->create(['slug' => 'personal-loan-in-mumbai']);

    $this->get('/loans/personal-loan/personal-loan-for-travel')
        ->assertSee('Personal Loan for Travel')
        ->assertDontSee('Personal Loan in other cities');
});

it('lists sub-pages on the loan product overview by amount, type, need, then location by city', function () {
    $product = LoanProduct::factory()->published()->create(['name' => 'Personal Loan', 'slug' => 'personal-loan', 'category' => LoanCategory::PersonalLoan]);
    LoanLandingPage::factory()->published()->inCity('Delhi')->for($product, 'loanProduct')->create(['slug' => 'personal-loan-in-delhi', 'title' => 'Personal Loan in Delhi']);
    LoanLandingPage::factory()->published()->for($product, 'loanProduct')->create(['group' => LandingPageGroup::ByNeed, 'slug' => 'personal-loan-for-travel']);
    LoanLandingPage::factory()->published()->for($product, 'loanProduct')->create(['group' => LandingPageGroup::ByType, 'slug' => 'personal-loan-for-women']);
    LoanLandingPage::factory()->published()->for($product, 'loanProduct')->create(['group' => LandingPageGroup::ByAmount, 'slug' => '5-lakh-personal-loan']);

    $this->get('/loans/personal-loan')->assertSeeInOrder([
        'Explore Personal Loan by amount, type, need &amp; location',
        'By Amount',
        'By Type',
        'By Need',
        'By Location',
        'aria-label="Personal Loan in Delhi"',
    ], false);
});
