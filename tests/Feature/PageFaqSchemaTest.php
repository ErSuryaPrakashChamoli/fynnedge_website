<?php

use App\Enums\PublishStatus;
use App\Filament\RelationManagers\FaqsRelationManager;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\Faq;
use App\Models\Page;
use App\Models\SeoMeta;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/**
 * Pulls the decoded JSON-LD block of the given @type out of rendered HTML.
 *
 * @return array<array-key, mixed>|null
 */
function jsonLdOfType(string $html, string $type): ?array
{
    return collect(explode('<script type="application/ld+json">', $html))
        ->skip(1)
        ->map(fn (string $chunk) => json_decode(explode('</script>', $chunk)[0], true))
        ->first(fn (?array $data) => ($data['@type'] ?? null) === $type);
}

function publishedPage(string $slug): Page
{
    return Page::factory()->create([
        'slug' => $slug,
        'status' => PublishStatus::Published,
        'published_at' => now(),
    ]);
}

it('emits FAQPage JSON-LD, with a valid @context, from FAQs attached to a page', function () {
    $page = publishedPage('disclaimer');
    $page->faqs()->create([
        'question' => 'What is the minimum salary required for a personal loan?',
        'answer' => 'Most private banks and NBFCs require a minimum net monthly salary of ₹25,000.',
        'sort_order' => 0,
        'status' => PublishStatus::Published,
    ]);

    $response = $this->get('/disclaimer');

    $response->assertOk();

    $json = jsonLdOfType($response->getContent(), 'FAQPage');

    expect($json)->not->toBeNull();
    expect($json['@context'])->toBe('https://schema.org');
    expect($json['mainEntity'][0]['@type'])->toBe('Question');
    expect($json['mainEntity'][0]['name'])->toBe('What is the minimum salary required for a personal loan?');
    expect($json['mainEntity'][0]['acceptedAnswer']['@type'])->toBe('Answer');
    expect($json['mainEntity'][0]['acceptedAnswer']['text'])->toStartWith('Most private banks and NBFCs');
});

it('also renders the page FAQs visibly, so the schema is not markup-only', function () {
    $page = publishedPage('disclaimer');
    $page->faqs()->create([
        'question' => 'How does a flexi loan differ from a personal loan?',
        'answer' => 'A flexi loan acts as an active line of credit or overdraft.',
        'sort_order' => 0,
        'status' => PublishStatus::Published,
    ]);

    $this->get('/disclaimer')
        ->assertOk()
        ->assertSee('Frequently asked questions')
        ->assertSee('How does a flexi loan differ from a personal loan?')
        ->assertSee('A flexi loan acts as an active line of credit or overdraft.');
});

it('excludes draft and expired FAQs from both the visible list and the schema', function () {
    $page = publishedPage('disclaimer');
    $page->faqs()->createMany([
        ['question' => 'Live question?', 'answer' => 'Live answer.', 'sort_order' => 0, 'status' => PublishStatus::Published],
        ['question' => 'Draft question?', 'answer' => 'Draft answer.', 'sort_order' => 1, 'status' => PublishStatus::Draft],
        ['question' => 'Expired question?', 'answer' => 'Expired answer.', 'sort_order' => 2, 'status' => PublishStatus::Published, 'expires_at' => now()->subDay()],
    ]);

    $response = $this->get('/disclaimer');

    $response->assertOk()
        ->assertSee('Live question?')
        ->assertDontSee('Draft question?')
        ->assertDontSee('Expired question?');

    expect(collect(jsonLdOfType($response->getContent(), 'FAQPage')['mainEntity'])->pluck('name'))
        ->toEqual(collect(['Live question?']));
});

it('emits no FAQPage JSON-LD on a page with no FAQs', function () {
    publishedPage('disclaimer');

    $response = $this->get('/disclaimer');

    $response->assertOk()->assertDontSee('FAQPage', false);
    expect(jsonLdOfType($response->getContent(), 'FAQPage'))->toBeNull();
});

