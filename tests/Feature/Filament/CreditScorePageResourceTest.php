<?php

use App\Filament\Resources\CreditScorePages\Pages\CreateCreditScorePage;
use App\Filament\Resources\CreditScorePages\Pages\EditCreditScorePage;
use App\Filament\Resources\CreditScorePages\Pages\ListCreditScorePages;
use App\Models\CreditScorePage;
use App\Models\User;
use App\Modules\CreditScore\Enums\BureauName;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('lists one row per credit score page', function () {
    $pages = collect(BureauName::cases())->map(fn (BureauName $bureau) => CreditScorePage::factory()->create(['bureau' => $bureau]));

    Livewire::test(ListCreditScorePages::class)->assertCanSeeTableRecords($pages);
});

it('creates the About content for a bureau page', function () {
    Livewire::test(CreateCreditScorePage::class)
        ->fillForm(['bureau' => 'experian', 'title' => 'About Experian', 'body' => '<p>New Experian copy.</p>'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(CreditScorePage::query()->where('bureau', 'experian')->first())
        ->title->toBe('About Experian')
        ->body->toContain('New Experian copy.');
});

it('edits one bureau page without changing another', function () {
    $cibil = CreditScorePage::factory()->create(['bureau' => BureauName::Cibil, 'body' => '<p>CIBIL copy.</p>']);
    $crif = CreditScorePage::factory()->create(['bureau' => BureauName::Crif, 'body' => '<p>CRIF copy.</p>']);

    Livewire::test(EditCreditScorePage::class, ['record' => $cibil->getRouteKey()])
        ->fillForm(['body' => '<p>Changed CIBIL copy.</p>'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($cibil->fresh()->body)->toContain('Changed CIBIL copy.')
        ->and($crif->fresh()->body)->toBe('<p>CRIF copy.</p>');
});

it('requires a bureau that has no page row yet', function () {
    CreditScorePage::factory()->create(['bureau' => BureauName::Cibil]);

    Livewire::test(CreateCreditScorePage::class)
        ->fillForm(['bureau' => 'cibil', 'body' => '<p>Duplicate.</p>'])
        ->call('create')
        ->assertHasFormErrors(['bureau' => 'unique']);
});

it('rejects a bureau that is not a real credit score page', function () {
    Livewire::test(CreateCreditScorePage::class)
        ->fillForm(['bureau' => 'check-cibil', 'body' => '<p>Nowhere to show this.</p>'])
        ->call('create')
        ->assertHasFormErrors(['bureau']);

    expect(CreditScorePage::query()->count())->toBe(0);
});
