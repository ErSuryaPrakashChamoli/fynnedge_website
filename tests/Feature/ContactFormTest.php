<?php

use App\Models\ContactEnquiry;
use App\Models\Setting;
use App\Modules\MobileOtp\Enums\MobileOtpPurpose;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use Illuminate\Support\Js;

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
    $challenge = MobileOtpChallenge::factory()->verified()->forPurpose(MobileOtpPurpose::ContactEnquiry)->create(['mobile_number' => '9876543210']);

    $response = $this->from('/contact')->post('/contact', [
        'name' => 'Jordan',
        'email' => 'jordan@example.com',
        'phone' => '9876543210',
        'otp_challenge_id' => $challenge->public_id,
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

it('takes an enquiry without a phone and without any code', function () {
    $this->from('/contact')->post('/contact', [
        'name' => 'Jordan',
        'email' => 'jordan@example.com',
        'phone' => '',
        'message' => 'Please email me about home loans.',
    ])->assertRedirect('/contact')->assertSessionHasNoErrors();

    expect(ContactEnquiry::query()->sole())
        ->phone->toBeNull()
        ->phone_verified_at->toBeNull();
});

it('refuses a phone number that was not verified', function (?Closure $makeChallenge) {
    $challenge = $makeChallenge?->call($this);

    $this->from('/contact')->post('/contact', [
        'name' => 'Jordan',
        'email' => 'jordan@example.com',
        'phone' => '9876543210',
        'otp_challenge_id' => $challenge?->public_id,
        'message' => 'Call me back.',
    ])->assertSessionHasErrors(['phone' => 'Please verify this number with the code we text you, or leave the phone blank.']);

    expect(ContactEnquiry::query()->count())->toBe(0);
})->with([
    'no challenge at all' => [null],
    'code sent but never entered' => [fn () => MobileOtpChallenge::factory()->forPurpose(MobileOtpPurpose::ContactEnquiry)->create(['mobile_number' => '9876543210'])],
    'verified for another number' => [fn () => MobileOtpChallenge::factory()->verified()->forPurpose(MobileOtpPurpose::ContactEnquiry)->create(['mobile_number' => '9123456780'])],
    'verified for another form' => [fn () => MobileOtpChallenge::factory()->verified()->forPurpose(MobileOtpPurpose::LoanEnquiry)->create(['mobile_number' => '9876543210'])],
]);

it('stores the verified number normalised and marked as verified', function () {
    $challenge = MobileOtpChallenge::factory()->verified()->forPurpose(MobileOtpPurpose::ContactEnquiry)->create(['mobile_number' => '9876543210']);

    $this->from('/contact')->post('/contact', [
        'name' => 'Jordan',
        'email' => 'jordan@example.com',
        'phone' => '+91 98765-43210',
        'otp_challenge_id' => $challenge->public_id,
        'message' => 'Call me back.',
    ])->assertSessionHasNoErrors();

    expect(ContactEnquiry::query()->sole())
        ->phone->toBe('9876543210')
        ->phone_verified_at->not->toBeNull();
    expect($challenge->fresh()->consumed_at)->not->toBeNull();
});

it('spends one verification on one enquiry only', function () {
    $challenge = MobileOtpChallenge::factory()->verified()->forPurpose(MobileOtpPurpose::ContactEnquiry)->create(['mobile_number' => '9876543210']);
    $payload = [
        'name' => 'Jordan',
        'email' => 'jordan@example.com',
        'phone' => '9876543210',
        'otp_challenge_id' => $challenge->public_id,
        'message' => 'Call me back.',
    ];
    $this->from('/contact')->post('/contact', $payload)->assertSessionHasNoErrors();

    $this->from('/contact')->post('/contact', $payload)->assertSessionHasErrors('phone');

    expect(ContactEnquiry::query()->count())->toBe(1);
});

it('rejects a phone that is not an indian mobile number', function () {
    $this->from('/contact')->post('/contact', [
        'name' => 'Jordan',
        'email' => 'jordan@example.com',
        'phone' => '0120-123456',
        'message' => 'Call me back.',
    ])->assertSessionHasErrors(['phone' => 'Please enter a valid 10-digit mobile number, or leave the phone blank.']);
});

it('ignores a verified flag posted by the browser', function () {
    $this->from('/contact')->post('/contact', [
        'name' => 'Jordan',
        'email' => 'jordan@example.com',
        'phone' => '9876543210',
        'phone_verified' => true,
        'phone_verified_at' => now()->toDateTimeString(),
        'message' => 'Call me back.',
    ])->assertSessionHasErrors('phone');

    expect(ContactEnquiry::query()->count())->toBe(0);
});

it('verifies a contact phone end to end through the public api', function () {
    fakeAreachSms();
    $challengeId = $this->postJson('/api/v1/mobile-otp/send', ['mobile_number' => '9876543210', 'purpose' => 'contact_enquiry'])
        ->assertCreated()
        ->json('data.challenge_id');
    $this->postJson('/api/v1/mobile-otp/verify', [
        'challenge_id' => $challengeId,
        'mobile_number' => '9876543210',
        'purpose' => 'contact_enquiry',
        'otp' => lastTextedOtp(),
    ])->assertOk();

    $this->from('/contact')->post('/contact', [
        'name' => 'Jordan',
        'email' => 'jordan@example.com',
        'phone' => '9876543210',
        'otp_challenge_id' => $challengeId,
        'message' => 'Call me back.',
    ])->assertSessionHasNoErrors();

    expect(ContactEnquiry::query()->sole()->phone_verified_at)->not->toBeNull();
});

it('renders the phone verification on the contact page', function () {
    $this->get('/contact')
        ->assertSee('contactPhoneOtp', false)
        ->assertSee(Js::from(route('api.v1.mobile-otp.send'))->toHtml(), false)
        ->assertSee('name="otp_challenge_id"', false);
});
