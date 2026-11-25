<?php

use App\Filament\Clusters\AdminModules;
use App\Filament\Clusters\Leads\LeadsCluster;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\HelpCenter;
use App\Filament\Pages\LeadAnalytics;
use App\Filament\Resources\Lenders\LenderResource;
use App\Filament\Resources\LoanLandingPages\LoanLandingPageResource;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Widgets\ContentStatusWidget;
use App\Filament\Widgets\LatestLeadsWidget;
use App\Filament\Widgets\LeadStatsWidget;
use App\Filament\Widgets\LeadTrendChart;
use App\Filament\Widgets\RecentContentWidget;
use App\Filament\Widgets\WelcomeWidget;
use App\Models\ContactEnquiry;
use App\Models\LoanProduct;
use App\Models\Page;
use App\Models\User;
use App\Support\Admin\AdminGuide;
use App\Support\Admin\AdminRequirements;
use App\Support\Admin\AdminWorkflows;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

it('describes every screen in the admin', function () {
    $missing = collect(AdminModules::placements())
        ->keys()
        ->reject(fn (string $screen): bool => array_key_exists($screen, AdminGuide::screens()))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

it('explains a screen under its title and in the About this screen panel', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $this->get(PageResource::getUrl('index'))
        ->assertOk()
        ->assertSeeText(AdminGuide::screens()[PageResource::class]['summary'])
        ->assertSeeText('About this screen')
        ->assertSeeText('On the website')
        ->assertSeeText('Suggested next step');
});

it('tells the admin what to create first, and stops once it exists', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $this->get(LoanLandingPageResource::getUrl('create'))
        ->assertOk()
        ->assertSeeText('Before you start')
        ->assertSeeText(AdminRequirements::all()['loan_product']['why']);

    LoanProduct::factory()->create();

    $this->get(LoanLandingPageResource::getUrl('create'))
        ->assertOk()
        ->assertDontSeeText('Before you start');
});

it('never links a role to a screen it cannot open', function () {
    $this->seed(RoleSeeder::class);
    $seo = User::factory()->create(['is_admin' => true]);
    $seo->syncRoles(['SEO']);

    $this->actingAs($seo);

    $help = $this->get(HelpCenter::getUrl())->assertOk();

    $help->assertSeeText('SEO & Performance')
        ->assertDontSeeText('Credit Bureau')
        ->assertDontSee(LenderResource::getUrl('index'), false);
});

it('shows workflows with live progress and the next step', function () {
    LoanProduct::factory()->create();

    $workflow = AdminWorkflows::resolve('loan-product');
    $statuses = collect($workflow['steps'])->pluck('status', 'label');

    expect($statuses['Create the loan product'])->toBe('done')
        ->and($statuses['Add the lenders'])->toBe('next')
        ->and(collect($workflow['steps'])->where('status', 'next'))->toHaveCount(1);

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get(HelpCenter::getUrl())
        ->assertOk()
        ->assertSeeText('Launch a new loan product')
        ->assertSeeText('How a lead travels')
        ->assertSeeText('Next step');
});

it('greets the admin on the dashboard with shortcuts they can use', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true, 'name' => 'Asha Verma']))
        ->get('/admin')
        ->assertOk()
        ->assertSeeText('Asha')
        ->assertSeeText('Create a Page')
        ->assertSeeText('Leads & Enquiries');

    ContactEnquiry::factory()->create(['name' => 'Ravi Kumar']);
    Page::factory()->published()->create(['title' => 'Partnerships']);

    Livewire::test(LatestLeadsWidget::class)->assertSeeText('Ravi Kumar');
    Livewire::test(LeadStatsWidget::class)->assertSeeText('New leads today');
    Livewire::test(ContentStatusWidget::class)->assertSeeText('General pages');
    Livewire::test(RecentContentWidget::class)->assertSeeText('Partnerships');
    Livewire::test(LeadTrendChart::class)->assertOk();
});

it('opens the dashboard on the welcome banner, without a page title or summary line', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true, 'name' => 'Asha Verma']))
        ->get(Dashboard::getUrl())
        ->assertOk()
        ->assertDontSee('fi-header-heading', false)
        ->assertDontSee('fe-screen-summary', false)
        ->assertSeeInOrder(['<h1', 'Asha', '</h1>'], false)
        ->assertSeeInOrder(['<title>', 'Dashboard', '</title>'], false);
});

it('colours each Jump to shortcut on the dashboard by its module', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(WelcomeWidget::class)->assertSeeHtmlInOrder([
        'Jump to',
        'data-module="'.LeadsCluster::getSlug().'"',
        e(LeadsCluster::getNavigationLabel()),
    ]);
});

it('opens Lead Analytics from the mobile verified figure on the dashboard', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(LeadStatsWidget::class)->assertSeeHtmlInOrder([
        'Last 7 days',
        'href="'.LeadAnalytics::getUrl().'"',
        'Mobile verified (30 days)',
    ]);
});
