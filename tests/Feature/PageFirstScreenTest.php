<?php

use App\Enums\LoanCategory;
use App\Models\Article;
use App\Models\Page;
use Dom\HTMLDocument;

/**
 * The selectors whose element carries data-reveal, contains an element that
 * does, or sits inside one. An unmatched selector throws, so a renamed
 * element fails the test instead of passing it.
 *
 * @param  list<string>  $selectors
 * @return list<string>
 */
function revealedOnFirstScreen(string $html, array $selectors): array
{
    $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);

    return array_values(array_filter($selectors, function (string $selector) use ($document): bool {
        $element = $document->querySelector("#main-content {$selector}") ?? throw new RuntimeException("Nothing on the page matches {$selector}.");

        return $element->closest('[data-reveal]') !== null || $element->querySelector('[data-reveal]') !== null;
    }));
}

function publishedPagePath(string $slug): string
{
    Page::query()->updateOrCreate(['slug' => $slug], Page::factory()->published()->raw(['slug' => $slug]));

    return "/{$slug}";
}

/*
 * data-reveal holds an element at opacity 0 until app.js runs, which waits
 * for livewire.js. On a page's first screen that kept the Largest Contentful
 * Paint (the heading, its introduction or the page body) 1.3–2s behind the
 * text being ready. The homepage hero is covered by HomepageHeroBannerTest.
 */
it('paints the heading and the rest of the first screen without the scroll reveal', function (string $path, array $selectors) {
    $html = $this->get($path)->assertOk()->getContent();

    expect(revealedOnFirstScreen($html, $selectors))->toBe([]);
})->with([
    'the about page' => [fn () => publishedPagePath('about'), ['h1', 'h1 + p', '.rich-text']],
    'the careers page' => [fn () => publishedPagePath('careers'), ['h1', 'h1 + p', '.rich-text']],
    'a CMS page' => [fn () => publishedPagePath('privacy-policy'), ['h1', 'h1 + p', '.rich-text']],
    'the FAQs page' => [fn () => publishedPagePath('faqs'), ['h1', 'h1 + p', '.rich-text']],
    'an article' => [fn () => '/resources/'.Article::factory()->published()->create()->slug, ['h1', 'h1 + p', '.rich-text']],
    'the contact page' => ['/contact', ['h1', 'h1 + p', 'form[action$="/contact"]']],
    'the partners page' => ['/partners', ['section']],
    'the quick enquiry page' => ['/quick-enquiry', ['section']],
    'the loans directory' => ['/loans', ['h1', 'h1 + p']],
    'the eligibility product picker' => ['/eligibility', ['h1', 'h1 + p']],
    'the resources directory' => ['/resources', ['h1', 'h1 + p']],
    'the calculators directory' => ['/calculators', ['h1', 'h1 + p']],
    'an EMI calculator' => [fn () => tap('/calculators/emi/personal-loan', fn () => seedCalculatorProduct(LoanCategory::PersonalLoan)), ['h1', 'h1 + p', '[wire\:id]']],
    'an eligibility calculator' => [fn () => tap('/calculators/eligibility/personal-loan', fn () => seedCalculatorProduct(LoanCategory::PersonalLoan)), ['h1', 'h1 + p', '[wire\:id]']],
    'a prepayment calculator' => [fn () => tap('/calculators/prepayment/personal-loan', fn () => seedCalculatorProduct(LoanCategory::PersonalLoan)), ['h1', 'h1 + p', '[wire\:id]']],
    'the fixed deposit calculator' => ['/calculators/fixed-deposit', ['h1', 'h1 + p', '[wire\:id]']],
    'the SIP calculator' => ['/calculators/sip', ['h1', 'h1 + p', '[wire\:id]']],
    'the daily SIP calculator' => ['/calculators/daily-sip', ['h1', 'h1 + p', '[wire\:id]']],
    'the GST calculator' => ['/calculators/gst', ['h1', 'h1 + p', '[wire\:id]']],
    'a credit score page' => ['/credit-score/cibil', ['h1', 'h1 + p', '[wire\:id]']],
    'the Flexi Hybrid loan page' => [fn () => '/loans/'.seedCalculatorProduct(LoanCategory::FlexiHybridTermLoan)->slug, ['section.flexi-hybrid-panel']],
]);
