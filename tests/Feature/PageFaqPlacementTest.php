<?php

use App\Enums\FaqPlacement;
use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Filament\Resources\Faqs\Pages\ListFaqs;
use App\Filament\Resources\PageFaqs\Pages\CreatePageFaq;
use App\Filament\Resources\PageFaqs\Pages\ListPageFaqs;
use App\Models\Faq;
use App\Models\LoanProduct;
use App\Models\User;
use Livewire\Livewire;

/**
 * @param  array<int, FaqPlacement|string>  $placements
 */
function pinnedFaq(string $question, array $placements, array $attributes = []): Faq
{
    return Faq::factory()->create([
        'question' => $question,
        'answer' => 'Pinned answer.',
        'status' => PublishStatus::Published,
        'placements' => collect($placements)->map(fn ($p) => $p instanceof FaqPlacement ? $p->value : $p)->all(),
        ...$attributes,
    ]);
}

/**
 * @return array<int, array<string, mixed>>
 */
function faqPageBlocks(string $html): array
{
    return collect(explode('<script type="application/ld+json">', $html))
        ->skip(1)
        ->map(fn (string $chunk) => json_decode(explode('</script>', $chunk)[0], true))
        ->filter(fn (?array $data) => ($data['@type'] ?? null) === 'FAQPage')
        ->values()
        ->all();
}

it('shows a pinned FAQ on the page it was pinned to, visibly and as FAQPage schema', function () {
    pinnedFaq('Do you charge a fee?', [FaqPlacement::Contact]);

    $response = $this->get('/contact')->assertOk();

    $response->assertSee('Do you charge a fee?')->assertSee('Pinned answer.');

    $blocks = faqPageBlocks($response->getContent());

    expect($blocks)->toHaveCount(1);
    expect($blocks[0]['mainEntity'][0]['name'])->toBe('Do you charge a fee?');
    expect($blocks[0]['mainEntity'][0]['acceptedAnswer']['text'])->toBe('Pinned answer.');
});

it('does not show a pinned FAQ on pages it was not pinned to', function () {
    pinnedFaq('Contact only question?', [FaqPlacement::Contact]);

    $this->get('/resources')->assertOk()->assertDontSee('Contact only question?');
    $this->get('/calculators')->assertOk()->assertDontSee('Contact only question?');
});

it('shows one FAQ on every page it was pinned to', function () {
    pinnedFaq('Shared across pages?', [FaqPlacement::Contact, FaqPlacement::ResourcesIndex, FaqPlacement::CalculatorsDirectory]);

    $this->get('/contact')->assertOk()->assertSee('Shared across pages?');
    $this->get('/resources')->assertOk()->assertSee('Shared across pages?');
    $this->get('/calculators')->assertOk()->assertSee('Shared across pages?');
    $this->get('/loans')->assertOk()->assertDontSee('Shared across pages?');
});

it('applies a parameterised placement to every URL that route serves', function () {
    LoanProduct::factory()->published()->create(['slug' => 'personal-loan', 'category' => LoanCategory::PersonalLoan]);
    LoanProduct::factory()->published()->create(['slug' => 'home-loan', 'category' => LoanCategory::HomeLoan]);
    pinnedFaq('Is this on every loan page?', [FaqPlacement::LoanProductPages]);

    $this->get('/loans/personal-loan')->assertOk()->assertSee('Is this on every loan page?');
    $this->get('/loans/home-loan')->assertOk()->assertSee('Is this on every loan page?');
});

it('merges pinned FAQs into a page that already has its own, as one accordion and one FAQPage entity', function () {
    $product = LoanProduct::factory()->published()->create(['slug' => 'personal-loan', 'category' => LoanCategory::PersonalLoan]);
    $product->faqs()->create([
        'question' => 'Product specific question?',
        'answer' => 'Product answer.',
        'sort_order' => 0,
        'status' => PublishStatus::Published,
    ]);
    pinnedFaq('Site wide loan question?', [FaqPlacement::LoanProductPages]);

    $response = $this->get('/loans/personal-loan')->assertOk();

    $response->assertSee('Product specific question?')->assertSee('Site wide loan question?');

    $blocks = faqPageBlocks($response->getContent());

    expect($blocks)->toHaveCount(1);
    expect(collect($blocks[0]['mainEntity'])->pluck('name'))
        ->toEqual(collect(['Product specific question?', 'Site wide loan question?']));
});

