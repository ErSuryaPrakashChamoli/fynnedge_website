<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('lets a Marketing-role user reach their assigned modules but blocks modules outside their role', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles(['Marketing']);

    $this->actingAs($user);

    $this->get('/admin/articles')->assertOk();
    $this->get('/admin/banners')->assertOk();
    $this->get('/admin/lenders')->assertForbidden();
    $this->get('/admin/loan-products')->assertForbidden();
});

it('lets an SEO-role user reach their assigned modules but blocks modules outside their role', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles(['SEO']);

    $this->actingAs($user);

    // Phase 8: SEO no longer has blanket access to the full LoanProduct editor
    // (which also contains calculator/financial fields) — see
    // LoanProductSeoResourceTest for its replacement, scoped access.
    $this->get('/admin/loan-products')->assertForbidden();
    $this->get('/admin/articles')->assertOk();
    $this->get('/admin/banners')->assertForbidden();
});

it('lets a Marketing-role user manage marketing sections and navigation links', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles(['Marketing']);

    $this->actingAs($user);

    $this->get('/admin/marketing-sections')->assertOk();
    $this->get('/admin/navigation-links')->assertOk();
});

it('blocks an SEO-role user from marketing sections and navigation links', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles(['SEO']);

    $this->actingAs($user);

    $this->get('/admin/marketing-sections')->assertForbidden();
    $this->get('/admin/navigation-links')->assertForbidden();
});

it('lets a Marketing-role user manage how-it-works steps but blocks the media governance page', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles(['Marketing']);

    $this->actingAs($user);

    $this->get('/admin/how-it-works-steps')->assertOk();
    $this->get('/admin/media-governance')->assertForbidden();
});

it('blocks a non-super-admin role from managing users or roles', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles(['Marketing']);

    $this->actingAs($user);

    $this->get('/admin/users')->assertForbidden();
    $this->get('/admin/shield/roles')->assertForbidden();
});

it('gives a super-admin full access regardless of the modules seeded for other roles', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin);

    $this->get('/admin/articles')->assertOk();
    $this->get('/admin/lenders')->assertOk();
    $this->get('/admin/users')->assertOk();
    $this->get('/admin/shield/roles')->assertOk();
});

it('leaves a panel user with no role at all unable to reach any module', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles([]);

    $this->actingAs($user);

    $this->get('/admin/articles')->assertForbidden();
});
