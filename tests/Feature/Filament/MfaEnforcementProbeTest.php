<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

/**
 * Documents a real, empirically-verified Filament architectural limitation
 * investigated for Phase 5 MFA privilege hardening (requiring MFA only for
 * super_admin): `multiFactorAuthentication(..., isRequired: bool|Closure)`
 * accepts a Closure, but Filament evaluates it while registering panel
 * routes/middleware — a point in the request lifecycle that precedes
 * session-based auth resolution. `Filament::auth()->user()` is therefore
 * always null inside that closure, so a role-based condition (e.g.
 * `fn () => $user?->hasRole('super_admin')`) silently never enforces for
 * ANYONE, super_admin included — not a lockout, but a false sense of
 * security. This was confirmed directly against a temporary copy of
 * AdminPanelProvider during development: a static `isRequired: true`
 * correctly redirected every role to `/admin/multi-factor-authentication/set-up`
 * (proving the mechanism itself works), while the role-based closure
 * redirected no one, for either role (proving the timing issue). Per-role
 * MFA enforcement was therefore NOT implemented — see the Phase 5 report.
 * MFA stays exactly as before: opt-in for every admin role, no lockout risk.
 */
it('confirms MFA remains opt-in for every role, including super_admin, given the isRequired-closure timing limitation', function () {
    $this->seed(RoleSeeder::class);
    $superAdmin = User::factory()->create(['is_admin' => true]);
    $marketingAdmin = User::factory()->create(['is_admin' => true]);
    $marketingAdmin->syncRoles(['Marketing']);

    $this->actingAs($superAdmin)->get('/admin')->assertOk();
    $this->actingAs($marketingAdmin)->get('/admin')->assertOk();
});
