<?php

use App\Enums\LoanCategory;
use App\Models\LoanProduct;

/*
 * Long-form copy in full-width sections spans the container and is justified.
 * A max-w-* cap on these blocks left a wide empty gutter on the right of the
 * max-w-7xl layout, and ragged-right text read as misaligned against it.
 */

it('renders the loan product body at full width and justified', function () {
    $product = LoanProduct::factory()->published()->create([
        'slug' => 'alignment-test',
        'category' => LoanCategory::PersonalLoan,
        'body' => '<p>Compare offers from multiple lenders side by side.</p>',
    ]);

    $html = $this->get("/loans/{$product->slug}")->assertOk()->getContent();

    expect($html)
        ->toContain('rich-text mt-12 max-w-none text-ink-muted text-justify hyphens-auto')
        ->not->toContain('max-w-3xl text-ink-muted text-justify');
});

it('does not cap the width of shared rich-text explainers', function (string $view) {
    expect(file_get_contents(resource_path("views/{$view}")))
        ->toContain('max-w-none text-ink-muted text-justify hyphens-auto')
        ->not->toContain('max-w-2xl text-ink-muted text-justify');
})->with([
    'components/site/calculator-explainer.blade.php',
    'components/⚡emi-calculator.blade.php',
    'loans/landing-page.blade.php',
    'loans/show-flexi-hybrid.blade.php',
]);

/*
 * The typography plugin is not installed, so `prose` renders nothing and
 * admin-written headings, paragraph gaps and lists collapse into plain text.
 * Every admin rich-text body must use the site's own `.rich-text` styles.
 */
it('styles admin rich-text bodies with the rich-text class instead of the uninstalled prose plugin', function (string $view) {
    expect(file_get_contents(resource_path("views/{$view}")))
        ->toContain('class="rich-text ')
        ->not->toMatch('/\bprose\b/');
})->with([
    'loans/show.blade.php',
    'loans/show-flexi-hybrid.blade.php',
    'loans/landing-page.blade.php',
    'pages/show.blade.php',
    'careers.blade.php',
    'resources/show.blade.php',
    'components/site/calculator-explainer.blade.php',
    'components/⚡emi-calculator.blade.php',
]);

it('defines heading, paragraph and list styles for rich-text bodies', function () {
    expect(file_get_contents(resource_path('css/app.css')))
        ->toContain('.rich-text > * + *')
        ->toContain('.rich-text h2')
        ->toContain('.rich-text h3')
        ->toContain('.rich-text ul')
        ->toContain('.rich-text p:empty::before');
});
