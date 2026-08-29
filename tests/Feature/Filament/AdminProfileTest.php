<?php

use App\Models\User;
use Filament\Auth\Pages\EditProfile;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true, 'password' => 'old-password-123']);
    $this->actingAs($this->admin);
});

it('renders the profile page', function () {
    $this->get('/admin/profile')->assertOk();
});

it('lets an admin change their password from the profile page', function () {
    Livewire::test(EditProfile::class)
        ->fillForm([
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'currentPassword' => 'old-password-123',
            'password' => 'a-brand-new-secure-password',
            'passwordConfirmation' => 'a-brand-new-secure-password',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('a-brand-new-secure-password', $this->admin->fresh()->password))->toBeTrue();
});

it('refuses to change the password without the correct current password', function () {
    Livewire::test(EditProfile::class)
        ->fillForm([
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'currentPassword' => 'wrong-password',
            'password' => 'a-brand-new-secure-password',
            'passwordConfirmation' => 'a-brand-new-secure-password',
        ])
        ->call('save')
        ->assertHasFormErrors(['currentPassword']);

    expect(Hash::check('old-password-123', $this->admin->fresh()->password))->toBeTrue();
});
