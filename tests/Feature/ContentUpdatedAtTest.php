<?php

use App\Enums\LoanCategory;
use App\Models\Article;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Models\Page;
use App\Support\Localization\SiteLocale;
use App\Support\Seo\Sitemap;
use Dom\HTMLDocument;

/**
 * 06:00 UTC is 11:30 AM in India, the time the stamp shows.
 */
const CONTENT_UPDATED_AT = '2026-11-02 06:00:00';

dataset('content pages', [
    'loan page' => fn () => route('loans.show', LoanProduct::factory()->published()->create([
        'category' => LoanCategory::PersonalLoan,
        'updated_at' => CONTENT_UPDATED_AT,
    ])),
    'flexi hybrid loan page' => fn () => route('loans.show', LoanProduct::factory()->published()->create([
        'category' => LoanCategory::FlexiHybridTermLoan,
        'updated_at' => CONTENT_UPDATED_AT,
    ])),
    'loan landing page' => function () {
        $page = LoanLandingPage::factory()->published()
            ->for(LoanProduct::factory()->published()->create(['category' => LoanCategory::PersonalLoan]), 'loanProduct')
            ->create(['updated_at' => CONTENT_UPDATED_AT]);

        return route('loans.landing-pages.show', ['loanProduct' => $page->loanProduct, 'landingPage' => $page]);
    },
    'article' => fn () => route('resources.show', Article::factory()->published()->create(['updated_at' => CONTENT_UPDATED_AT])),
    'policy page' => function () {
        $slug = Sitemap::ROUTED_PAGE_SLUGS[0];
        Page::query()->updateOrCreate(['slug' => $slug], Page::factory()->published()->raw(['slug' => $slug]))
            ->forceFill(['updated_at' => CONTENT_UPDATED_AT])
            ->save();

        return url($slug);
    },
]);

it('shows when a content page was last updated, in India time', function (string $url) {
    $this->get($url)
        ->assertOk()
        ->assertSee('Updated <time datetime="2026-11-02T06:00:00+00:00">2 Nov 2026, 11:30 AM</time>', false);
})->with('content pages');

/*
 * The user asked on 2026-11-03 for the stamp at the end of the page body
 * rather than beside the breadcrumbs.
 */
it('puts the update time last in the page content, below the heading', function (string $url) {
    $html = $this->get($url)->assertOk()->getContent();
    $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);

    $stamp = $document->querySelector('#main-content time[datetime="2026-11-02T06:00:00+00:00"]')->closest('p');

    expect($stamp->nextElementSibling)->toBeNull()
        ->and($stamp->parentElement->tagName)->toBeIn(['ARTICLE', 'SECTION'])
        ->and(strpos($html, '</h1>'))->toBeLessThan(strpos($html, '<time datetime="2026-11-02T06:00:00+00:00"'));
})->with('content pages');

it('labels the update time in Hindi for Hindi visitors', function () {
    $product = LoanProduct::factory()->published()->create([
        'category' => LoanCategory::PersonalLoan,
        'updated_at' => CONTENT_UPDATED_AT,
    ]);

    $this->withUnencryptedCookie(SiteLocale::COOKIE, 'hi')
        ->get(route('loans.show', $product))
        ->assertSee('अपडेट किया गया <time datetime="2026-11-02T06:00:00+00:00">', false)
        ->assertDontSee('Updated <time', false);
});
