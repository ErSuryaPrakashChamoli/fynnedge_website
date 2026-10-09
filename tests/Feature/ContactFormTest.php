<?php

use App\Models\ContactEnquiry;
use App\Models\Setting;

it('shows the admin-configured map embed on the contact page', function () {
    Setting::set('contact_map_url', 'https://www.google.com/maps/embed?pb=abc123');

    $this->get('/contact')
        ->assertOk()
        ->assertSee('https://www.google.com/maps/embed?pb=abc123', false);
});

it('positions the map pin label with one transform so it stays centred over the pin', function () {
    Setting::set('contact_map_url', 'https://maps.google.com/maps?q=28.5854%2C77.3130&z=17&output=embed');

    $html = $this->get('/contact')->getContent();

    preg_match('/class="([^"]*)"\s+style="transform: translate\(-50%, [^"]*\)"/', $html, $labelOverlay);

    expect($labelOverlay[1] ?? null)->toBeString()->not->toContain('translate-');
});

it('leaves a Google listing map to name its own pin', function (string $mapUrl) {
    Setting::set('contact_map_url', $mapUrl);

    $this->get('/contact')
        ->assertSee('<iframe', false)
        ->assertDontSee('style="transform: translate(-50%', false)
        ->assertDontSee('location in Google Maps');
})->with([
    'embed code' => 'https://www.google.com/maps/embed?pb=abc123',
    'place search' => 'https://maps.google.com/maps?q=Fynnedge+Advisory+Pvt.+Ltd.&ll=28.585606%2C77.31294&z=17&output=embed',
]);

it('hides a saved map link that Google refuses to show inside the site', function () {
    Setting::set('contact_map_url', 'https://maps.app.goo.gl/nMFxPSk98pK1nzDr9?output=embed');

    $this->get('/contact')->assertDontSee('<iframe', false);
});

it('omits the map embed when no map url is configured', function () {
    Setting::set('contact_map_url', '');

    $this->get('/contact')->assertOk()->assertDontSee('<iframe', false);
});

it('stores a valid enquiry and redirects back with a status message', function () {
    $response = $this->from('/contact')->post('/contact', [
        'name' => 'Jordan',
        'email' => 'jordan@example.com',
        'phone' => '9876543210',
        'message' => 'I would like to know more about home loans.',
    ]);

    $response->assertRedirect('/contact');
    $response->assertSessionHas('statusTitle', 'Your Loan Query Has Been Submitted Successfully! 🎉');
    $response->assertSessionHas('status');

    expect(ContactEnquiry::query()->where('email', 'jordan@example.com')->exists())->toBeTrue();
});

it('rejects an enquiry missing required fields', function () {
    $response = $this->from('/contact')->post('/contact', [
        'name' => '',
        'email' => 'not-an-email',
        'message' => '',
    ]);

    $response->assertSessionHasErrors(['name', 'email', 'message']);
    expect(ContactEnquiry::query()->count())->toBe(0);
});
