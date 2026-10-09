<?php

use App\Models\Setting;

it('shows the office map in the footer with directions to the pinned location', function () {
    Setting::set('contact_map_url', 'https://maps.google.com/maps?q=28.5854%2C77.3130&z=17&output=embed');
    Setting::set('contact_address', 'A-70, Sector 2, Noida 201301');

    $response = $this->get('/');

    $response
        ->assertSee('Visit our office')
        ->assertSee('A-70, Sector 2, Noida 201301')
        ->assertSee('src="https://maps.google.com/maps?q=28.5854%2C77.3130&amp;z=17&amp;output=embed"', false)
        ->assertSee('href="https://www.google.com/maps/dir/?api=1&amp;destination=28.5854%2C77.3130"', false)
        ->assertSee('href="https://maps.google.com/maps?q=28.5854%2C77.3130&amp;z=17"', false);
});

it('falls back to the contact address for directions when the map link has no location of its own', function () {
    Setting::set('contact_map_url', 'https://www.google.com/maps/embed?pb=abc123');
    Setting::set('contact_address', 'A-70, Sector 2, Noida');

    $response = $this->get('/');

    $response->assertSee('href="https://www.google.com/maps/dir/?api=1&amp;destination=A-70%2C%20Sector%202%2C%20Noida"', false);
});

it('hides the office map once an admin switches it off', function () {
    Setting::set('contact_map_url', 'https://www.google.com/maps/embed?pb=abc123');
    Setting::set('footer_map_enabled', false);

    $response = $this->get('/');

    $response
        ->assertDontSee('Visit our office')
        ->assertDontSee('https://www.google.com/maps/embed?pb=abc123', false);
});

it('leaves the office map out when no map URL is configured', function () {
    Setting::set('contact_map_url', '');

    $response = $this->get('/');

    $response->assertDontSee('Visit our office');
});

it('does not repeat the map in the footer on the contact page', function () {
    Setting::set('contact_map_url', 'https://www.google.com/maps/embed?pb=abc123');

    $html = $this->get('/contact')->assertDontSee('Visit our office')->getContent();

    expect(substr_count($html, 'https://www.google.com/maps/embed?pb=abc123'))->toBe(1);
});
