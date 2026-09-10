<?php

use App\Filament\Resources\Redirects\Pages\EditRedirect;
use App\Filament\Resources\Redirects\Pages\ListRedirects;
use App\Models\Redirect;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('lists redirects', function () {
    $redirects = Redirect::factory()->count(2)->create();

    Livewire::test(ListRedirects::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords($redirects);
});

it('edits a redirect and immediately serves the new destination', function () {
    $redirect = Redirect::factory()->create(['source_path' => '/old-page', 'destination' => '/loans']);

    Livewire::test(EditRedirect::class, ['record' => $redirect->getKey()])
        ->fillForm(['destination' => '/about'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->get('/old-page')->assertRedirect(url('/about'));
});

it('keeps redirects away from an admin without the permission', function () {
    $editor = User::factory()->create(['is_admin' => true]);
    $editor->syncRoles([]);

    $this->actingAs($editor);

    $this->get('/admin/redirects')->assertForbidden();
});

it('lets a role with only the redirect permissions manage them', function () {
    $seo = User::factory()->create(['is_admin' => true]);
    $seo->syncRoles([]);
    $seo->givePermissionTo(Permission::findOrCreate('ViewAny:Redirect'));

    $this->actingAs($seo);

    $this->get('/admin/redirects')->assertOk();
});
