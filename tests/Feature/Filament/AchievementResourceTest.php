<?php

use App\Enums\PublishStatus;
use App\Filament\RelationManagers\RestorableAuditLogsRelationManager;
use App\Filament\Resources\Achievements\Pages\CreateAchievement;
use App\Filament\Resources\Achievements\Pages\EditAchievement;
use App\Filament\Resources\Achievements\Pages\ListAchievements;
use App\Models\Achievement;
use App\Models\Lender;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('lists achievements', function () {
    Achievement::factory()->count(2)->create();

    Livewire::test(ListAchievements::class)->assertSuccessful();
});

it('creates an achievement', function () {
    Livewire::test(CreateAchievement::class)
        ->fillForm([
            'label' => 'Cities served',
            'value' => '550',
            'suffix' => '+',
            'status' => PublishStatus::Published->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Achievement::query()->where('label', 'Cities served')->value('value'))->toBe('550');
});

it('requires a metric name and a value', function () {
    Livewire::test(CreateAchievement::class)
        ->fillForm(['label' => '', 'value' => ''])
        ->call('create')
        ->assertHasFormErrors(['label', 'value']);
});

it('composes the displayed figure from prefix, value and suffix', function () {
    $achievement = Achievement::factory()->make(['prefix' => '₹', 'value' => '20', 'suffix' => 'Cr+']);

    expect($achievement->displayValue())->toBe('₹20Cr+');
});

it('shows published achievements on the homepage in place of the derived stats, in order', function () {
    Achievement::factory()->published()->create(['label' => 'Cities served', 'value' => '550', 'suffix' => '+', 'sort_order' => 1]);
    Achievement::factory()->published()->create(['label' => 'Customers assisted', 'value' => '1200', 'suffix' => '+', 'sort_order' => 0]);
    Achievement::factory()->create(['label' => 'Draft metric', 'status' => PublishStatus::Draft]);

    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('Cities served')
        ->assertSee('Customers assisted')
        ->assertSee('1200+')
        ->assertDontSee('Draft metric')
        ->assertDontSee('Partner banks');

    $content = $response->getContent();
    expect(strpos($content, 'Customers assisted'))->toBeLessThan(strpos($content, 'Cities served'));
});

/**
 * The fallback is the whole reason nothing is seeded into this table: an
 * empty achievements list must leave the hero showing figures the app can
 * actually derive from published records, never an unverified claim and
 * never a blank row.
 */
it('falls back to the stats derived from real records when no achievement is published', function () {
    Lender::factory()->create();
    Achievement::factory()->create(['label' => 'Unpublished metric', 'status' => PublishStatus::Draft]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Partner banks')
        ->assertDontSee('Unpublished metric');
});

it('restores an achievement to a previous version from its history', function () {
    $achievement = Achievement::factory()->create(['value' => '400']);
    $achievement->update(['value' => '550']);

    $log = $achievement->auditLogs()->where('action', 'updated')->sole();

    Livewire::test(RestorableAuditLogsRelationManager::class, [
        'ownerRecord' => $achievement,
        'pageClass' => EditAchievement::class,
    ])->callTableAction('restore', $log);

    expect($achievement->fresh()->value)->toBe('400');
});

it('lets the Marketing role manage achievements, matching its other content permissions', function () {
    $this->seed(RoleSeeder::class);
    $marketing = User::factory()->create(['is_admin' => true]);
    $marketing->syncRoles(['Marketing']);

    $this->actingAs($marketing)->get('/admin/achievements')->assertOk();
});

it('blocks a panel user with no role from managing achievements', function () {
    $this->seed(RoleSeeder::class);
    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles([]);

    $this->actingAs($user)->get('/admin/achievements')->assertForbidden();
});
