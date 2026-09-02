<?php

use App\Enums\PublishStatus;
use App\Filament\RelationManagers\RestorableAuditLogsRelationManager;
use App\Filament\Resources\MarketingSections\Pages\CreateMarketingSection;
use App\Filament\Resources\MarketingSections\Pages\EditMarketingSection;
use App\Filament\Resources\MarketingSections\Pages\ListMarketingSections;
use App\Models\MarketingSection;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('lists marketing sections', function () {
    MarketingSection::factory()->count(2)->create();

    Livewire::test(ListMarketingSections::class)->assertSuccessful();
});

it('creates a marketing section for a homepage placement', function () {
    Livewire::test(CreateMarketingSection::class)
        ->fillForm([
            'placement' => 'home_finance_cta',
            'heading' => 'Talk to a loan specialist today',
            'description' => 'Free, no-obligation guidance.',
            'cta_label' => 'Check Eligibility',
            'cta_url' => '/eligibility',
            'status' => PublishStatus::Published->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(MarketingSection::query()->where('placement', 'home_finance_cta')->exists())->toBeTrue();
});

it('shows the homepage finance CTA once a marketing section is published for it, replacing the hardcoded default', function () {
    MarketingSection::factory()->published()->create([
        'placement' => 'home_finance_cta',
        'heading' => 'Talk to a loan specialist today',
        'description' => 'Free, no-obligation guidance.',
        'cta_label' => 'Speak to us',
        'cta_url' => '/contact',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Talk to a loan specialist today')
        ->assertSee('Speak to us')
        ->assertDontSee('Not sure which loan or lender fits your profile?');
});

it('leaves the hardcoded homepage CTA in place when no marketing section is published for that placement', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Not sure which loan or lender fits your profile?');
});

it('restores a marketing section to a previous version from its history', function () {
    $section = MarketingSection::factory()->create(['heading' => 'Original heading']);
    $section->update(['heading' => 'Changed heading']);

    $updateLog = $section->auditLogs()->where('action', 'updated')->sole();

    Livewire::test(RestorableAuditLogsRelationManager::class, [
        'ownerRecord' => $section,
        'pageClass' => EditMarketingSection::class,
    ])
        ->callTableAction('restore', $updateLog);

    expect($section->fresh()->heading)->toBe('Original heading');
});
