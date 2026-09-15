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
        ->toContain('prose prose-neutral mt-12 max-w-none text-ink-muted text-justify hyphens-auto')
        ->not->toContain('prose prose-neutral mt-12 max-w-3xl');
});

it('does not cap the width of shared rich-text explainers', function (string $view) {
    expect(file_get_contents(resource_path("views/{$view}")))
        ->toContain('max-w-none text-ink-muted text-justify hyphens-auto')
        ->not->toContain('prose prose-neutral mt-4 max-w-2xl');
})->with([
    'components/site/calculator-explainer.blade.php',
    'components/⚡emi-calculator.blade.php',
    'loans/landing-page.blade.php',
    'loans/show-flexi-hybrid.blade.php',
]);
