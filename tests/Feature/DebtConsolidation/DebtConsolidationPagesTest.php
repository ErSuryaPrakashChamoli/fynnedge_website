<?php

use App\Enums\PublishStatus;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Models\Page;
use App\Models\Setting;
use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings;
use App\Support\Pages\DebtConsolidationPageContent;
use App\Support\Pages\DebtConsolidationPages;
use App\Support\Seo\Sitemap;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

function enableDebtConsolidation(): void
{
    Setting::set(DebtConsolidationSettings::KEY, ['enabled' => true]);
}

function publishDebtPage(string $key): Page
{
    $page = Page::query()->where('slug', DebtConsolidationPages::slug($key))->firstOrFail();
    $page->update(['status' => PublishStatus::Published, 'published_at' => now()->subMinute()]);
    // Starter FAQs ship as drafts; marketing publishes them after review.
    $page->faqs()->update(['status' => PublishStatus::Published]);

    return $page;
}

it('ships every page as an unpublished draft with starter copy, SEO and FAQs', function () {
    $pages = Page::query()->whereIn('slug', DebtConsolidationPages::slugs())->with(['seoMeta', 'faqs'])->get();

    expect($pages)->toHaveCount(7)
        ->and($pages->every(fn (Page $page): bool => $page->status === PublishStatus::Draft))->toBeTrue()
        ->and($pages->flatMap->faqs->every(fn ($faq): bool => $faq->status === PublishStatus::Draft))->toBeTrue()
        ->and($pages->every(fn (Page $page): bool => filled($page->seoMeta?->title)))->toBeTrue()
        ->and($pages->firstWhere('slug', 'debt-consolidation-faqs')->faqs)->toHaveCount(10);
});

it('serves a published page at its nested address once the service is on', function () {
    enableDebtConsolidation();
    publishDebtPage('how_it_works');

    $this->get('/debt-consolidation/how-it-works')
        ->assertOk()
        ->assertSee('How Debt Consolidation Works')
        ->assertSee('How long does consolidation take?')
        ->assertSee('<link rel="canonical" href="'.route('debt-consolidation.how-it-works').'">', false);
});

it('answers 404 while the service is switched off, even for a published page', function () {
    publishDebtPage('hub');

    $this->get('/debt-consolidation')->assertNotFound();
});

it('answers 404 for a draft page but shows it through a signed preview link', function () {
    enableDebtConsolidation();

    $this->get('/debt-consolidation/calculator')->assertNotFound();
    $this->get(URL::temporarySignedRoute('debt-consolidation.calculator', now()->addMinutes(30)))
        ->assertOk()
        ->assertSee('Debt Consolidation Analyser');
});

it('sends the flat slug to the page\'s one address', function () {
    enableDebtConsolidation();
    publishDebtPage('faqs');

    $this->get('/debt-consolidation-faqs')->assertMovedPermanently()->assertRedirect(route('debt-consolidation.faqs'));
});

it('renders the hub with its FAQs, structured data and the related loan pages', function () {
    enableDebtConsolidation();
    publishDebtPage('hub');
    LoanLandingPage::factory()->published()->for(LoanProduct::factory()->published())->create(['title' => 'Personal Loan for Debt Consolidation']);
    LoanLandingPage::factory()->published()->for(LoanProduct::factory()->published())->create(['title' => 'Personal Loan for Travel']);

    $this->get('/debt-consolidation')
        ->assertOk()
        ->assertSee('Debt Consolidation')
        ->assertSee('Is FynnEdge a lender?')
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSee('"@type":"Service"', false)
        ->assertViewHas('related', fn ($related): bool => $related->pluck('title')->all() === ['Personal Loan for Debt Consolidation'])
        ->assertSee('Talk to a debt consolidation advisor');
});

it('lists live pages in the sitemap only while the service is on', function () {
    publishDebtPage('hub');
    $hubUrl = route('debt-consolidation.hub');

    expect(Sitemap::entries()->pluck('loc')->all())->not->toContain($hubUrl);

    enableDebtConsolidation();

    expect(Sitemap::entries()->pluck('loc')->all())->toContain($hubUrl);
});

it('renders the calculator suite on the calculator page', function () {
    enableDebtConsolidation();
    publishDebtPage('calculator');

    $this->get('/debt-consolidation/calculator')
        ->assertOk()
        ->assertSee('Your existing loans and cards')
        ->assertSee('Foreclosure cost');
});

it('names the loan-by-loan page the Debt Analyser and links the Savings Calculator beside it', function () {
    enableDebtConsolidation();
    publishDebtPage('hub');
    publishDebtPage('calculator');
    publishDebtPage('assessment');
    $savingsCalculator = 'href="'.route('calculators.debt-consolidation').'"';

    $response = $this->get('/debt-consolidation');

    $response->assertSeeInOrder(['aria-label="Debt consolidation pages"', $savingsCalculator, 'data-debt-savings-calculator-link', 'Savings calculator', 'Debt analyser', 'Free assessment'], false)
        ->assertSeeInOrder(['See your numbers first', $savingsCalculator, 'Open the savings calculator', 'href="'.route('debt-consolidation.calculator').'"', 'Analyse my loans', 'Start my free assessment'], false)
        ->assertDontSee('Use the calculator');
});

