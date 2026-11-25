<?php

use App\Filament\Clusters\AdminModuleCluster;
use App\Filament\Clusters\AdminModules;
use App\Filament\Clusters\Content\ContentCluster;
use App\Filament\Clusters\Leads\LeadsCluster;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\HelpCenter;
use App\Filament\Pages\LeadInbox;
use App\Filament\Resources\Faqs\FaqResource;
use App\Models\User;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Filament\Resources\Resource;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('puts every admin screen into a main module, leaving only the dashboard and help at the top level', function () {
    $panel = Filament::getPanel('admin');
    $clustered = collect(AdminModules::clusters())->flatMap(fn (string $cluster): array => $cluster::getClusteredComponents());

    $topLevel = collect([...$panel->getResources(), ...$panel->getPages()])
        ->reject(fn (string $screen): bool => is_subclass_of($screen, AdminModuleCluster::class))
        ->reject(fn (string $screen): bool => $clustered->contains($screen))
        ->values()
        ->all();

    expect($topLevel)->toEqualCanonicalizing([Dashboard::class, HelpCenter::class])
        ->and($clustered)->toContain(RoleResource::class);
});

it('opens every module screen for a super admin', function (string $screen) {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $url = is_subclass_of($screen, Resource::class) ? $screen::getUrl('index') : $screen::getUrl();

    $this->get($url)->assertOk();
})->with(fn (): array => array_keys(AdminModules::placements()));

it('keeps the admin addresses staff already bookmarked', function (string $path) {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $this->get($path)->assertOk();
})->with([
    '/admin/eligibility-tester',
    '/admin/faqs',
    '/admin/contact-enquiries',
    '/admin/cibil-requests',
    '/admin/newsletter-campaigns',
    '/admin/loan-landing-pages',
    '/admin/pages',
    '/admin/shield/roles',
]);

it('sends each module address to its first screen', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $this->get(LeadsCluster::getUrl())->assertRedirect(LeadInbox::getUrl());
    $this->get('/admin/content')->assertRedirect();
});

it('shows modules in the sidebar and the module\'s screens as tabs on top', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $this->get(FaqResource::getUrl('index'))
        ->assertOk()
        ->assertSee('fi-page-sub-navigation-tabs', false)
        ->assertSeeText(ContentCluster::getNavigationLabel())
        ->assertSeeText('General FAQs')
        ->assertSeeText('Page FAQs')
        ->assertSeeText('Authors')
        ->assertSeeText('Leads & Enquiries');
});

it('reopens a sidebar heading that a browser still remembers as collapsed', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    // Filament hides groups saved in localStorage first; the re-open script must run after it.
    $this->get(Dashboard::getUrl())
        ->assertOk()
        ->assertSee('data-group-label="Operations"', false)
        ->assertSeeInOrder([
            "group.classList.add('fi-collapsed')",
            '.fi-sidebar-group:not(.fi-collapsible)[data-group-label]',
            "group.classList.remove('fi-collapsed')",
        ], false);
});

it('only shows a role the modules it can use', function () {
    $marketer = User::factory()->create(['is_admin' => true]);
    $marketer->syncRoles(['Marketing']);

    $this->actingAs($marketer)
        ->get('/admin/articles')
        ->assertOk()
        ->assertSeeText('Blog & Content')
        ->assertSeeText('Website Pages')
        ->assertDontSeeText('Credit Bureau')
        ->assertDontSeeText('Administration');

    // Funnel Analytics has always been open to every admin, so the Leads module shows —
    // but none of the lead records Marketing cannot open.
    $this->get(LeadInbox::getUrl())->assertForbidden();
    $this->get('/admin/contact-enquiries')->assertForbidden();
});
