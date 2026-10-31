<?php

use App\Enums\LoanCategory;
use App\Models\Article;

/*
 * livewire.js carries its own Alpine, and the layout used to print it on every
 * page. Livewire now injects it only where a component rendered; app.js starts
 * a standalone Alpine everywhere else, keyed on window.Livewire being absent.
 * Two Alpines on one page break wire:model, so a Livewire page must still get
 * the script exactly once.
 */
it('leaves livewire.js off a page that renders no Livewire component', function (string $path) {
    $this->get($path)
        ->assertOk()
        ->assertDontSee('livewire.js', false)
        ->assertDontSee('data-update-uri', false)
        ->assertDontSee('<!-- Livewire Styles -->', false);
})->with([
    'the homepage' => ['/'],
    'the contact page' => ['/contact'],
    'an article' => [fn () => '/resources/'.Article::factory()->published()->create()->slug],
]);

it('loads livewire.js and its styles exactly once on a page with a Livewire component', function () {
    seedCalculatorProduct(LoanCategory::PersonalLoan);

    $html = $this->get('/calculators/emi/personal-loan')->assertOk()->getContent();

    expect(substr_count($html, 'livewire.js?id='))->toBe(1)
        ->and(substr_count($html, '<!-- Livewire Styles -->'))->toBe(1)
        ->and(strpos($html, 'livewire.js?id='))->toBeGreaterThan(strpos($html, '</main>'));
});
