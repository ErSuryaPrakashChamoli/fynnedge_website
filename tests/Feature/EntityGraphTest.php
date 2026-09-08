<?php

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Models\Article;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Support\Seo\SchemaGraph;

/**
 * @return array<int, array<string, mixed>>
 */
function graphNodes(string $url): array
{
    $html = test()->get($url)->assertOk()->getContent();

    $graph = collect(explode('<script type="application/ld+json">', $html))
        ->skip(1)
        ->map(fn (string $chunk) => json_decode(explode('</script>', $chunk)[0], true))
        ->first(fn (?array $data) => isset($data['@graph']));

    return $graph['@graph'] ?? [];
}

/**
 * @param  array<int, array<string, mixed>>  $nodes
 */
function nodeOfType(array $nodes, string $type): ?array
{
    return collect($nodes)->first(fn (array $node): bool => in_array($type, (array) ($node['@type'] ?? []), true));
}

it('emits one graph carrying Organization, WebSite and WebPage with stable @ids', function () {
    $nodes = graphNodes('/');

    $organization = nodeOfType($nodes, 'Organization');
    $website = nodeOfType($nodes, 'WebSite');
    $webPage = nodeOfType($nodes, 'WebPage');

    expect($organization['@id'])->toBe(SchemaGraph::organizationId());
    expect($website['@id'])->toBe(SchemaGraph::websiteId());
    expect($webPage['@id'])->toBe(url('/').'#webpage');

    // Every node is declared exactly once — no duplicate entities to reconcile.
    expect(collect($nodes)->pluck('@id')->duplicates())->toBeEmpty();
});

it('wires the graph together by reference instead of repeating entities', function () {
    $nodes = graphNodes('/');

    expect(nodeOfType($nodes, 'WebSite')['publisher'])->toBe(['@id' => SchemaGraph::organizationId()]);
    expect(nodeOfType($nodes, 'WebPage')['isPartOf'])->toBe(['@id' => SchemaGraph::websiteId()]);
    expect(nodeOfType($nodes, 'WebPage')['about'])->toBe(['@id' => SchemaGraph::organizationId()]);
});

it('types the WebPage node per page', function () {
    expect(nodeOfType(graphNodes('/contact'), 'ContactPage'))->not->toBeNull();
    expect(nodeOfType(graphNodes('/loans'), 'CollectionPage'))->not->toBeNull();
});

it('describes a loan page as an advisory Service provided by the organization, not a product it originates', function () {
    LoanProduct::factory()->published()->create([
        'slug' => 'personal-loan',
        'name' => 'Personal Loan',
        'category' => LoanCategory::PersonalLoan,
        'summary' => 'Compare personal loan options from multiple lenders.',
    ]);

    $nodes = graphNodes('/loans/personal-loan');
    $service = nodeOfType($nodes, 'Service');

    expect($service['@id'])->toBe(url('/loans/personal-loan').'#service');
    expect($service['name'])->toBe('Personal Loan');
    expect($service['serviceType'])->toBe('Personal Loan');
    expect($service['url'])->toBe(url('/loans/personal-loan'));
    expect($service['description'])->toBe('Compare personal loan options from multiple lenders.');
    expect($service['provider'])->toBe(['@id' => SchemaGraph::organizationId()]);

    // FynnEdge is a distributor, not a lender: never a FinancialProduct, and
    // never a fabricated rate/fee/amount/tenure.
    expect(nodeOfType($nodes, 'FinancialProduct'))->toBeNull();
    foreach (['interestRate', 'annualPercentageRate', 'feesAndCommissionsSpecification', 'amount', 'loanTerm', 'offers'] as $property) {
        expect($service)->not->toHaveKey($property);
    }
});

it('carries no Service node on pages that describe no service', function () {
    expect(nodeOfType(graphNodes('/'), 'Service'))->toBeNull();
    expect(nodeOfType(graphNodes('/contact'), 'Service'))->toBeNull();
});

it('adds sameAs only for social profiles an admin has actually configured', function () {
    expect(nodeOfType(graphNodes('/'), 'Organization'))->not->toHaveKey('sameAs');

    Setting::set('social_linkedin', 'https://linkedin.com/company/fynnedge');

    expect(nodeOfType(graphNodes('/'), 'Organization')['sameAs'])
        ->toBe(['https://linkedin.com/company/fynnedge']);
});

it('emits the full social meta set with a per-page og:type', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('<meta property="og:type" content="website">', false)
        ->assertSee('<meta property="og:locale" content="en_IN">', false)
        ->assertSee('<meta name="twitter:card" content="summary">', false)
        ->assertSee('<meta name="twitter:title" content="FynnEdge">', false);

    $article = Article::factory()->published()->create(['slug' => 'og-type-article']);

    $this->get(route('resources.show', $article))
        ->assertOk()
        ->assertSee('<meta property="og:type" content="article">', false);
});

it('keeps FAQPage and BreadcrumbList addressable by @id alongside the main graph', function () {
    $product = LoanProduct::factory()->published()->create(['slug' => 'graph-faq-test', 'category' => LoanCategory::PersonalLoan]);
    $product->faqs()->create(['question' => 'Is this addressable?', 'answer' => 'Yes.', 'sort_order' => 0, 'status' => PublishStatus::Published]);

    $html = $this->get('/loans/graph-faq-test')->assertOk()->getContent();

    $blocks = collect(explode('<script type="application/ld+json">', $html))
        ->skip(1)
        ->map(fn (string $chunk) => json_decode(explode('</script>', $chunk)[0], true));

    $faq = $blocks->first(fn (?array $d) => ($d['@type'] ?? null) === 'FAQPage');
    $breadcrumb = $blocks->first(fn (?array $d) => ($d['@type'] ?? null) === 'BreadcrumbList');

    expect($faq['@id'])->toBe(url('/loans/graph-faq-test').'#faq');
    expect($breadcrumb['@id'])->toBe(url('/loans/graph-faq-test').'#breadcrumb');
});
