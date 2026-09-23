<?php

use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\PageSeos\Pages\CreatePageSeo;
use App\Filament\Resources\PageSeos\Pages\ListPageSeos;
use App\Models\Page;
use App\Models\PageSeo;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('lists page SEO entries', function () {
    $entries = PageSeo::factory()->count(2)->create();

    Livewire::test(ListPageSeos::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords($entries);
});

it('creates an entry that immediately changes the live page', function () {
    Livewire::test(CreatePageSeo::class)
        ->fillForm([
            'url_path' => '/Contact/',
            'is_active' => true,
            'seoMeta' => [
                'title' => 'Talk To Us',
                'description' => 'Reach the FynnEdge team.',
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // Stored normalised, so the row matches the URL a browser actually requests.
    expect(PageSeo::sole()->url_path)->toBe('/contact');

    $this->get('/contact')
        ->assertSee('<title>Talk To Us</title>', false)
        ->assertSee('<meta name="description" content="Reach the FynnEdge team.">', false);
});

it('rejects a second entry for the same page, however it is typed', function () {
    PageSeo::factory()->create(['url_path' => '/contact']);

    Livewire::test(CreatePageSeo::class)
        ->fillForm(['url_path' => '/Contact/?utm_source=x'])
        ->call('create')
        ->assertHasFormErrors(['url_path']);
});

it('keeps page SEO away from an admin without the permission', function () {
    $editor = User::factory()->create(['is_admin' => true]);
    $editor->syncRoles([]);

    $this->actingAs($editor);

    $this->get('/admin/page-seos')->assertForbidden();
});

it('lets a role with only the page SEO permission reach it', function () {
    $seo = User::factory()->create(['is_admin' => true]);
    $seo->syncRoles([]);
    $seo->givePermissionTo(Permission::findOrCreate('ViewAny:PageSeo'));

    $this->actingAs($seo);

    $this->get('/admin/page-seos')->assertOk();
});

it('accepts a title and description past the old 60/160 character caps', function () {
    $title = str_repeat('a', 200);
    $description = str_repeat('b', 255);

    Livewire::test(CreatePageSeo::class)
        ->fillForm([
            'url_path' => '/faqs',
            'is_active' => true,
            'seoMeta' => ['title' => $title, 'description' => $description],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(PageSeo::sole()->seoMeta->title)->toBe($title)
        ->and(PageSeo::sole()->seoMeta->description)->toBe($description);
});

it('still stops at the width of the column that stores it', function () {
    Livewire::test(CreatePageSeo::class)
        ->fillForm([
            'url_path' => '/faqs',
            'seoMeta' => ['title' => str_repeat('a', 256)],
        ])
        ->call('create')
        ->assertHasFormErrors(['seoMeta.title']);
});

it('leaves robots unset when the admin does not choose one, so the page keeps its own', function () {
    $terms = Page::factory()->published()->create(['slug' => 'terms']);
    $terms->seoMeta()->create(['robots' => 'noindex, follow']);

    Livewire::test(CreatePageSeo::class)
        ->assertSchemaStateSet(['seoMeta.robots' => null])
        ->fillForm([
            'url_path' => '/terms',
            'is_active' => true,
            'seoMeta' => ['title' => 'Only Changing The Title'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(PageSeo::sole()->seoMeta->robots)->toBeNull();

    $this->get('/terms')
        ->assertSee('<title>Only Changing The Title</title>', false)
        ->assertSee('<meta name="robots" content="noindex, follow">', false);
});

it('still overrides the page when the admin explicitly chooses a robots value', function () {
    $terms = Page::factory()->published()->create(['slug' => 'terms']);
    $terms->seoMeta()->create(['robots' => 'index, follow']);

    Livewire::test(CreatePageSeo::class)
        ->fillForm([
            'url_path' => '/terms',
            'is_active' => true,
            'seoMeta' => ['robots' => 'noindex, nofollow'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->get('/terms')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

it('keeps "index, follow" as the robots default on a record\'s own SEO section', function () {
    Livewire::test(CreatePage::class)
        ->assertSchemaStateSet(['seoMeta.robots' => 'index, follow']);
});
