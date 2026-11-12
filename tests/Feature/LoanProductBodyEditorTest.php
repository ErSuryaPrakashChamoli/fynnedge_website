<?php

use App\Enums\LoanCategory;
use App\Filament\Resources\LoanProductContent\Pages\EditLoanProductContent;
use App\Models\LoanProduct;
use App\Models\User;
use Livewire\Livewire;

/**
 * The body as the browser's visual editor sends it after "business loan" is
 * selected and given a link in the toolbar's Link dialog.
 *
 * @return array<string, mixed>
 */
function bodyWithLinkedWords(?string $target = null): array
{
    return [
        'type' => 'doc',
        'content' => [
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'Choose the perfect '],
                ['type' => 'text', 'text' => 'business loan', 'marks' => [
                    ['type' => 'link', 'attrs' => ['href' => 'https://fynnedge.com/', 'target' => $target, 'rel' => 'noopener noreferrer nofollow', 'class' => null]],
                ]],
                ['type' => 'text', 'text' => ' to settle day-to-day costs.'],
            ]],
            ['type' => 'bulletList', 'content' => [
                ['type' => 'listItem', 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'No-collateral business loans available.']]],
                ]],
            ]],
        ],
    ];
}

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $this->product = LoanProduct::factory()->published()->create([
        'category' => LoanCategory::BusinessLoan,
        'body' => '<p>Choose the perfect business loan to settle day-to-day costs.</p><ul><li><p>No-collateral business loans available.</p></li></ul>',
    ]);
});

it('saves a link added in the visual editor and keeps it on the next save', function (?string $target) {
    Livewire::test(EditLoanProductContent::class, ['record' => $this->product->getRouteKey()])
        ->fillForm(['body' => bodyWithLinkedWords($target)])
        ->call('save')
        ->assertHasNoFormErrors();

    $saved = $this->product->refresh()->body;

    expect($saved)->toContain('href="https://fynnedge.com/"')
        ->and($saved)->toContain('>business loan</a>');

    // Reopening the page must keep the visual editor and change nothing on save.
    Livewire::test(EditLoanProductContent::class, ['record' => $this->product->getRouteKey()])
        ->assertSchemaStateSet(['body_html_mode' => false])
        ->fillForm(['summary' => 'A different summary'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->product->refresh()->body)->toBe($saved);

    $this->get(route('loans.show', $this->product))
        ->assertSee('href="https://fynnedge.com/"', false)
        ->assertSee('>business loan</a>', false);
})->with([
    'same tab' => [null],
    'new tab' => ['_blank'],
]);

/**
 * A body using every formatting button on the editor's toolbar, as the
 * browser sends it.
 *
 * @return array<string, mixed>
 */
function bodyWithEveryToolbarFeature(): array
{
    $text = fn (string $text, array $marks = []): array => ['type' => 'text', 'text' => $text]
        + ($marks === [] ? [] : ['marks' => array_map(fn (string $mark): array => ['type' => $mark], $marks)]);
    $paragraph = fn (array $content, string $align = 'start'): array => ['type' => 'paragraph', 'attrs' => ['textAlign' => $align], 'content' => $content];
    $cell = fn (string $type, string $words): array => ['type' => $type, 'attrs' => ['colspan' => 1, 'rowspan' => 1, 'colwidth' => null], 'content' => [$paragraph([$text($words)])]];

    return [
        'type' => 'doc',
        'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2, 'textAlign' => 'start'], 'content' => [$text('Business loan features')]],
            ['type' => 'heading', 'attrs' => ['level' => 3, 'textAlign' => 'start'], 'content' => [$text('Why FynnEdge')]],
            $paragraph([
                $text('Bold', ['bold']), $text(' and '), $text('italic', ['italic']), $text(' and '),
                $text('underline', ['underline']), $text(' and '), $text('strike', ['strike']),
                $text(' H'), $text('2', ['subscript']), $text('O and x'), $text('2', ['superscript']),
            ]),
            $paragraph([$text('A centred line')], 'center'),
            $paragraph([$text('A right-aligned line')], 'end'),
            ['type' => 'blockquote', 'content' => [$paragraph([$text('A customer quote')])]],
            ['type' => 'codeBlock', 'attrs' => ['language' => null], 'content' => [$text('EMI = P x r')]],
            ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [$paragraph([$text('A bullet point')])]]]],
            ['type' => 'orderedList', 'attrs' => ['start' => 1], 'content' => [['type' => 'listItem', 'content' => [$paragraph([$text('A numbered step')])]]]],
            ['type' => 'table', 'content' => [
                ['type' => 'tableRow', 'content' => [$cell('tableHeader', 'Lender'), $cell('tableHeader', 'Rate')]],
                ['type' => 'tableRow', 'content' => [$cell('tableCell', 'Bank A'), $cell('tableCell', 'From the rate book')]],
            ]],
        ],
    ];
}

it('saves every toolbar format, reopens it in the visual editor and shows it on the site', function () {
    // Blank first: the test helper merges a new body into the old one key by key,
    // where the browser's editor replaces it whole.
    $this->product->update(['body' => null]);

    Livewire::test(EditLoanProductContent::class, ['record' => $this->product->getRouteKey()])
        ->fillForm(['body' => bodyWithEveryToolbarFeature()])
        ->call('save')
        ->assertHasNoFormErrors();

    $saved = $this->product->refresh()->body;
    $expectedMarkup = [
        '<h2>Business loan features</h2>', '<h3>Why FynnEdge</h3>',
        '<strong>Bold</strong>', '<em>italic</em>', '<u>underline</u>', '<s>strike</s>', '<sub>2</sub>', '<sup>2</sup>',
        'style="text-align: center;">A centred line', 'style="text-align: end;">A right-aligned line',
        '<blockquote><p>A customer quote</p></blockquote>', '<pre><code>EMI = P x r</code></pre>',
        '<ul><li><p>A bullet point</p></li></ul>', '<li><p>A numbered step</p></li></ol>',
        '<th', '>Lender</p></th>', '<td', '>Bank A</p></td>',
    ];

    foreach ($expectedMarkup as $markup) {
        expect($saved)->toContain($markup);
    }

    Livewire::test(EditLoanProductContent::class, ['record' => $this->product->getRouteKey()])
        ->assertSchemaStateSet(['body_html_mode' => false])
        ->fillForm(['summary' => 'A different summary'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->product->refresh()->body)->toBe($saved);

    $page = $this->get(route('loans.show', $this->product))->assertOk();

    foreach ($expectedMarkup as $markup) {
        $page->assertSee($markup, false);
    }
});
