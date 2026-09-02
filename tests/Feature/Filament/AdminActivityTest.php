<?php

use App\Filament\Pages\AdminActivity;
use App\Models\AuditLog;
use App\Models\Lender;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('shows recent admin activity from the existing audit log to a permitted admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $lender = Lender::factory()->create(['name' => 'Acme Finance']);
    $lender->update(['name' => 'Acme Finance Ltd']);

    Livewire::test(AdminActivity::class)
        ->assertSuccessful()
        ->assertSee('Acme Finance Ltd')
        ->assertSee('Lender');
});

it('filters activity by action', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $lender = Lender::factory()->create();
    $lender->update(['name' => 'Renamed Lender']);

    $component = Livewire::test(AdminActivity::class)
        ->set('action', 'created');

    expect($component->instance()->logs()->total())->toBe(1);

    $component->set('action', 'updated');
    expect($component->instance()->logs()->total())->toBe(1);

    $component->set('action', null);
    expect($component->instance()->logs()->total())->toBe(2);
});

it('blocks a role without the View:AuditLog permission from the activity page', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles(['Marketing']);
    $this->actingAs($user);

    $this->get('/admin/admin-activity')->assertForbidden();
});

it('allows a role explicitly granted View:AuditLog', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles(['Marketing']);
    $user->givePermissionTo('View:AuditLog');
    $this->actingAs($user);

    $this->get('/admin/admin-activity')->assertOk();
});

it('renders a legacy audit row whose changes are flat values rather than {old, new} pairs without erroring', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $lender = Lender::factory()->create(['name' => 'Legacy Row Lender']);

    // A handful of rows written before this trait stored {old, new} pairs
    // hold a bare value per key instead — the activity feed must degrade
    // gracefully for these rather than throw a TypeError.
    AuditLog::query()->create([
        'auditable_type' => $lender::class,
        'auditable_id' => $lender->id,
        'user_id' => $admin->id,
        'action' => 'created',
        'changes' => ['name' => 'Legacy Row Lender', 'status' => 'active'],
    ]);

    Livewire::test(AdminActivity::class)
        ->assertSuccessful()
        ->assertSee('Legacy Row Lender')
        ->assertSee('Status');
});