it('offers the Savings Calculator card even when neither the analyser nor the assessment is live', function () {
    enableDebtConsolidation();
    publishDebtPage('hub');

    $this->get('/debt-consolidation')
        ->assertSeeInOrder(['See your numbers first', 'href="'.route('calculators.debt-consolidation').'"', 'Open the savings calculator'], false)
        ->assertDontSee('Debt analyser');
});

it('never links the hidden Savings Calculator from a preview while the service is off', function (string $route) {
    $this->get(URL::temporarySignedRoute($route, now()->addMinutes(30)))
        ->assertOk()
        ->assertDontSee(route('calculators.debt-consolidation'), false);
})->with(['debt-consolidation.hub', 'debt-consolidation.calculator']);

it('points visitors on the analyser page to the quick Savings Calculator', function () {
    enableDebtConsolidation();
    publishDebtPage('calculator');

    $this->get('/debt-consolidation/calculator')
        ->assertSeeInOrder(['data-debt-savings-calculator-hint', 'Only need a quick estimate?', 'href="'.route('calculators.debt-consolidation').'"', 'Debt Consolidation Savings Calculator', 'Your existing loans and cards'], false);
});

it('renames the calculator page to the Debt Analyser only where its shipped copy is unchanged', function () {
    $page = Page::query()->where('slug', DebtConsolidationPages::slug('calculator'))->with('seoMeta')->firstOrFail();
    $page->update(['title' => 'Debt Consolidation Calculator']);
    $page->seoMeta->update(['title' => 'Our own SEO title']);

    (require database_path('migrations/2026_11_26_072745_rename_debt_consolidation_calculator_page_to_debt_analyser.php'))->up();

    expect($page->fresh()->title)->toBe('Debt Consolidation Analyser');
    expect($page->seoMeta->fresh()->title)->toBe('Our own SEO title');
});

it('renames the shipped calculator wording in saved page settings, never wording an editor changed', function () {
    $settings = fn (string $key): array => Setting::query()->where('key', $key)->value('value');
    Setting::set(DebtConsolidationPageContent::SETTING_KEY, ['secondary_cta_label' => 'Use the calculator', 'calculator_card_title' => 'Our own tool name']);
    Setting::set(DebtConsolidationPageContent::HINDI_SETTING_KEY, ['secondary_cta_label' => 'कैलकुलेटर इस्तेमाल करें']);
    $rename = require database_path('migrations/2026_11_26_072745_rename_debt_consolidation_calculator_page_to_debt_analyser.php');

    $rename->up();

    expect($settings(DebtConsolidationPageContent::SETTING_KEY))->toBe(['secondary_cta_label' => 'Analyse my loans', 'calculator_card_title' => 'Our own tool name'])
        ->and($settings(DebtConsolidationPageContent::HINDI_SETTING_KEY))->toBe(['secondary_cta_label' => 'मेरे लोन का विश्लेषण करें']);

    $rename->down();

    expect($settings(DebtConsolidationPageContent::SETTING_KEY))->toBe(['secondary_cta_label' => 'Use the calculator', 'calculator_card_title' => 'Our own tool name'])
        ->and($settings(DebtConsolidationPageContent::HINDI_SETTING_KEY))->toBe(['secondary_cta_label' => 'कैलकुलेटर इस्तेमाल करें']);
});

it('leaves the renamed page looking unedited, so a full rollback still removes it', function () {
    $slug = DebtConsolidationPages::slug('calculator');
    $rename = require database_path('migrations/2026_11_26_072745_rename_debt_consolidation_calculator_page_to_debt_analyser.php');
    $pages = require database_path('migrations/2026_11_25_150300_create_debt_consolidation_pages.php');
    $this->travel(10)->minutes();

    $rename->down();
    $pages->down();

    expect(Page::withTrashed()->where('slug', $slug)->exists())->toBeFalse();
});

it('links the hub from the site header only while it is live', function () {
    publishDebtPage('hub');

    $this->get('/')->assertDontSee('href="'.route('debt-consolidation.hub').'"', false);

    enableDebtConsolidation();

    $this->get('/')->assertSee('href="'.route('debt-consolidation.hub').'"', false);
});

it('answers the slashed address with one redirect to the canonical one', function () {
    enableDebtConsolidation();
    publishDebtPage('how_it_works');

    // The test client trims trailing slashes, so hand the request to the kernel as is.
    $kernel = app(Kernel::class);
    $request = Request::create(config('app.url').'/debt-consolidation/how-it-works/');
    $response = $kernel->handle($request);

    expect($response->getStatusCode())->toBe(301)
        ->and($response->headers->get('Location'))->toBe(route('debt-consolidation.how-it-works'));
});
