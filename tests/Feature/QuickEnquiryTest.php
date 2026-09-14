<?php

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Filament\Resources\ContactEnquiries\Pages\ListContactEnquiries;
use App\Models\ContactEnquiry;
use App\Models\User;
use App\Modules\CreditScore\Models\MobileOtpChallenge;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    // Both limiters here are real cache-backed limiters, not the test-exempt
    // route limiters — clear them between tests so a number reused across
    // examples starts from zero attempts.
    RateLimiter::clear('enquiry:9876543210:general');
    RateLimiter::clear('otp-request:9876543210');
});

/**
 * A challenge already issued for this number, plus the code that verifies it.
 * The lead can only be written once the code checks out, so every submission
 * test carries one.
 *
 * @return array{otp_challenge_id: string, otp_code: string}
 */
function verifiedOtpFor(string $phone = '9876543210'): array
{
    $challenge = MobileOtpChallenge::factory()->create([
        'mobile_number' => $phone,
        'otp_hash' => Hash::make('123456'),
    ]);

    return ['otp_challenge_id' => $challenge->public_id, 'otp_code' => '123456'];
}

it('creates a quick enquiry lead from a valid new number', function () {
    $response = $this->postJson('/quick-enquiry', ['phone' => '9876543210', ...verifiedOtpFor()]);

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
    $this->postJson('/quick-enquiry', ['phone' => '+91 98765-43210', ...verifiedOtpFor()])->assertCreated();

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

    $response = $this->postJson('/quick-enquiry', ['phone' => '9876543210', ...verifiedOtpFor()]);

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

    $this->postJson('/quick-enquiry', ['phone' => '9876543210', ...verifiedOtpFor()])->assertOk();

    expect($existing->fresh())
        ->enquiry_type->toBe(EnquiryType::Contact)
        ->name->toBe('Jordan')
        ->message->toBe('I would like to know more about home loans.');
});

it('reopens a closed enquiry instead of duplicating the lead', function () {
    $existing = ContactEnquiry::factory()->quickEnquiry()->closed()->create(['phone' => '9876543210']);

    $response = $this->postJson('/quick-enquiry', ['phone' => '9876543210', ...verifiedOtpFor()]);

    $response->assertCreated()->assertJsonPath('outcome', 'created');

    expect(ContactEnquiry::query()->count())->toBe(1)
        ->and($existing->fresh())
        ->status->toBe(EnquiryStatus::New)
        ->handled_at->toBeNull()
        ->enquiry_count->toBe(2);
});

it('never returns internal record details to the visitor', function () {
    $this->postJson('/quick-enquiry', ['phone' => '9876543210', ...verifiedOtpFor()])
        ->assertCreated()
        ->assertJsonStructure(['outcome', 'title', 'message'])
        ->assertJsonMissing(['id' => ContactEnquiry::query()->sole()->id])
        ->assertJsonMissingPath('public_id')
        ->assertJsonMissingPath('phone');
});

it('rejects a submission that fills the honeypot field', function () {
    $this->postJson('/quick-enquiry', ['phone' => '9876543210', 'website' => 'https://spam.example', ...verifiedOtpFor()])
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
    // Three submissions per hour per number, enforced inside RecordEnquiry
    // (the per-IP route throttle is uncapped under the test runner).
    foreach (range(1, 3) as $attempt) {
        $this->postJson('/quick-enquiry', ['phone' => '9876543210', ...verifiedOtpFor()])->assertSuccessful();
    }

    $this->postJson('/quick-enquiry', ['phone' => '9876543210', ...verifiedOtpFor()])
        ->assertOk()
        ->assertJsonPath('outcome', 'duplicate');

    expect(ContactEnquiry::query()->sole()->enquiry_count)->toBe(3);
});

it('answers a plain browser post with a redirect and a flash message', function () {
    $this->from('/')
        ->post('/quick-enquiry', ['phone' => '9876543210', ...verifiedOtpFor()])
        ->assertRedirect('/')
        ->assertSessionHas('quickEnquiryTitle', 'Thank You!');

    expect(ContactEnquiry::query()->count())->toBe(1);
});

it('renders the quick enquiry form on the homepage', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Get Started with a Quick Enquiry')
        ->assertSee('Enter your mobile number and our team will get in touch with you.')
        ->assertSee('Send OTP')
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

it('resolves the enquiry source from a fixed list rather than storing what was posted', function () {
    $this->postJson('/quick-enquiry', ['phone' => '9876543210', 'source' => 'homepage', ...verifiedOtpFor()])->assertCreated();

    expect(ContactEnquiry::query()->sole())
        ->enquiry_source->toBe('Homepage Quick Enquiry')
        ->source->toBe('website');
});

it('falls back to the default placement when an unknown source is posted', function () {
    $this->postJson('/quick-enquiry', ['phone' => '9876543210', 'source' => 'Paid Campaign XYZ', ...verifiedOtpFor()])->assertCreated();

    expect(ContactEnquiry::query()->sole())
        ->enquiry_source->toBe('Website Quick Enquiry')
        ->source->toBe('website');
});

it('issues an otp challenge without creating a lead', function () {
    $response = $this->postJson('/quick-enquiry/otp', ['phone' => '9876543210']);

    $response->assertCreated()->assertJsonStructure(['otp_challenge_id', 'demo_otp_code']);

    expect(MobileOtpChallenge::query()->where('mobile_number', '9876543210')->exists())->toBeTrue()
        // An unverified number must never reach the marketing table — that is the
        // whole point of putting the challenge in front of it.
        ->and(ContactEnquiry::query()->count())->toBe(0);
});

it('normalises the number before issuing a challenge for it', function () {
    $this->postJson('/quick-enquiry/otp', ['phone' => '+91 98765-43210'])->assertCreated();

    expect(MobileOtpChallenge::query()->sole()->mobile_number)->toBe('9876543210');
});

it('refuses to issue a challenge for a malformed number', function () {
    $this->postJson('/quick-enquiry/otp', ['phone' => '12345'])
        ->assertStatus(422)
        ->assertJsonPath('errors.phone.0', 'Please enter a valid 10-digit mobile number.');

    expect(MobileOtpChallenge::query()->count())->toBe(0);
});

it('reports the per-number otp request limit against the field the form shows', function () {
    // RequestMobileOtp raises its limit against `mobileNumber`; this form has no
    // such field, so an error left under that key would never be seen.
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/quick-enquiry/otp', ['phone' => '9876543210'])->assertCreated();
    }

    $this->postJson('/quick-enquiry/otp', ['phone' => '9876543210'])
        ->assertStatus(422)
        ->assertJsonPath('errors.phone.0', 'Too many OTP requests for this number. Please try again in a while.');
});

