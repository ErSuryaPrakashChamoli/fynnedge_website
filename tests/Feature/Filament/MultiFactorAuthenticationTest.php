<?php

use App\Models\User;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;

it('does not require existing admins to enrol in MFA before they can use the panel', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get('/admin')->assertOk();
});

it('lets an admin who has not enrolled in MFA reach their profile page to set it up', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get('/admin/profile')->assertOk();
});

it('implements the contracts Filament requires for TOTP app authentication with recovery codes', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    expect($admin)
        ->toBeInstanceOf(HasAppAuthentication::class)
        ->toBeInstanceOf(HasAppAuthenticationRecovery::class);
});

it('never serialises the MFA secret or recovery codes', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $admin->forceFill(['app_authentication_secret' => 'super-secret-totp-seed'])->save();

    $array = $admin->fresh()->toArray();

    expect($array)->not->toHaveKey('app_authentication_secret');
    expect($array)->not->toHaveKey('app_authentication_recovery_codes');
});
