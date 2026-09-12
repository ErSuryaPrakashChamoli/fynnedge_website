<?php

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Filament\Resources\ContactEnquiries\Pages\ListContactEnquiries;
use App\Models\ContactEnquiry;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    // The per-number limiter inside SubmitQuickEnquiry is a real cache-backed
    // limiter, not one of the test-exempt route limiters — clear it between tests
    // so a number reused across examples starts from zero attempts.
    RateLimiter::clear('quick-enquiry:9876543210');
});

it('creates a quick enquiry lead from a valid new number', function () {
    $response = $this->postJson('/quick-enquiry', ['phone' => '9876543210']);

    $response->assertCreated()
        ->assertJsonPath('outcome', 'created')
        ->assertJsonPath('title', 'Thank You!');

    $enquiry = ContactEnquiry::query()->sole();

    expect($enquiry->phone)->toBe('9876543210')
        ->and($enquiry->enquiry_type)->toBe(EnquiryType::QuickEnquiry)
        ->and($enquiry->source)->toBe('website')
        ->and($enquiry->status)->toBe(EnquiryStatus::New)
        ->and($enquiry->enquiry_count)->toBe(1)
        ->and($enquiry->name)->toBeNull();
});

it('normalises a number typed with a country code and separators', function () {
    $this->postJson('/quick-enquiry', ['phone' => '+91 98765-43210'])->assertCreated();

    expect(ContactEnquiry::query()->sole()->phone)->toBe('9876543210');
});

it('rejects a number that is not a valid indian mobile number', function (string $phone) {
    $this->postJson('/quick-enquiry', ['phone' => $phone])
        ->assertStatus(422)
        ->assertJsonPath('errors.phone.0', 'Please enter a valid 10-digit mobile number.');

    expect(ContactEnquiry::query()->count())->toBe(0);
})->with([
    'too short' => '98765432',
    'too long' => '98765432100',
    'invalid leading digit' => '1234567890',
    'letters' => 'abcdefghij',
    'empty' => '',
]);

it('does not create a second record when an open enquiry already exists for the number', function () {
    $existing = ContactEnquiry::factory()->quickEnquiry()->create(['phone' => '9876543210']);

    $response = $this->postJson('/quick-enquiry', ['phone' => '9876543210']);

    $response->assertOk()
        ->assertJsonPath('outcome', 'duplicate')
        ->assertJsonPath('title', "You're Already Connected");

    expect(ContactEnquiry::query()->count())->toBe(1)
        ->and($existing->fresh()->enquiry_count)->toBe(2);
});

it('keeps a contact form record intact when the same number sends a quick enquiry', function () {
    $existing = ContactEnquiry::factory()->create([
        'phone' => '9876543210',
        'name' => 'Jordan',
        'message' => 'I would like to know more about home loans.',
    ]);

    $this->postJson('/quick-enquiry', ['phone' => '9876543210'])->assertOk();

    expect($existing->fresh())
        ->enquiry_type->toBe(EnquiryType::Contact)
        ->name->toBe('Jordan')
        ->message->toBe('I would like to know more about home loans.');
});

it('reopens a closed enquiry instead of duplicating the lead', function () {
    $existing = ContactEnquiry::factory()->quickEnquiry()->closed()->create(['phone' => '9876543210']);

    $response = $this->postJson('/quick-enquiry', ['phone' => '9876543210']);

    $response->assertCreated()->assertJsonPath('outcome', 'created');

    expect(ContactEnquiry::query()->count())->toBe(1)
        ->and($existing->fresh())
        ->status->toBe(EnquiryStatus::New)
        ->handled_at->toBeNull()
        ->enquiry_count->toBe(2);
});

it('never returns internal record details to the visitor', function () {
    $this->postJson('/quick-enquiry', ['phone' => '9876543210'])
        ->assertCreated()
        ->assertJsonStructure(['outcome', 'title', 'message'])
        ->assertJsonMissing(['id' => ContactEnquiry::query()->sole()->id])
        ->assertJsonMissingPath('public_id')
        ->assertJsonMissingPath('phone');
});

it('rejects a submission that fills the honeypot field', function () {
    $this->postJson('/quick-enquiry', ['phone' => '9876543210', 'website' => 'https://spam.example'])
        ->assertStatus(422);

    expect(ContactEnquiry::query()->count())->toBe(0);
});

it('requires a csrf token for a plain browser post', function () {
    // postJson above runs without CSRF because the testing kernel disables the
    // middleware; this asserts the route really is inside the web group, where
    // VerifyCsrfToken applies, rather than an unprotected API-style endpoint.
    expect(collect(app('router')->getRoutes()->getByName('quick-enquiry.store')->gatherMiddleware()))
        ->toContain('web');
});

it('stops a number being submitted repeatedly once the per-number limit is reached', function () {
    // Three submissions per hour per number, enforced inside SubmitQuickEnquiry
    // (the per-IP route throttle is uncapped under the test runner).
    foreach (range(1, 3) as $attempt) {
        $this->postJson('/quick-enquiry', ['phone' => '9876543210'])->assertSuccessful();
    }

    $this->postJson('/quick-enquiry', ['phone' => '9876543210'])
        ->assertOk()
        ->assertJsonPath('outcome', 'duplicate');

    expect(ContactEnquiry::query()->sole()->enquiry_count)->toBe(3);
});

it('answers a plain browser post with a redirect and a flash message', function () {
    $this->from('/')
        ->post('/quick-enquiry', ['phone' => '9876543210'])
        ->assertRedirect('/')
        ->assertSessionHas('quickEnquiryTitle', 'Thank You!');

    expect(ContactEnquiry::query()->count())->toBe(1);
});

it('renders the quick enquiry form on the homepage', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Get Started with a Quick Enquiry')
        ->assertSee('Enter your mobile number and our team will get in touch with you.')
        ->assertSee('quickEnquiryForm(', false);
});

it('stamps handled_at when an enquiry is closed', function () {
    $enquiry = ContactEnquiry::factory()->quickEnquiry()->create();

    $enquiry->update(['status' => EnquiryStatus::Closed]);

    expect($enquiry->fresh()->handled_at)->not->toBeNull();
});

it('lists a quick enquiry in the admin enquiries table with its type, source and status', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $quickEnquiry = ContactEnquiry::factory()->quickEnquiry()->create(['phone' => '9876543210']);
    $contactEnquiry = ContactEnquiry::factory()->create();

    Livewire::actingAs($admin)
        ->test(ListContactEnquiries::class)
        ->assertCanSeeTableRecords([$quickEnquiry, $contactEnquiry])
        ->assertTableColumnStateSet('enquiry_type', EnquiryType::QuickEnquiry, $quickEnquiry)
        ->assertTableColumnStateSet('status', EnquiryStatus::New, $quickEnquiry)
        ->assertTableColumnStateSet('source', 'website', $quickEnquiry)
        ->filterTable('enquiry_type', EnquiryType::QuickEnquiry->value)
        ->assertCanSeeTableRecords([$quickEnquiry])
        ->assertCanNotSeeTableRecords([$contactEnquiry]);
});
