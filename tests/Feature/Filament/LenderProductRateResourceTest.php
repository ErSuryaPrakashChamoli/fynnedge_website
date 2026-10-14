<?php

use App\Enums\LoanCategory;
use App\Enums\RateStatus;
use App\Enums\RateVerificationStatus;
use App\Filament\Resources\LenderProductRates\Pages\CreateLenderProductRate;
use App\Filament\Resources\LenderProductRates\Pages\EditLenderProductRate;
use App\Filament\Resources\LenderProductRates\Pages\ListLenderProductRates;
use App\Filament\Resources\LenderProductRates\Pages\ViewLenderProductRate;
use App\Filament\Resources\LenderProductRates\RelationManagers\VersionsRelationManager;
use App\Filament\Widgets\RateHealthOverview;
use App\Models\LenderProduct;
use App\Models\LenderProductRate;
use App\Models\LoanProduct;
use App\Models\User;
use App\Support\Rates\RateAuditCsv;
use App\Support\Rates\RateRoles;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

function rateAdmin(string $role): User
{
    return tap(User::factory()->create(['is_admin' => true]))->syncRoles([$role]);
}

function rateFormOffer(): LenderProduct
{
    return LenderProduct::factory()
        ->for(LoanProduct::factory()->published()->state(['category' => LoanCategory::PersonalLoan]), 'loanProduct')
        ->create();
}

/**
 * @return array<string, mixed>
 */
function validRateForm(LenderProduct $offer, array $overrides = []): array
{
    return [
        'loan_product' => $offer->loan_product_id,
        'lender_product_id' => $offer->id,
        'rate_unit' => 'per_annum',
        'rate_min' => 10.49,
        'rate_max' => 24,
        'source_url' => 'https://www.lender.example/personal-loan/rates',
        'source_notes' => 'Interest rate 10.49% to 24% p.a.',
        'last_verified_at' => now()->toDateString(),
        'verification_status' => RateVerificationStatus::Verified->value,
        ...$overrides,
    ];
}

it('gives each role the right access to the interest rates screens', function (string $role, int $listStatus, int $createStatus) {
    $this->actingAs(rateAdmin($role));

    $this->get('/admin/interest-rates')->assertStatus($listStatus);
    $this->get('/admin/interest-rates/create')->assertStatus($createStatus);
})->with([
    'marketing editor' => [RateRoles::EDITOR_ROLE, 200, 200],
    'marketing publisher' => [RateRoles::PUBLISHER_ROLE, 200, 200],
    'read-only reviewer' => [RateRoles::REVIEWER_ROLE, 200, 403],
]);

it('keeps roles without rate permissions out', function () {
    $this->seed(RoleSeeder::class);
    $this->actingAs(rateAdmin('SEO'));

    $this->get('/admin/interest-rates')->assertForbidden();
});

it('saves an editor\'s new rate as a draft that is not yet on the site', function () {
    $editor = rateAdmin(RateRoles::EDITOR_ROLE);
    $this->actingAs($editor);
    $offer = rateFormOffer();

    Livewire::test(CreateLenderProductRate::class)
        ->fillForm(validRateForm($offer))
        ->call('create')
        ->assertHasNoFormErrors();

    $rate = LenderProductRate::query()->sole();
    expect($rate)
        ->status->toBe(RateStatus::Draft)
        ->lender_product_id->toBe($offer->id)
        ->rate_min->toBe('10.49')
        ->created_by->toBe($editor->id);
});

it('rejects invalid rate figures and a non-https source', function (array $overrides, string $field) {
    $this->actingAs(rateAdmin(RateRoles::EDITOR_ROLE));

    Livewire::test(CreateLenderProductRate::class)
        ->fillForm(validRateForm(rateFormOffer(), $overrides))
        ->call('create')
        ->assertHasFormErrors([$field]);

    expect(LenderProductRate::query()->count())->toBe(0);
})->with([
    'negative rate' => [['rate_min' => -1], 'rate_min'],
    'rate above the per-annum cap' => [['rate_min' => 75, 'rate_max' => null], 'rate_min'],
    'maximum below starting rate' => [['rate_min' => 12, 'rate_max' => 10], 'rate_max'],
    'promotion with no end' => [['promo_rate' => 9.5, 'promo_label' => 'Offer'], 'promo_ends_at'],
    'insecure source link' => [['source_url' => 'http://www.lender.example/rates'], 'source_url'],
    'verification date in the future' => [['last_verified_at' => now()->addWeek()->toDateString()], 'last_verified_at'],
]);

it('opens a published rate read-only instead of in the edit form', function () {
    $this->actingAs(rateAdmin(RateRoles::PUBLISHER_ROLE));
    $live = LenderProductRate::factory()->published()->for(rateFormOffer(), 'lenderProduct')->create();

    Livewire::test(EditLenderProductRate::class, ['record' => $live->getRouteKey()])
        ->assertRedirect(ViewLenderProductRate::getUrl(['record' => $live]));
});

it('shows the publish action to a publisher but not to an editor', function () {
    $pending = LenderProductRate::factory()->pendingApproval()->for(rateFormOffer(), 'lenderProduct')->create();

    $this->actingAs(rateAdmin(RateRoles::EDITOR_ROLE));
    Livewire::test(ListLenderProductRates::class, ['activeTab' => 'all'])->assertTableActionHidden('publish', $pending);

    $this->actingAs(rateAdmin(RateRoles::PUBLISHER_ROLE));
    Livewire::test(ListLenderProductRates::class, ['activeTab' => 'all'])
        ->callTableAction('publish', $pending)
        ->assertNotified('Published — the website now shows this rate');

    expect($pending->fresh()->status)->toBe(RateStatus::Published);
});

