<?php

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Models\Article;
use App\Models\Faq;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Support\Faqs\FaqPlacements;

/**
 * Placements are per-page, not just per-route-type: an admin can pin an FAQ to
 * "Personal Loan" alone rather than only to "every loan product page".
 *
 * A stored token is either `loans.show` (the whole route) or
 * `loans.show:personal-loan` (one page). Both forms have to keep working, and
 * a page must surface both of its tokens.
 */
function placementFaq(string $question, array $placements): Faq
{
    return Faq::create([
        'question' => $question,
        'answer' => 'Answer for '.$question,
        'placements' => $placements,
        'sort_order' => 0,
        'status' => PublishStatus::Published,
        'published_at' => now()->subMinute(),
    ]);
}

it('shows a page-specific FAQ only on that page', function () {
    LoanProduct::factory()->published()->create(['slug' => 'personal-loan', 'category' => LoanCategory::PersonalLoan]);
    LoanProduct::factory()->published()->create(['slug' => 'home-loan', 'category' => LoanCategory::HomeLoan]);

    placementFaq('Only on personal loan?', ['loans.show:personal-loan']);

    $this->get('/loans/personal-loan')->assertOk()->assertSee('Only on personal loan?');
    $this->get('/loans/home-loan')->assertOk()->assertDontSee('Only on personal loan?');
});

it('still shows a route-wide FAQ on every page of that type', function () {
    LoanProduct::factory()->published()->create(['slug' => 'personal-loan', 'category' => LoanCategory::PersonalLoan]);
    LoanProduct::factory()->published()->create(['slug' => 'home-loan', 'category' => LoanCategory::HomeLoan]);

    placementFaq('On every loan page?', ['loans.show']);

    $this->get('/loans/personal-loan')->assertOk()->assertSee('On every loan page?');
    $this->get('/loans/home-loan')->assertOk()->assertSee('On every loan page?');
});

it('surfaces both the route-wide and the page-specific FAQ on the same page', function () {
    LoanProduct::factory()->published()->create(['slug' => 'personal-loan', 'category' => LoanCategory::PersonalLoan]);

    placementFaq('Everywhere question?', ['loans.show']);
    placementFaq('Just here question?', ['loans.show:personal-loan']);

    $this->get('/loans/personal-loan')
        ->assertOk()
        ->assertSee('Everywhere question?')
        ->assertSee('Just here question?');
});

it('scopes a landing page FAQ to that landing page alone', function () {
    $product = LoanProduct::factory()->published()->create(['slug' => 'personal-loan', 'category' => LoanCategory::PersonalLoan]);
    LoanLandingPage::factory()->published()->for($product, 'loanProduct')->create(['slug' => 'for-salaried', 'title' => 'For Salaried']);
    LoanLandingPage::factory()->published()->for($product, 'loanProduct')->create(['slug' => 'for-women', 'title' => 'For Women']);

    placementFaq('Salaried only?', ['loans.landing-pages.show:for-salaried']);

    $this->get('/loans/personal-loan/for-salaried')->assertOk()->assertSee('Salaried only?');
    $this->get('/loans/personal-loan/for-women')->assertOk()->assertDontSee('Salaried only?');
});

it('scopes an article FAQ to that article alone', function () {
    Article::factory()->published()->create(['slug' => 'how-emis-work']);
    Article::factory()->published()->create(['slug' => 'other-guide']);

    placementFaq('EMI guide only?', ['resources.show:how-emis-work']);

    $this->get('/resources/how-emis-work')->assertOk()->assertSee('EMI guide only?');
    $this->get('/resources/other-guide')->assertOk()->assertDontSee('EMI guide only?');
});

it('resolves both tokens for a parameterised route and one for a plain page', function () {
    expect(FaqPlacements::tokensFor('loans.show', ['loanProduct' => new LoanProduct(['slug' => 'personal-loan'])]))
        ->toBe(['loans.show', 'loans.show:personal-loan']);

    expect(FaqPlacements::tokensFor('about'))->toBe(['about']);
    expect(FaqPlacements::tokensFor(null))->toBe([]);
});

it('offers every published page individually alongside the "every ..." option', function () {
    LoanProduct::factory()->published()->create(['slug' => 'personal-loan', 'name' => 'Personal Loan', 'category' => LoanCategory::PersonalLoan]);
    LoanProduct::factory()->create(['slug' => 'draft-loan', 'name' => 'Draft Loan', 'status' => PublishStatus::Draft]);

    $group = FaqPlacements::options()['Loan product pages'];

    expect($group)->toHaveKey('loans.show');
    expect($group)->toHaveKey('loans.show:personal-loan');
    expect($group['loans.show:personal-loan'])->toBe('Personal Loan');

    // an unreachable page is not worth offering
    expect($group)->not->toHaveKey('loans.show:draft-loan');
});

it('matches nothing rather than everything when no token resolves', function () {
    placementFaq('Should stay hidden?', ['loans.show']);

    expect(Faq::query()->forPlacements([])->count())->toBe(0);
});

it('labels a page-specific token with the page name for the admin table', function () {
    LoanProduct::factory()->published()->create(['slug' => 'personal-loan', 'name' => 'Personal Loan', 'category' => LoanCategory::PersonalLoan]);

    expect(FaqPlacements::label('loans.show:personal-loan'))->toBe('Personal Loan');
    expect(FaqPlacements::label('loans.show'))->toBe('Every loan product page');
    expect(FaqPlacements::label('about'))->toBe('About page');
});
