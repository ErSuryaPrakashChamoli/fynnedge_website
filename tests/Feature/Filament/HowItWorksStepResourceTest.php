<?php

use App\Enums\PublishStatus;
use App\Filament\RelationManagers\RestorableAuditLogsRelationManager;
use App\Filament\Resources\HowItWorksSteps\Pages\CreateHowItWorksStep;
use App\Filament\Resources\HowItWorksSteps\Pages\EditHowItWorksStep;
use App\Filament\Resources\HowItWorksSteps\Pages\ListHowItWorksSteps;
use App\Models\HowItWorksStep;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('lists how-it-works steps', function () {
    HowItWorksStep::factory()->count(2)->create();

    Livewire::test(ListHowItWorksSteps::class)->assertSuccessful();
});

it('creates a how-it-works step', function () {
    Livewire::test(CreateHowItWorksStep::class)
        ->fillForm([
            'title' => 'Tell us about yourself',
            'description' => 'A short profile, nothing invasive.',
            'status' => PublishStatus::Published->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(HowItWorksStep::query()->where('title', 'Tell us about yourself')->exists())->toBeTrue();
});

it('shows published steps on the homepage in place of the hardcoded default, in order', function () {
    HowItWorksStep::factory()->published()->create(['title' => 'Step Alpha', 'sort_order' => 1]);
    HowItWorksStep::factory()->published()->create(['title' => 'Step Beta', 'sort_order' => 0]);
    HowItWorksStep::factory()->create(['title' => 'Draft Step', 'status' => PublishStatus::Draft]);

    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('Step Alpha')
        ->assertSee('Step Beta')
        ->assertDontSee('Draft Step')
        ->assertDontSee('Track your application to disbursal');

    $content = $response->getContent();
    expect(strpos($content, 'Step Beta'))->toBeLessThan(strpos($content, 'Step Alpha'));
});

it('falls back to the original hardcoded steps when none are published', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Tell us about yourself')
        ->assertSee('Track your application to disbursal');
});

it('restores a how-it-works step to a previous version from its history', function () {
    $step = HowItWorksStep::factory()->create(['title' => 'Original title']);
    $step->update(['title' => 'Changed title']);

    $log = $step->auditLogs()->where('action', 'updated')->sole();

    Livewire::test(RestorableAuditLogsRelationManager::class, [
        'ownerRecord' => $step,
        'pageClass' => EditHowItWorksStep::class,
    ])->callTableAction('restore', $log);

    expect($step->fresh()->title)->toBe('Original title');
});