it('stamps the lead as phone verified', function () {
    $this->postJson('/quick-enquiry', ['phone' => '9876543210', ...verifiedOtpFor()])->assertCreated();

    expect(ContactEnquiry::query()->sole()->phone_verified_at)->not->toBeNull();
});

it('records nothing when the submitted code is wrong', function () {
    $challenge = MobileOtpChallenge::factory()->create([
        'mobile_number' => '9876543210',
        'otp_hash' => Hash::make('123456'),
    ]);

    $this->postJson('/quick-enquiry', [
        'phone' => '9876543210',
        'otp_challenge_id' => $challenge->public_id,
        'otp_code' => '000000',
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.otp_code.0', 'That code is incorrect or has expired. You can request a new one.');

    expect(ContactEnquiry::query()->count())->toBe(0);
});

it('records nothing when no code is submitted at all', function () {
    $this->postJson('/quick-enquiry', ['phone' => '9876543210'])->assertStatus(422);

    expect(ContactEnquiry::query()->count())->toBe(0);
});

it('records nothing when the challenge has expired', function () {
    $challenge = MobileOtpChallenge::factory()->expired()->create([
        'mobile_number' => '9876543210',
        'otp_hash' => Hash::make('123456'),
    ]);

    $this->postJson('/quick-enquiry', [
        'phone' => '9876543210',
        'otp_challenge_id' => $challenge->public_id,
        'otp_code' => '123456',
    ])->assertStatus(422);

    expect(ContactEnquiry::query()->count())->toBe(0);
});

it('will not let a challenge verified for one number wave through another', function () {
    // Otherwise the OTP proves only that the submitter owns *some* number, and
    // the lead the team calls back is still unverified.
    $challenge = MobileOtpChallenge::factory()->create([
        'mobile_number' => '9876543210',
        'otp_hash' => Hash::make('123456'),
    ]);

    $this->postJson('/quick-enquiry', [
        'phone' => '9123456780',
        'otp_challenge_id' => $challenge->public_id,
        'otp_code' => '123456',
    ])->assertStatus(422);

    expect(ContactEnquiry::query()->count())->toBe(0);
});

it('will not let one verified challenge be replayed for a second lead', function () {
    $otp = verifiedOtpFor();

    $this->postJson('/quick-enquiry', ['phone' => '9876543210', ...$otp])->assertCreated();

    ContactEnquiry::query()->delete();

    $this->postJson('/quick-enquiry', ['phone' => '9876543210', ...$otp])->assertStatus(422);

    expect(ContactEnquiry::query()->count())->toBe(0);
});

it('walks a javascript-free visitor through both steps with flashed state', function () {
    $this->from('/')
        ->post('/quick-enquiry/otp', ['phone' => '9876543210'])
        ->assertRedirect('/')
        ->assertSessionHas('quickEnquiryChallenge')
        ->assertSessionHas('quickEnquiryDemoOtp');

    $challenge = MobileOtpChallenge::query()->sole();

    expect(session('quickEnquiryChallenge'))->toBe($challenge->public_id)
        ->and(ContactEnquiry::query()->count())->toBe(0);

    $this->from('/')
        ->post('/quick-enquiry', [
            'phone' => '9876543210',
            'otp_challenge_id' => $challenge->public_id,
            'otp_code' => session('quickEnquiryDemoOtp'),
        ])
        ->assertRedirect('/')
        ->assertSessionHas('quickEnquiryTitle', 'Thank You!');

    expect(ContactEnquiry::query()->sole()->phone_verified_at)->not->toBeNull();
});

it('keeps a javascript-free visitor on the otp step after a wrong code', function () {
    $challenge = MobileOtpChallenge::factory()->create([
        'mobile_number' => '9876543210',
        'otp_hash' => Hash::make('123456'),
    ]);

    // The challenge id comes back as old input, which is what the Blade reads to
    // re-render step two instead of sending them back to retype their number.
    $this->from('/')
        ->post('/quick-enquiry', [
            'phone' => '9876543210',
            'otp_challenge_id' => $challenge->public_id,
            'otp_code' => '000000',
        ])
        ->assertRedirect('/')
        ->assertSessionHasErrors('otp_code')
        ->assertSessionHasInput('otp_challenge_id', $challenge->public_id);
});

it('throttles the otp endpoint on the same per-ip limiter as the submission', function () {
    expect(collect(app('router')->getRoutes()->getByName('quick-enquiry.otp')->gatherMiddleware()))
        ->toContain('web')
        ->toContain('throttle:enquiry-forms');
});
