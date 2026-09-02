<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->create(['is_admin' => true]);
});

it('renders the users index and lists users', function () {
    $this->actingAs($this->admin);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$this->admin]);
});

it('lets a super-admin create a user and assign a role', function () {
    $this->actingAs($this->admin);

    $marketing = Role::findByName('Marketing');

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Marketing User',
            'email' => 'marketing@example.com',
            'password' => 'password',
            'is_admin' => true,
            'roles' => [$marketing->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'marketing@example.com')->firstOrFail();

    expect($user->is_admin)->toBeTrue();
    expect($user->hasRole('Marketing'))->toBeTrue();
    expect($user->hasRole('super_admin'))->toBeFalse();
});