it('publishes the selected rates that pass the checks and reports the rest', function () {
    $publisher = rateAdmin(RateRoles::PUBLISHER_ROLE);
    $this->actingAs($publisher);
    $ready = LenderProductRate::factory()->pendingApproval()->for(rateFormOffer(), 'lenderProduct')->create();
    $unsourced = LenderProductRate::factory()->unverified()->for(rateFormOffer(), 'lenderProduct')->create();

    Livewire::test(ListLenderProductRates::class, ['activeTab' => 'all'])
        ->callTableBulkAction('bulkPublish', [$ready, $unsourced]);

    expect($ready->fresh()->status)->toBe(RateStatus::Published)
        ->and($unsourced->fresh()->status)->toBe(RateStatus::Draft);
});

it('leaves selected rates that are already live alone instead of reporting them as failures', function () {
    $this->actingAs(rateAdmin(RateRoles::PUBLISHER_ROLE));
    $live = LenderProductRate::factory()->published()->for(rateFormOffer(), 'lenderProduct')->create(['published_at' => now()->subWeek()]);
    $pending = LenderProductRate::factory()->pendingApproval()->for(rateFormOffer(), 'lenderProduct')->create();

    Livewire::test(ListLenderProductRates::class, ['activeTab' => 'all'])
        ->callTableBulkAction('bulkPublish', [$live, $pending])
        ->assertNotified('Published 1 rate');

    expect($pending->fresh()->status)->toBe(RateStatus::Published)
        ->and($live->fresh())->status->toBe(RateStatus::Published)->published_at->toDateString()->toBe(now()->subWeek()->toDateString());
});

it('says there is nothing to publish when every selected rate is already live', function () {
    $this->actingAs(rateAdmin(RateRoles::PUBLISHER_ROLE));
    $live = LenderProductRate::factory()->published()->count(2)->for(rateFormOffer(), 'lenderProduct')->create();

    Livewire::test(ListLenderProductRates::class, ['activeTab' => 'all'])
        ->callTableBulkAction('bulkPublish', $live)
        ->assertNotified('Nothing to publish');
});

it('offers bulk publishing only on tabs that can list drafts or submitted rates', function (string $tab, bool $offered) {
    $this->actingAs(rateAdmin(RateRoles::PUBLISHER_ROLE));

    $component = Livewire::test(ListLenderProductRates::class, ['activeTab' => $tab]);

    $offered ? $component->assertTableBulkActionVisible('bulkPublish') : $component->assertTableBulkActionHidden('bulkPublish');
})->with([
    'needs attention' => ['attention', true],
    'awaiting approval' => ['pending', true],
    'drafts' => ['drafts', true],
    'all' => ['all', true],
    'live on site' => ['live', false],
    'expired' => ['expired', false],
    'archived versions' => ['history', false],
]);

it('restores an archived version from the history as a new draft', function () {
    $this->actingAs(rateAdmin(RateRoles::EDITOR_ROLE));
    $offer = rateFormOffer();
    $old = LenderProductRate::factory()->for($offer, 'lenderProduct')->create(['status' => RateStatus::Archived, 'rate_min' => 9.9]);
    $current = LenderProductRate::factory()->published()->for($offer, 'lenderProduct')->create(['series_id' => $old->series_id, 'version' => 2, 'rate_min' => 10.9]);

    Livewire::test(VersionsRelationManager::class, ['ownerRecord' => $current, 'pageClass' => ViewLenderProductRate::class])
        ->callTableAction('newVersion', $old);

    $draft = LenderProductRate::query()->where('series_id', $old->series_id)->where('version', 3)->sole();
    expect($draft)->status->toBe(RateStatus::Draft)->rate_min->toBe('9.90')
        ->and($current->fresh()->status)->toBe(RateStatus::Published);
});

it('exports the rates the table shows as a CSV with their evidence', function () {
    $this->actingAs(rateAdmin(RateRoles::REVIEWER_ROLE));
    LenderProductRate::factory()->published()->for(rateFormOffer(), 'lenderProduct')->create();

    $response = Livewire::test(ListLenderProductRates::class, ['activeTab' => 'all'])
        ->callAction('exportCsv');

    $response->assertFileDownloaded('interest-rate-audit-'.LenderProductRate::today()->format('Y-m-d').'.csv');
});

it('writes each rate\'s evidence into the CSV and neutralises spreadsheet formulas', function () {
    LenderProductRate::factory()->published()->for(rateFormOffer(), 'lenderProduct')->create([
        'rate_min' => 10.49,
        'source_url' => 'https://www.lender.example/pl-rates',
        'source_notes' => '=HYPERLINK("https://evil.example")',
    ]);

    ob_start();
    RateAuditCsv::download(LenderProductRate::query())->sendContent();
    $csv = ob_get_clean();

    expect($csv)
        ->toContain('"Loan type",Lender,')
        ->toContain('10.49')
        ->toContain('https://www.lender.example/pl-rates')
        ->toContain("'=HYPERLINK")
        ->not->toContain(',=HYPERLINK');
});

it('summarises rate health for anyone who can view rates', function () {
    $this->actingAs(rateAdmin(RateRoles::REVIEWER_ROLE));
    LenderProductRate::factory()->pendingApproval()->for(rateFormOffer(), 'lenderProduct')->create();
    LenderProductRate::factory()->unverified()->for(rateFormOffer(), 'lenderProduct')->create();

    Livewire::test(RateHealthOverview::class)
        ->assertSee('Awaiting approval')
        ->assertSee('Need verification')
        ->assertSee('Loan types with no verified rate');
});
