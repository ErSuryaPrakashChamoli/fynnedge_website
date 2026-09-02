<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

/**
 * Phase 6.3 security audit finding: CalculatorPageResource had no Shield
 * policy at all (unlike every sibling content resource), so Filament fell
 * back to Gate::before resolution instead of a real policy check — any
 * logged-in admin regardless of role could reach it. Fixed by generating
 * CalculatorPagePolicy the same way every other resource already has one.
 */
it('blocks a role without ViewAny:CalculatorPage from managing calculator pages', function () {
    $this->seed(RoleSeeder::class);
    $seo = User::factory()->create(['is_admin' => true]);
    $seo->syncRoles(['SEO']);

    $this->actingAs($seo)->get('/admin/calculator-pages')->assertForbidden();
});

it('blocks a panel user with no role at all', function () {
    $this->seed(RoleSeeder::class);
    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles([]);

    $this->actingAs($user)->get('/admin/calculator-pages')->assertForbidden();
});

it('lets the Marketing role manage calculator pages, matching its other content permissions', function () {
    $this->seed(RoleSeeder::class);
    $marketing = User::factory()->create(['is_admin' => true]);
    $marketing->syncRoles(['Marketing']);

    $this->actingAs($marketing)->get('/admin/calculator-pages')->assertOk();
});

it('allows a super-admin to manage calculator pages', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get('/admin/calculator-pages')->assertOk();
});
