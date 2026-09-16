<?php

use App\Filament\Resources\PageSeos\Pages\CreatePageSeo;
use App\Filament\Resources\PageSeos\Pages\ListPageSeos;
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
        ->assertSee('<title>Talk To Us — ', false)
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
