<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

it('blocks a role without ViewAny:CreditScorePage from managing credit score pages', function () {
    $this->seed(RoleSeeder::class);
    $seo = User::factory()->create(['is_admin' => true]);
    $seo->syncRoles(['SEO']);

    $this->actingAs($seo)->get('/admin/credit-score-pages')->assertForbidden();
});

it('lets the Marketing role manage credit score pages, matching its other content permissions', function () {
    $this->seed(RoleSeeder::class);
    $marketing = User::factory()->create(['is_admin' => true]);
    $marketing->syncRoles(['Marketing']);

    $this->actingAs($marketing)->get('/admin/credit-score-pages')->assertOk();
});