it('merges pinned FAQs into the homepage without duplicating the general ones', function () {
    Faq::factory()->create(['question' => 'General homepage question?', 'status' => PublishStatus::Published]);
    pinnedFaq('Pinned homepage question?', [FaqPlacement::Home]);

    $response = $this->get('/')->assertOk();

    $response->assertSee('General homepage question?')->assertSee('Pinned homepage question?');
    expect(faqPageBlocks($response->getContent()))->toHaveCount(1);
});

it('excludes draft and expired pinned FAQs', function () {
    pinnedFaq('Draft pinned question?', [FaqPlacement::Contact], ['status' => PublishStatus::Draft]);
    pinnedFaq('Expired pinned question?', [FaqPlacement::Contact], ['expires_at' => now()->subDay()]);
    pinnedFaq('Live pinned question?', [FaqPlacement::Contact]);

    $this->get('/contact')
        ->assertOk()
        ->assertSee('Live pinned question?')
        ->assertDontSee('Draft pinned question?')
        ->assertDontSee('Expired pinned question?');
});

it('renders no FAQ section on a page with nothing pinned to it', function () {
    $response = $this->get('/loans')->assertOk();

    expect(faqPageBlocks($response->getContent()))->toBeEmpty();
    $response->assertDontSee('Frequently asked questions');
});

it('keeps page FAQs, general FAQs and product FAQs in separate admin lists', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $general = Faq::factory()->create(['question' => 'A general one?', 'status' => PublishStatus::Published]);
    $pinned = pinnedFaq('A pinned one?', [FaqPlacement::Contact]);
    $product = LoanProduct::factory()->published()->create();
    $productFaq = $product->faqs()->create([
        'question' => 'A product one?', 'answer' => 'x', 'sort_order' => 0, 'status' => PublishStatus::Published,
    ]);

    Livewire::actingAs($admin)->test(ListPageFaqs::class)
        ->assertCanSeeTableRecords([$pinned])
        ->assertCanNotSeeTableRecords([$general, $productFaq]);

    Livewire::actingAs($admin)->test(ListFaqs::class)
        ->assertCanSeeTableRecords([$general])
        ->assertCanNotSeeTableRecords([$pinned, $productFaq]);
});

it('lets an admin create a page FAQ pinned to several pages at once', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test(CreatePageFaq::class)
        ->fillForm([
            'question' => 'Created through the panel?',
            'answer' => 'Yes it was.',
            'placements' => [FaqPlacement::About->value, FaqPlacement::Contact->value],
            'sort_order' => 0,
            'status' => PublishStatus::Published->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $faq = Faq::query()->where('question', 'Created through the panel?')->sole();

    expect($faq->placements)->toBe(['about', 'contact']);

    $this->get('/contact')->assertOk()->assertSee('Created through the panel?');
});

it('requires at least one page to be picked', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test(CreatePageFaq::class)
        ->fillForm([
            'question' => 'No page picked?',
            'answer' => 'Nowhere to show.',
            'status' => PublishStatus::Published->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['placements']);
});

it('keeps calculator-page FAQs across a Livewire re-render, since updates run on a different route', function () {
    seedCalculatorProduct(LoanCategory::PersonalLoan);
    pinnedFaq('Pinned to the EMI calculator?', [FaqPlacement::EmiCalculators]);

    $this->get('/calculators/emi/personal-loan')
        ->assertOk()
        ->assertSee('Pinned to the EMI calculator?');

    // The component keeps the originating route in state; a re-render (moving a
    // slider) arrives on livewire.update and must not lose the pinned FAQs.
    Livewire::withQueryParams([])
        ->test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('placementRoute', 'calculators.emi')
        ->set('principal', 600000)
        ->assertSee('Pinned to the EMI calculator?');
});
