<?php

use App\Filament\RelationManagers\AuditLogsRelationManager;
use App\Filament\Resources\Lenders\Pages\EditLender;
use App\Models\Lender;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('hides the history tab from a role without the View:AuditLog permission', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles(['Marketing']);
    $this->actingAs($user);

    $lender = Lender::factory()->create();

    expect(AuditLogsRelationManager::canViewForRecord($lender, EditLender::class))->toBeFalse();
});

it('shows the history tab once a role is granted the View:AuditLog permission', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles(['Marketing']);
    $user->givePermissionTo('View:AuditLog');
    $this->actingAs($user);

    $lender = Lender::factory()->create();

    expect(AuditLogsRelationManager::canViewForRecord($lender, EditLender::class))->toBeTrue();
});

it('grants a super-admin the history tab without any explicit permission grant', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $lender = Lender::factory()->create();

    expect(AuditLogsRelationManager::canViewForRecord($lender, EditLender::class))->toBeTrue();
});
