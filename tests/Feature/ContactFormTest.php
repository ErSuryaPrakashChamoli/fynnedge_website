<?php

use App\Models\ContactEnquiry;
use App\Models\Setting;

it('shows the admin-configured map embed on the contact page', function () {
    Setting::set('contact_map_url', 'https://www.google.com/maps/embed?pb=abc123');

    $this->get('/contact')
        ->assertOk()
        ->assertSee('https://www.google.com/maps/embed?pb=abc123', false);
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
