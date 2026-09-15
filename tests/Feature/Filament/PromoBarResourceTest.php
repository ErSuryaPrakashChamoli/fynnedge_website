<?php

use App\Enums\FaqPlacement;
use App\Enums\PromoBarTrigger;
use App\Enums\PublishStatus;
use App\Filament\Resources\PromoBars\Pages\CreatePromoBar;
use App\Filament\Resources\PromoBars\Pages\ListPromoBars;
use App\Models\PromoBar;
use App\Models\User;
use App\Support\PromoBars\PromoBars;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

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
        'headline' => 'Get up to *₹50 Lakhs* starting at 9.99%',
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

it('duplicates a promo bar as a draft copy', function () {
    $promoBar = PromoBar::factory()->create(['name' => 'Festive offer']);

    Livewire::test(ListPromoBars::class)
        ->callTableAction('replicate', $promoBar);

    $copy = PromoBar::query()->whereKeyNot($promoBar->getKey())->sole();

    expect($copy->name)->toBe('Festive offer (copy)');
    expect($copy->status)->toBe(PublishStatus::Draft);
    expect($copy->public_id)->not->toBe($promoBar->public_id);
});
