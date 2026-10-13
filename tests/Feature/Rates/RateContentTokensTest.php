<?php

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Filament\Resources\PromoBars\Pages\CreatePromoBar;
use App\Models\Faq;
use App\Models\LenderProduct;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Models\PromoBar;
use App\Models\User;
use App\Support\PromoBars\PromoBars;
use Livewire\Livewire;

function tokenProduct(): LoanProduct
{
    return LoanProduct::factory()->published()->create(['category' => LoanCategory::PersonalLoan, 'slug' => 'personal-loan', 'name' => 'Personal Loan']);
}

it('renders a [rate-range] token in a landing page body from the published rates', function () {
    $product = tokenProduct();
    publishRate(LenderProduct::factory()->for($product, 'loanProduct')->create(), 9.99, 21.0);
    publishRate(LenderProduct::factory()->for($product, 'loanProduct')->create(), 10.5, 26.0);
    $page = LoanLandingPage::factory()->published()->for($product, 'loanProduct')->create([
        'body' => '<p>Personal Loan is typically available from ₹25,000, [rate-range:personal-loan].</p>',
    ]);

    $this->get(route('loans.landing-pages.show', ['loanProduct' => $product, 'landingPage' => $page]))
        ->assertSee("from ₹25,000, at lenders' published rates of 9.99%–26.00% p.a.</p>", false)
        ->assertDontSee('[rate-range', false);
});

it('falls back to wording without a figure while no lender has a verified rate', function () {
    $product = tokenProduct();
    $page = LoanLandingPage::factory()->published()->for($product, 'loanProduct')->create([
        'body' => '<p>Available over 12–84 months, [rate-range:personal-loan].</p>',
    ]);

    $this->get(route('loans.landing-pages.show', ['loanProduct' => $product, 'landingPage' => $page]))
        ->assertSee('Available over 12–84 months, at rates set by each lender.</p>', false);
});

it('shows the live headline in a promo bar instead of a typed figure', function () {
    publishRate(LenderProduct::factory()->for(tokenProduct(), 'loanProduct')->create(), 9.99);
    PromoBar::factory()->create([
        'headline' => 'Personal loans *[rate:personal-loan]*',
        'placements' => [PromoBars::EVERY_PAGE],
        'status' => PublishStatus::Published,
    ]);

    $this->get('/contact')
        ->assertSee('Personal loans <strong class="promo-bar-highlight">from 9.99% p.a.</strong>', false);
});

it('gives the FAQ accordion and its FAQPage schema the same resolved rate', function () {
    $product = tokenProduct();
    publishRate(LenderProduct::factory()->for($product, 'loanProduct')->create(), 10.25);
    Faq::factory()->create([
        'faqable_type' => LoanProduct::class,
        'faqable_id' => $product->id,
        'question' => 'What rate will I pay?',
        'answer' => 'Rates start [rate:personal-loan], depending on your profile.',
    ]);

    $html = $this->get("/loans/{$product->slug}")->getContent();

    expect(substr_count($html, 'Rates start from 10.25% p.a., depending on your profile.'))->toBe(2);
});

it('refuses a typed rate in promo bar copy and points to the token', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(CreatePromoBar::class)
        ->fillForm([
            'name' => 'Typed rate',
            'headline' => 'Get up to *₹50 Lakhs* starting at *9.99%* p.a.',
            'cta_label' => 'Apply now',
            'cta_url' => '/eligibility',
            'placements' => [PromoBars::EVERY_PAGE],
            'status' => PublishStatus::Published->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['headline'])
        ->assertSee('Use [rate:loan-slug] (e.g. [rate:personal-loan]) so the figure always matches the published, verified rate.');

    expect(PromoBar::query()->count())->toBe(0);
});
