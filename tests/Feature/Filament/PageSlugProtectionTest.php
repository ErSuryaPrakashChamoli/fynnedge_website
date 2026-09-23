<?php

use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\Page;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('derives the slug from the title while creating a page', function () {
    Livewire::test(CreatePage::class)
        ->fillForm(['title' => 'Refund Policy'])
        ->assertSchemaStateSet(['slug' => 'refund-policy']);
});

it('keeps the existing slug when the title of a page is edited', function () {
    $page = Page::factory()->create(['title' => 'Old Title', 'slug' => 'kept-slug']);

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->fillForm(['title' => 'A Completely New Title'])
        ->assertSchemaStateSet(['slug' => 'kept-slug'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($page->refresh())
        ->title->toBe('A Completely New Title')
        ->slug->toBe('kept-slug');
});

it('locks the slug of a page a public route depends on', function (string $slug) {
    $page = Page::factory()->published()->create(['slug' => $slug]);

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->assertFormFieldDisabled('slug')
        ->fillForm(['title' => 'Renamed', 'slug' => 'something-else'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($page->refresh()->slug)->toBe($slug);
    $this->get("/{$slug}")->assertOk();
})->with(['terms', 'privacy-policy', 'disclaimer', 'grievance', 'credit-report-terms', 'about', 'careers']);

it('leaves the slug of an ordinary page editable', function () {
    $page = Page::factory()->create(['slug' => 'draft-notes']);

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->assertFormFieldEnabled('slug');
});
