<?php

use App\Enums\LenderStatus;
use App\Enums\LoanCategory;
use App\Enums\RateVerificationStatus;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LenderProductRate;
use App\Models\LoanProduct;
use App\Models\User;
use App\Support\Rates\RateBook;
use App\Support\Rates\RateWorkflow;
use Illuminate\Support\Facades\DB;

function displayProduct(LoanCategory $category = LoanCategory::PersonalLoan, string $slug = 'rate-display-test'): LoanProduct
{
    return LoanProduct::factory()->published()->create(['category' => $category, 'slug' => $slug, 'name' => $category->getLabel()]);
}

function displayOffer(LoanProduct $product, string $lenderName): LenderProduct
{
    return LenderProduct::factory()
        ->for($product, 'loanProduct')
        ->for(Lender::factory()->state(['name' => $lenderName]))
        ->create(['interest_rate_from' => 8.0]);
}

it('shows only a published, verified rate and never the offer\'s unsourced figure', function () {
    $product = displayProduct();
    $verified = displayOffer($product, 'Verified Bank');
    $pending = displayOffer($product, 'Pending Bank');
    publishRate($verified, 10.25, 21.0);
    LenderProductRate::factory()->pendingApproval()->for($pending, 'lenderProduct')->create(['rate_min' => 9.15]);

    $this->get("/loans/{$product->slug}")
        ->assertSee('10.25%')
        ->assertSee('Check current rates')
        ->assertDontSee('9.15%')
        ->assertDontSee('8.00%');
});

it('keeps a lender\'s rates separate for each loan type', function () {
    $lender = Lender::factory()->create(['name' => 'Same Lender']);
    $personal = displayProduct(LoanCategory::PersonalLoan, 'pl-separate');
    $home = displayProduct(LoanCategory::HomeLoan, 'hl-separate');
    publishRate(LenderProduct::factory()->for($personal, 'loanProduct')->for($lender)->create(), 10.99);
    publishRate(LenderProduct::factory()->for($home, 'loanProduct')->for($lender)->create(), 7.45);

    $this->get('/loans/pl-separate')->assertSee('10.99%')->assertDontSee('7.45%');
    $this->get('/loans/hl-separate')->assertSee('7.45%')->assertDontSee('10.99%');
});

it('stops showing a rate the day after its validity ends, and not before', function () {
    $this->travelTo('2026-10-20 12:00:00');
    $product = displayProduct();
    publishRate(displayOffer($product, 'Seasonal Bank'), 9.75, null, ['effective_until' => '2026-10-31']);

    $this->travelTo('2026-10-31 18:00:00');
    $this->get("/loans/{$product->slug}")->assertSee('9.75%');

    $this->travelTo('2026-11-01 06:00:00');
    $this->get("/loans/{$product->slug}")->assertDontSee('9.75%')->assertSee('Check current rates');
});

it('shows a promotional rate as an offer beside the standard rate, and drops it when the promotion ends', function () {
    $this->travelTo('2026-10-20 12:00:00');
    $product = displayProduct();
    publishRate(displayOffer($product, 'Festive Bank'), 10.5, 18.0, [
        'promo_rate' => 9.49,
        'promo_label' => 'Festive offer',
        'promo_ends_at' => '2026-10-31 18:29:00',
    ]);

    $this->get("/loans/{$product->slug}")
        ->assertSee('10.50%')
        ->assertSee('Festive offer: 9.49% p.a. until 31 Oct 2026');
    expect(RateBook::headlineFor($product)->startingLabel())->toBe('9.49%');

    $this->travelTo('2026-10-31 18:30:00');

    $this->get("/loans/{$product->slug}")
        ->assertSee('10.50%')
        ->assertDontSee('Festive offer');
    expect(RateBook::headlineFor($product)->startingLabel())->toBe('10.50%');
});

