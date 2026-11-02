<?php

use App\Enums\FaqPlacement;
use App\Enums\PromoBarTrigger;
use App\Enums\PublishStatus;
use App\Filament\Resources\PromoBars\Pages\CreatePromoBar;
use App\Filament\Resources\PromoBars\Pages\EditPromoBar;
use App\Filament\Resources\PromoBars\Pages\ListPromoBars;
use App\Models\PromoBar;
use App\Models\User;
use App\Support\PromoBars\PromoBars;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Storage::fake('public');

    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function promoBarFormData(array $overrides = []): array
{
    return [
        'name' => 'Festive personal loan offer',
        'headline' => 'Get up to *₹50 Lakhs* *[rate:personal-loan]*',
        'cta_label' => 'Apply now',
        'cta_url' => '/eligibility',
        'placements' => [PromoBars::EVERY_PAGE],
        'status' => PublishStatus::Published->value,
        ...$overrides,
    ];
}

it('renders the promo bar index and edit pages', function () {
    $promoBar = PromoBar::factory()->create();

    $this->get('/admin/promo-bars')->assertOk();
    $this->get("/admin/promo-bars/{$promoBar->public_id}/edit")->assertOk();
});

it('saves a promo bar with its pop-out image on the public disk', function () {
    Livewire::test(CreatePromoBar::class)
        ->fillForm(promoBarFormData([
            'image_path' => UploadedFile::fake()->image('ambassador.png', 400, 500),
            'excluded_placements' => [FaqPlacement::Contact->value],
            'trigger' => PromoBarTrigger::Delay->value,
            'trigger_value' => 5,
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    $promoBar = PromoBar::query()->sole();

    expect($promoBar->image_path)->toStartWith('promo-bars/');
    Storage::disk('public')->assertExists($promoBar->image_path);
    expect($promoBar->placements)->toBe([PromoBars::EVERY_PAGE]);
    expect($promoBar->excluded_placements)->toBe([FaqPlacement::Contact->value]);
    expect($promoBar->trigger)->toBe(PromoBarTrigger::Delay);
    expect($promoBar->trigger_value)->toBe(5);
});

it('rejects a button link that is not a web, phone or email link', function (string $url) {
    Livewire::test(CreatePromoBar::class)
        ->fillForm(promoBarFormData(['cta_url' => $url]))
        ->call('create')
        ->assertHasFormErrors(['cta_url']);

    expect(PromoBar::query()->exists())->toBeFalse();
})->with(['javascript:alert(1)', '//evil.example/steal']);

it('rejects a colour that is not a hex code', function () {
    Livewire::test(CreatePromoBar::class)
        ->fillForm(promoBarFormData(['background_color' => 'red;position:fixed']))
        ->call('create')
        ->assertHasFormErrors(['background_color']);
});

it('requires an end date when the countdown is switched on', function () {
    Livewire::test(CreatePromoBar::class)
        ->fillForm(promoBarFormData(['show_countdown' => true]))
        ->call('create')
        ->assertHasFormErrors(['expires_at' => 'required']);
});

it('switches a promo bar off and on from the list', function () {
    $promoBar = PromoBar::factory()->create(['status' => PublishStatus::Published]);

    Livewire::test(ListPromoBars::class)
        ->call('updateTableColumnState', 'active', (string) $promoBar->getKey(), false);

    expect($promoBar->refresh()->status)->toBe(PublishStatus::Draft);

    Livewire::test(ListPromoBars::class)
        ->call('updateTableColumnState', 'active', (string) $promoBar->getKey(), true);

    expect($promoBar->refresh()->status)->toBe(PublishStatus::Published);
});

it('ignores the Active switch for a user who may list promo bars but not edit them', function () {
    $viewer = User::factory()->create(['is_admin' => true]);
    $viewer->syncRoles([]);
    $viewer->givePermissionTo(Permission::findOrCreate('ViewAny:PromoBar'));
    $promoBar = PromoBar::factory()->create(['status' => PublishStatus::Published]);

    $this->actingAs($viewer);

    Livewire::test(ListPromoBars::class)
        ->call('updateTableColumnState', 'active', (string) $promoBar->getKey(), false);

    expect($promoBar->refresh()->status)->toBe(PublishStatus::Published);
});

it('rewrites the typed rates in an existing promo bar as the rate token, so it saves again', function () {
    $promoBar = PromoBar::factory()->create(promoBarFormData([
        'headline' => 'Get up to *₹50 Lakhs* starting at *10.49%* p.a.',
        'headline_hi' => '*₹50 लाख* तक पाएं, *10.49%* प्रति वर्ष से शुरू',
        'rotating_messages' => ['Compare offers from *30+ banks & NBFCs* in minutes', 'Personal loans @ 10.5% onwards'],
    ]));

    (require database_path('migrations/2026_11_02_055940_replace_typed_rates_in_promo_bars.php'))->up();

    $promoBar->refresh();

    expect($promoBar->headline)->toBe('Get up to *₹50 Lakhs* *[rate:personal-loan]*')
        ->and($promoBar->headline_hi)->toBe('*₹50 लाख* तक पाएं, *[rate:personal-loan]*')
        ->and($promoBar->rotating_messages)->toBe(['Compare offers from *30+ banks & NBFCs* in minutes', 'Personal loans [rate:personal-loan]']);

    Livewire::test(EditPromoBar::class, ['record' => $promoBar->public_id])
        ->fillForm(['status' => PublishStatus::Draft->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($promoBar->refresh()->status)->toBe(PublishStatus::Draft);
});

it('duplicates a promo bar as a draft copy', function () {
    $promoBar = PromoBar::factory()->create(['name' => 'Festive offer']);

    Livewire::test(ListPromoBars::class)
        ->callTableAction('replicate', $promoBar);

    $copy = PromoBar::query()->whereKeyNot($promoBar->getKey())->sole();

    expect($copy->name)->toBe('Festive offer (copy)');
    expect($copy->status)->toBe(PublishStatus::Draft);
    expect($copy->public_id)->not->toBe($promoBar->public_id);
});
