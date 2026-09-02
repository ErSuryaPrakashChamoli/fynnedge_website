<?php

use App\Filament\Resources\NavigationLinks\Pages\CreateNavigationLink;
use App\Filament\Resources\NavigationLinks\Pages\ListNavigationLinks;
use App\Models\NavigationLink;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('lists navigation links', function () {
    NavigationLink::factory()->count(2)->create();

    Livewire::test(ListNavigationLinks::class)->assertSuccessful();
});

it('creates a navigation link to an internal route', function () {
    Livewire::test(CreateNavigationLink::class)
        ->fillForm([
            'label' => 'Careers',
            'route_name' => 'careers',
            'location' => 'footer',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(NavigationLink::query()->where('label', 'Careers')->exists())->toBeTrue();
});

it('rejects a link with neither a route name nor a URL', function () {
    Livewire::test(CreateNavigationLink::class)
        ->fillForm(['label' => 'Broken link', 'location' => 'footer'])
        ->call('create')
        ->assertHasFormErrors(['route_name', 'url']);
});

it('rejects an unsafe URL scheme', function () {
    Livewire::test(CreateNavigationLink::class)
        ->fillForm(['label' => 'Bad link', 'url' => 'javascript:alert(1)', 'location' => 'footer'])
        ->call('create')
        ->assertHasFormErrors(['url']);
});

it('resolves an internal route name to a real URL and drops a renamed/removed one safely', function () {
    $valid = NavigationLink::factory()->create(['route_name' => 'careers', 'url' => null]);
    $broken = NavigationLink::factory()->create(['route_name' => 'no-such-route', 'url' => null]);

    expect($valid->resolvedUrl())->toBe(route('careers'));
    expect($broken->resolvedUrl())->toBeNull();
});

it('shows an active footer navigation link on the public site, alongside the existing footer content', function () {
    NavigationLink::factory()->create([
        'label' => 'Our Careers Page',
        'route_name' => 'careers',
        'location' => 'footer',
        'is_active' => true,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Our Careers Page')
        ->assertSee('Quick Links')
        ->assertSee('Privacy Policy');
});

it('hides an inactive navigation link from the public site', function () {
    NavigationLink::factory()->create([
        'label' => 'Hidden Link',
        'is_active' => false,
    ]);

    $this->get('/')->assertOk()->assertDontSee('Hidden Link');
});

it('does not render a Quick Links column at all when no navigation links exist', function () {
    $this->get('/')->assertOk()->assertDontSee('Quick Links');
});