it('keeps page FAQs out of the general FAQs page, which is scoped to unattached ones', function () {
    $page = publishedPage('disclaimer');
    $page->faqs()->create(['question' => 'Page-scoped question?', 'answer' => 'Answer.', 'sort_order' => 0, 'status' => PublishStatus::Published]);
    Faq::factory()->create(['question' => 'General question?']);

    $this->get('/faqs')
        ->assertOk()
        ->assertSee('General question?')
        ->assertDontSee('Page-scoped question?');
});

it('renders admin-authored custom JSON-LD from the page SEO section', function () {
    $page = publishedPage('disclaimer');
    $page->seoMeta()->save(new SeoMeta([
        'structured_data' => [
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => 'Loan matchmaking',
        ],
    ]));

    $response = $this->get('/disclaimer');

    $response->assertOk();

    $json = jsonLdOfType($response->getContent(), 'Service');

    expect($json)->not->toBeNull();
    expect($json['@context'])->toBe('https://schema.org');
    expect($json['name'])->toBe('Loan matchmaking');
});

it('escapes a closing script tag inside custom JSON-LD so it cannot break out of the tag', function () {
    $page = publishedPage('disclaimer');
    $page->seoMeta()->save(new SeoMeta([
        'structured_data' => [
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => '</script><script>alert(1)</script>',
        ],
    ]));

    $response = $this->get('/disclaimer');

    $response->assertOk()->assertDontSee('<script>alert(1)</script>', false);

    expect(jsonLdOfType($response->getContent(), 'Service')['name'])->toBe('</script><script>alert(1)</script>');
});

it('emits no custom JSON-LD block when the SEO section leaves it blank', function () {
    $page = publishedPage('disclaimer');
    $page->seoMeta()->save(new SeoMeta(['title' => 'Just an SEO title']));

    $this->get('/disclaimer')->assertOk()->assertDontSee('"@type":"Service"', false);
});

it('lets an admin manage a page\'s FAQs through the relation manager', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $page = publishedPage('disclaimer');
    $faq = $page->faqs()->create(['question' => 'Existing question?', 'answer' => 'Existing answer.', 'sort_order' => 0, 'status' => PublishStatus::Published]);

    Livewire::actingAs($admin)
        ->test(FaqsRelationManager::class, ['ownerRecord' => $page, 'pageClass' => EditPage::class])
        ->assertCanSeeTableRecords([$faq])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'question' => 'Newly added question?',
            'answer' => 'Newly added answer.',
            'sort_order' => 1,
            'status' => PublishStatus::Published->value,
        ])
        ->assertHasNoActionErrors();

    expect($page->faqs()->pluck('question'))->toContain('Newly added question?');
    expect(Faq::query()->where('question', 'Newly added question?')->value('faqable_type'))->toBe(Page::class);
});

it('rejects invalid custom JSON-LD when saving a page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $page = publishedPage('disclaimer');

    Livewire::actingAs($admin)
        ->test(EditPage::class, ['record' => $page->public_id])
        ->fillForm(['seoMeta' => ['structured_data' => '{ not valid json']])
        ->call('save')
        ->assertHasFormErrors(['seoMeta.structured_data']);

    expect($page->fresh()->seoStructuredData())->toBeNull();
});

it('stores valid custom JSON-LD as decoded data when saving a page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $page = publishedPage('disclaimer');

    Livewire::actingAs($admin)
        ->test(EditPage::class, ['record' => $page->public_id])
        ->fillForm(['seoMeta' => ['structured_data' => '{"@context":"https://schema.org","@type":"Service","name":"Loan matchmaking"}']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($page->fresh()->seoStructuredData())->toBe([
        '@context' => 'https://schema.org',
        '@type' => 'Service',
        'name' => 'Loan matchmaking',
    ]);
});
