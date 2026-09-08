<?php

use App\Enums\LandingPageGroup;
use App\Enums\PublishStatus;
use App\Models\Article;
use App\Models\Faq;
use App\Models\LoanProduct;
use App\Support\Seo\SchemaGraph;

/**
 * Every one of these previously asserted only `@type`, never `@context` —
 * which is exactly how a real bug shipped silently through Phase 6: Blade
 * compiles a literal `'@context' => ...` array key as its built-in
 * `Context` facade directive (the same family as `@auth`/`@guest`),
 * corrupting the key into compiled PHP source instead of the literal
 * string `@context`. The resulting JSON was still syntactically valid (so
 * json_decode() succeeded and `@type`-only assertions passed) but had no
 * usable `@context` key at all — found only by fetching real rendered HTML
 * during Phase 8 browser QA, not by any existing automated test. Fixed by
 * escaping it as `'@@context'` in every affected blade file (Blade's
 * standard "not a directive" escape). Every test below now explicitly
 * checks `@context` so this exact class of bug can't silently reappear.
 */
function assertValidJsonLd(?array $json): void
{
    expect($json)->not->toBeNull();
    expect($json)->toHaveKey('@context');
    expect($json['@context'])->toBe('https://schema.org');
}

it('emits BreadcrumbList JSON-LD derived from the same trail shown visually', function () {
    $loanProduct = LoanProduct::factory()->published()->create(['slug' => 'breadcrumb-test', 'name' => 'Breadcrumb Test Loan']);

    $response = $this->get('/loans/breadcrumb-test');

    $response->assertOk()->assertSee('BreadcrumbList', false);

    $json = collect(explode('<script type="application/ld+json">', $response->getContent()))
        ->skip(1)
        ->map(fn ($chunk) => json_decode(explode('</script>', $chunk)[0], true))
        ->first(fn ($data) => ($data['@type'] ?? null) === 'BreadcrumbList');

    assertValidJsonLd($json);
    expect($json['itemListElement'][0]['name'])->toBe('Home');
    expect($json['itemListElement'][0]['item'])->toBe(route('home'));
    expect(collect($json['itemListElement'])->pluck('name'))->toContain('Breadcrumb Test Loan');
});

it('emits Article JSON-LD with reliable, non-fabricated properties', function () {
    $article = Article::factory()->published()->create([
        'slug' => 'article-jsonld-test',
        'title' => 'A Guide To Personal Loans',
        'excerpt' => 'Everything you need to know.',
    ]);

    $response = $this->get('/resources/article-jsonld-test');

    $response->assertOk();

    $json = collect(explode('<script type="application/ld+json">', $response->getContent()))
        ->skip(1)
        ->map(fn ($chunk) => json_decode(explode('</script>', $chunk)[0], true))
        ->first(fn ($data) => ($data['@type'] ?? null) === 'Article');

    assertValidJsonLd($json);
    expect($json['headline'])->toBe('A Guide To Personal Loans');
    expect($json['description'])->toBe('Everything you need to know.');
    expect($json)->not->toHaveKey('image');

    /**
     * author/publisher reference the Organization node in the layout's graph by
     *
     * @id rather than restating its name inline, so there is exactly one
     * organization entity per page for crawlers to resolve.
     */
    expect($json['author'])->toBe(['@id' => SchemaGraph::organizationId()]);
    expect($json['publisher'])->toBe($json['author']);
    expect($json['mainEntityOfPage']['@id'])->toEndWith('#webpage');
});

it('emits sitewide Organization and WebSite JSON-LD on every page, with a valid @context', function () {
    $response = $this->get('/');

    $response->assertOk();

    $json = collect(explode('<script type="application/ld+json">', $response->getContent()))
        ->skip(1)
        ->map(fn ($chunk) => json_decode(explode('</script>', $chunk)[0], true))
        ->first(fn ($data) => isset($data['@graph']));

    assertValidJsonLd($json);

    /**
     * Asserted on the decoded graph rather than as `assertSee('"@type":"Organization"')`
     * on raw HTML: the Organization node is multi-typed (Organization +
     * FinancialService, so LocalBusiness-level areaServed/priceRange are legal
     * on it), which no exact-string assertion survives.
     */
    $organization = collect($json['@graph'])->first(fn ($node) => str_ends_with($node['@id'] ?? '', '#organization'));
    $website = collect($json['@graph'])->first(fn ($node) => ($node['@type'] ?? null) === 'WebSite');

    expect($organization['@type'])->toContain('Organization');
    expect($organization['name'])->toBe('FynnEdge');
    expect($website)->not->toBeNull();
    expect($website['url'])->toBe(url('/'));
});

it('emits FAQPage JSON-LD on a loan landing page that has FAQs, using the shared component, with a valid @context', function () {
    $loanProduct = LoanProduct::factory()->published()->create(['slug' => 'landing-faq-test']);
    $faq = Faq::factory()->create([
        'faqable_type' => LoanProduct::class,
        'faqable_id' => $loanProduct->id,
        'status' => PublishStatus::Published,
        'question' => 'Is this a real question?',
    ]);
    $landingPage = $loanProduct->landingPages()->create([
        'group' => LandingPageGroup::ByNeed,
        'title' => 'Landing FAQ Page',
        'slug' => 'landing-faq-page',
        'status' => PublishStatus::Published,
    ]);

    $response = $this->get('/loans/landing-faq-test/landing-faq-page');

    $response->assertOk()
        ->assertSee('FAQPage', false)
        ->assertSee('Is this a real question?');

    $json = collect(explode('<script type="application/ld+json">', $response->getContent()))
        ->skip(1)
        ->map(fn ($chunk) => json_decode(explode('</script>', $chunk)[0], true))
        ->first(fn ($data) => ($data['@type'] ?? null) === 'FAQPage');

    assertValidJsonLd($json);
});

it('emits FAQPage JSON-LD with a valid @context on the general FAQ page', function () {
    Faq::factory()->create([
        'status' => PublishStatus::Published,
        'question' => 'General test question?',
        'faqable_type' => null,
        'faqable_id' => null,
    ]);

    $response = $this->get('/faqs');

    $response->assertOk();

    $json = collect(explode('<script type="application/ld+json">', $response->getContent()))
        ->skip(1)
        ->map(fn ($chunk) => json_decode(explode('</script>', $chunk)[0], true))
        ->first(fn ($data) => ($data['@type'] ?? null) === 'FAQPage');

    assertValidJsonLd($json);
});
