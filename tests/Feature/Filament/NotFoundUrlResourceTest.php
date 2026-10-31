<?php

use App\Filament\Resources\NotFoundUrls\NotFoundUrlResource;
use App\Filament\Resources\NotFoundUrls\Pages\ListNotFoundUrls;
use App\Filament\Resources\Redirects\Pages\CreateRedirect;
use App\Models\NotFoundUrl;
use App\Models\Redirect;
use App\Models\User;
use Livewire\Livewire;

it('lists missing pages most requested first', function () {
    $rare = NotFoundUrl::factory()->create(['hits' => 2]);
    $popular = NotFoundUrl::factory()->create(['hits' => 40]);
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(ListNotFoundUrls::class)
        ->assertCanSeeTableRecords([$popular, $rare], inOrder: true);
});

it('narrows the list to missing pages linked from this site', function () {
    $linked = NotFoundUrl::factory()->linkedInternally()->create();
    $unlinked = NotFoundUrl::factory()->create();
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(ListNotFoundUrls::class)
        ->filterTable('linked_internally')
        ->assertCanSeeTableRecords([$linked])
        ->assertCanNotSeeTableRecords([$unlinked]);
});

it('offers Add rule, opening the prefilled redirect form, only for a path with no rule yet', function () {
    $open = NotFoundUrl::factory()->create(['path' => '/old-guide']);
    $covered = NotFoundUrl::factory()->create(['path' => '/old-offer']);
    Redirect::factory()->inactive()->create(['source_path' => '/old-offer']);
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(ListNotFoundUrls::class)
        ->assertTableActionHasUrl('addRule', CreateRedirect::getUrl(['source_path' => '/old-guide']), $open)
        ->assertTableActionHidden('addRule', $covered);
});

it('refuses the list to a staff user whose role does not include it', function () {
    $editor = User::factory()->create(['is_admin' => true]);
    $editor->syncRoles([]);

    $this->actingAs($editor)->get(NotFoundUrlResource::getUrl())->assertForbidden();
});
