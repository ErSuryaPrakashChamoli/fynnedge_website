<?php

use App\Filament\Pages\Settings;
use App\Models\Setting;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->admin);
});

it('renders the settings page', function () {
    $this->get('/admin/settings')->assertOk();
});

it('lets an admin save contact channel settings', function () {
    Livewire::test(Settings::class)
        ->fillForm([
            'contact_phone' => '+91 90000 00000',
            'contact_email' => 'hello@fynnedge.com',
            'contact_whatsapp' => '+91 90000 00001',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('contact_phone'))->toBe('+91 90000 00000');
    expect(Setting::get('contact_email'))->toBe('hello@fynnedge.com');
    expect(Setting::get('contact_whatsapp'))->toBe('+91 90000 00001');
});