it('shows a segment-only rate with its condition but never uses it for the product headline', function () {
    $product = displayProduct();
    publishRate(displayOffer($product, 'Segment Bank'), 8.6, null, ['borrower_segment' => 'Women borrowers']);
    publishRate(displayOffer($product, 'Open Bank'), 10.1);

    $this->get("/loans/{$product->slug}")->assertSee('Only for: Women borrowers');

    expect(RateBook::headlineFor($product)->startingLabel())->toBe('10.10%');
});

it('leaves out rates of an inactive offer or an inactive lender', function () {
    $product = displayProduct();
    $inactiveOffer = displayOffer($product, 'Paused Offer Bank');
    $inactiveOffer->update(['status' => LenderStatus::Inactive]);
    $inactiveLender = displayOffer($product, 'Paused Lender Bank');
    $inactiveLender->lender->update(['status' => LenderStatus::Inactive]);
    publishRate($inactiveOffer, 8.1);
    publishRate($inactiveLender, 8.2);

    expect(RateBook::headlineFor($product))->toBeNull();
});

it('never serves a published rate that is not verified', function () {
    $product = displayProduct();
    publishRate(displayOffer($product, 'Odd Bank'), 9.3, null, ['verification_status' => RateVerificationStatus::Unverified]);

    expect(RateBook::headlineFor($product))->toBeNull();
});

it('quotes credit cards as a monthly finance charge, not a loan rate', function () {
    $product = displayProduct(LoanCategory::CreditCard, 'card-display-test');
    publishRate(displayOffer($product, 'Card Bank'), 3.5, 3.75);

    $this->get('/loans/card-display-test')
        ->assertSee('Finance charges from')
        ->assertSee('3.50%')
        ->assertSee('per month')
        ->assertDontSee('p.a. onwards');
});

it('uses the same headline on the hero, the enquiry form and the comparison table', function () {
    $product = displayProduct();
    publishRate(displayOffer($product, 'Low Bank'), 9.99, 22.0);
    publishRate(displayOffer($product, 'High Bank'), 11.0, 24.0);

    $this->get("/loans/{$product->slug}")
        ->assertSeeInOrder(['9.99%', 'Interest from'])
        ->assertSee('starting at <span class="text-accent" x-text="selected?.rate">9.99% p.a.</span>', false)
        ->assertSeeInOrder(['Rates from', '9.99%']);
});

it('shows a newly published version on the next page view', function () {
    $product = displayProduct();
    $live = publishRate(displayOffer($product, 'Moving Bank'), 10.4);
    $this->get("/loans/{$product->slug}")->assertSee('10.40%');
    $publisher = tap(User::factory()->create(['is_admin' => true]))->syncRoles(['Marketing Publisher']);

    $next = RateWorkflow::draftFrom($live, $publisher);
    $next->update(['rate_min' => 10.65]);
    RateWorkflow::publish($next, $publisher);

    $this->get("/loans/{$product->slug}")->assertSee('10.65%')->assertDontSee('10.40%');
});

it('reads all live rates for a page in a single query', function () {
    $product = displayProduct();
    foreach (['A', 'B', 'C', 'D'] as $name) {
        publishRate(displayOffer($product, "Bank {$name}"), 10.0);
    }
    RateBook::flush();
    DB::enableQueryLog();

    foreach ($product->lenderProducts as $offer) {
        RateBook::quotesFor($offer->id);
    }
    RateBook::headlineFor($product);

    expect(collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], 'lender_product_rates')))->toHaveCount(1);
});

it('never lets a fee note carry an interest rate or an aggregator citation onto the page', function () {
    $product = displayProduct();
    displayOffer($product, 'Noted Bank')->update([
        'processing_fee_note' => 'Salaried from 7.75% p.a.; PF up to 3% of sanctioned amount + taxes; Paisabazaar: 0.71%-3.99% p.m.',
    ]);

    $this->get("/loans/{$product->slug}")
        ->assertSee('PF up to 3% of sanctioned amount + taxes')
        ->assertDontSee('7.75%')
        ->assertDontSee('Paisabazaar');
});
