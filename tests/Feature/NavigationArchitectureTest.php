<?php

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Models\LoanProduct;
use App\Models\NavigationLink;
use App\Models\Page;
use App\Support\Seo\Sitemap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Phase 5.2 audit outcome: the header's loan mega-menu, calculator mega-menu,
 * and credit-score dropdown, plus the footer's Company/Legal columns, stay
 * code-driven — NOT handed to NavigationLink. They're either safety-critical
 * (LoanMegaMenu::categories() only lists a category with a real, published
 * LoanProduct + working JourneyDefinition, preventing dead-end "Apply" links)
 * or structurally tied 1:1 to real controller/route implementations
 * (CalculatorCatalog, credit-score bureaus) that a generic link editor could
 * not safely reproduce — an admin "adding" a calculator link doesn't create
 * the calculator behind it. NavigationLink remains purely additive (footer
 * "Quick Links" only). These tests lock that decision in as regression
 * protection, not just documentation.
 */
/**
 * Only the <footer> element: legal links also appear in consent copy elsewhere
 * on some pages, which would mask a footer link that should have disappeared.
 */
function footerHtml(): string
{
    return Str::of(test()->get('/')->assertOk()->getContent())->after('<footer')->before('</footer>')->toString();
}

it('keeps the header loan mega-menu code-driven and unaffected by NavigationLink records', function () {
    $product = LoanProduct::factory()->published()->create(['category' => LoanCategory::PersonalLoan]);
    NavigationLink::factory()->create(['label' => 'Some Quick Link', 'route_name' => 'contact']);

    $response = $this->get('/');

    $response->assertOk()->assertSee($product->name);
});

it('keeps the calculator mega-menu entries intact regardless of navigation link configuration', function () {
    NavigationLink::factory()->create(['label' => 'Some Quick Link', 'route_name' => 'contact']);

    $this->get('/calculators')
        ->assertOk()
        ->assertSee('Fixed Deposit Calculator')
        ->assertSee('GST Calculator')
        ->assertSee('SIP Calculator');
});

it('keeps the header credit-score bureau links intact', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(route('credit-score.show', ['bureau' => 'cibil']), false)
        ->assertSee(route('credit-score.show', ['bureau' => 'experian']), false)
        ->assertSee(route('credit-score.show', ['bureau' => 'equifax']), false)
        ->assertSee(route('credit-score.show', ['bureau' => 'crif']), false);
});

it('keeps the footer Company and Legal columns hardcoded and present while their pages are live', function () {
    foreach (Sitemap::publicPageSlugs() as $slug) {
        Page::factory()->published()->create(['slug' => $slug]);
    }

    $this->get('/')
        ->assertOk()
        ->assertSee('About')
        ->assertSee('Careers')
        ->assertSee('Grievance')
        ->assertSee('Privacy Policy')
        ->assertSee('Terms')
        ->assertSee('Credit Report Terms of Use');
});

it('excludes an unpublished loan category from the mega-menu, same as before this phase', function () {
    LoanProduct::factory()->create([
        'category' => LoanCategory::HomeLoan,
        'status' => PublishStatus::Draft,
    ]);

    $this->get('/')->assertOk()->assertDontSee('/loans/apply');
});

it('hides a Company or Legal footer link whose page would 404', function () {
    foreach (Sitemap::publicPageSlugs() as $slug) {
        Page::factory()->published()->create(['slug' => $slug]);
    }

    Page::where('slug', 'terms')->update(['status' => PublishStatus::Draft]);
    Page::where('slug', 'disclaimer')->update(['expires_at' => now()->subMinute()]);
    Page::where('slug', 'grievance')->first()->delete();

    $footer = footerHtml();

    expect($footer)
        ->not->toContain('href="'.route('terms').'"')
        ->not->toContain('href="'.route('disclaimer').'"')
        ->not->toContain('href="'.route('grievance').'"')
        ->toContain('href="'.route('privacy-policy').'"')
        ->toContain('href="'.route('credit-report-terms').'"')
        ->toContain('href="'.route('about').'"')
        ->toContain('href="'.route('careers').'"')
        ->toContain('href="'.route('contact').'"');
});

it('drops the Legal heading when none of its pages are live, but keeps Contact', function () {
    $footer = footerHtml();

    expect($footer)
        ->not->toContain('>Legal</p>')
        ->toContain('href="'.route('contact').'"');
});

it('loads every footer page link state in a single query', function () {
    foreach (Sitemap::publicPageSlugs() as $slug) {
        Page::factory()->published()->create(['slug' => $slug]);
    }

    DB::enableQueryLog();
    $this->get('/')->assertOk();

    // The homepage itself queries no `pages` rows, so every one logged is the footer's.
    expect(collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], '"pages"')))->toHaveCount(1);
});
