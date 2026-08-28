<?php

use App\Models\ContactEnquiry;

it('stores a valid enquiry and redirects back with a status message', function () {
    $response = $this->from('/contact')->post('/contact', [
        'name' => 'Jordan',
        'email' => 'jordan@example.com',
        'phone' => '9876543210',
        'message' => 'I would like to know more about home loans.',
    ]);

    $response->assertRedirect('/contact');
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
