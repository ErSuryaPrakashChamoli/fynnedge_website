<?php

use App\Mail\Newsletter\ConfirmationMail;
use App\Mail\Newsletter\UnsubscribeConfirmationMail;
use App\Mail\Newsletter\WelcomeMail;
use App\Models\Setting;
use App\Modules\Newsletter\Enums\SubscriberStatus;
use App\Modules\Newsletter\Enums\SubscriptionSource;
use App\Modules\Newsletter\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

it('creates a pending subscriber and sends a confirmation email', function () {
    $this->post(route('newsletter.subscribe'), [
        'email' => 'Reader@Example.com',
        'source' => 'blog',
        'source_url' => '/resources/how-to-improve-cibil-score',
    ])->assertRedirect();

    $subscriber = NewsletterSubscriber::query()->sole();

    expect($subscriber->email)->toBe('reader@example.com')
        ->and($subscriber->status)->toBe(SubscriberStatus::Pending)
        ->and($subscriber->source)->toBe('blog')
        ->and($subscriber->source_url)->toBe('/resources/how-to-improve-cibil-score')
        ->and($subscriber->consent_at)->not->toBeNull()
        ->and($subscriber->consent_ip)->not->toBeNull()
        // The token is never stored in the clear.
        ->and($subscriber->confirmation_token)->not->toBe('')
        ->and(strlen((string) $subscriber->confirmation_token))->toBe(64);

    Mail::assertQueued(ConfirmationMail::class, fn (ConfirmationMail $mail) => $mail->hasTo('reader@example.com'));
    Mail::assertNotQueued(WelcomeMail::class);
});

it('rejects an invalid email address', function () {
    $this->post(route('newsletter.subscribe'), ['email' => 'not-an-email'])
        ->assertSessionHasErrors('email');

    expect(NewsletterSubscriber::query()->count())->toBe(0);
});

it('never creates a second row for the same address, whatever the casing', function () {
    $this->post(route('newsletter.subscribe'), ['email' => 'reader@example.com']);
    $this->post(route('newsletter.subscribe'), ['email' => 'READER@example.com']);

    expect(NewsletterSubscriber::query()->count())->toBe(1);
});

it('does not re-mail an already active subscriber, so the form cannot be used to spam them', function () {
    NewsletterSubscriber::factory()->active()->create(['email' => 'reader@example.com']);

    $this->post(route('newsletter.subscribe'), ['email' => 'reader@example.com'])->assertRedirect();

    Mail::assertNothingQueued();
    expect(NewsletterSubscriber::query()->sole()->status)->toBe(SubscriberStatus::Active);
});

it('gives the same neutral response whether or not the address is already subscribed', function () {
    NewsletterSubscriber::factory()->active()->create(['email' => 'known@example.com']);

    $known = $this->post(route('newsletter.subscribe'), ['email' => 'known@example.com']);
    $knownMessage = session('newsletterStatus');

    $unknown = $this->post(route('newsletter.subscribe'), ['email' => 'new@example.com']);
    $unknownMessage = session('newsletterStatus');

    // Identical status code AND identical wording: anything else lets a visitor
    // probe whether a given address is on the list.
    expect($known->getStatusCode())->toBe($unknown->getStatusCode())
        ->and($knownMessage)->not->toBeNull()
        ->and($knownMessage)->toBe($unknownMessage);
});

it('revives an unsubscribed address as pending rather than silently re-activating it', function () {
    NewsletterSubscriber::factory()->unsubscribed()->create(['email' => 'reader@example.com']);

    $this->post(route('newsletter.subscribe'), ['email' => 'reader@example.com']);

    $subscriber = NewsletterSubscriber::query()->sole();

    expect($subscriber->status)->toBe(SubscriberStatus::Pending)
        ->and($subscriber->unsubscribed_at)->toBeNull();

    Mail::assertQueued(ConfirmationMail::class);
});

it('keeps the original source when the same person subscribes again from another page', function () {
    $this->post(route('newsletter.subscribe'), ['email' => 'reader@example.com', 'source' => 'blog', 'source_url' => '/resources/first']);
    $this->post(route('newsletter.subscribe'), ['email' => 'reader@example.com', 'source' => 'footer', 'source_url' => '/contact']);

    expect(NewsletterSubscriber::query()->sole()->source)->toBe('blog');
});

it('falls back to a known source rather than trusting whatever the form posted', function () {
    $this->post(route('newsletter.subscribe'), ['email' => 'reader@example.com', 'source' => 'injected-value']);

    expect(NewsletterSubscriber::query()->sole()->source)->toBe(SubscriptionSource::Website->value);
});

it('stores only the path of a source URL, never an attacker-supplied absolute URL', function () {
    $this->post(route('newsletter.subscribe'), [
        'email' => 'reader@example.com',
        'source_url' => 'https://evil.test/phishing',
    ]);

    expect(NewsletterSubscriber::query()->sole()->source_url)->toBe('/phishing');
});

it('silently drops a submission that fills the honeypot field', function () {
    $this->post(route('newsletter.subscribe'), ['email' => 'bot@example.com', 'website' => 'http://spam.test'])
        ->assertSessionHasErrors('website');

    expect(NewsletterSubscriber::query()->count())->toBe(0);
});

it('subscribes immediately without confirmation when double opt-in is switched off', function () {
    Setting::set('double_opt_in_enabled', false);

    $this->post(route('newsletter.subscribe'), ['email' => 'reader@example.com']);

    expect(NewsletterSubscriber::query()->sole()->status)->toBe(SubscriberStatus::Active);

    Mail::assertQueued(WelcomeMail::class);
    Mail::assertNotQueued(ConfirmationMail::class);
});

it('404s the subscribe endpoint entirely when the newsletter is switched off', function () {
    Setting::set('newsletter_enabled', false);

    $this->post(route('newsletter.subscribe'), ['email' => 'reader@example.com'])->assertNotFound();
});

it('confirms a pending subscriber and sends the welcome email', function () {
    NewsletterSubscriber::factory()->pending('valid-token')->create(['email' => 'reader@example.com']);

    $this->get(route('newsletter.confirm', ['token' => 'valid-token']))
        ->assertOk()
        ->assertSee('You are subscribed');

    $subscriber = NewsletterSubscriber::query()->sole();

    expect($subscriber->status)->toBe(SubscriberStatus::Active)
        ->and($subscriber->confirmed_at)->not->toBeNull()
        // Single-use: the token is consumed, and an unsubscribe token now exists.
        ->and($subscriber->confirmation_token)->toBeNull()
        ->and($subscriber->unsubscribe_token)->not->toBeNull();

    Mail::assertQueued(WelcomeMail::class);
});

it('rejects an invalid confirmation token', function () {
    NewsletterSubscriber::factory()->pending('valid-token')->create();

    $this->get(route('newsletter.confirm', ['token' => 'wrong-token']))
        ->assertOk()
        ->assertSee('This link is not valid');

    expect(NewsletterSubscriber::query()->sole()->status)->toBe(SubscriberStatus::Pending);
    Mail::assertNothingQueued();
});

it('refuses an expired confirmation token', function () {
    $subscriber = NewsletterSubscriber::factory()->pending('valid-token')->create();
    $subscriber->forceFill(['confirmation_sent_at' => now()->subDays(NewsletterSubscriber::CONFIRMATION_TTL_DAYS + 1)])->save();

    $this->get(route('newsletter.confirm', ['token' => 'valid-token']))
        ->assertOk()
        ->assertSee('This link has expired');

    expect(NewsletterSubscriber::query()->sole()->status)->toBe(SubscriberStatus::Pending);
});

it('tells an already-confirmed visitor there is nothing to do', function () {
    NewsletterSubscriber::factory()->active()->create(['confirmation_token' => hash('sha256', 'valid-token')]);

    $this->get(route('newsletter.confirm', ['token' => 'valid-token']))
        ->assertOk()
        ->assertSee('Already confirmed');

    Mail::assertNothingQueued();
});

it('unsubscribes without deleting the record, and sends a receipt', function () {
    NewsletterSubscriber::factory()->active('unsub-token')->create(['email' => 'reader@example.com']);

    $this->get(route('newsletter.unsubscribe', ['token' => 'unsub-token']))
        ->assertOk()
        ->assertSee('You have been unsubscribed');

    $subscriber = NewsletterSubscriber::query()->sole();

    expect($subscriber->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and($subscriber->unsubscribed_at)->not->toBeNull()
        ->and($subscriber->exists)->toBeTrue();

    Mail::assertQueued(UnsubscribeConfirmationMail::class);
});

it('keeps a repeat unsubscribe click working but does not re-send the receipt', function () {
    NewsletterSubscriber::factory()->active('unsub-token')->create();

    $this->get(route('newsletter.unsubscribe', ['token' => 'unsub-token']))->assertOk();
    Mail::assertQueuedCount(1);

    $this->get(route('newsletter.unsubscribe', ['token' => 'unsub-token']))
        ->assertOk()
        ->assertSee('You have been unsubscribed');

    Mail::assertQueuedCount(1);
});

it('shows a clear message for an invalid unsubscribe token', function () {
    $this->get(route('newsletter.unsubscribe', ['token' => 'nonsense']))
        ->assertOk()
        ->assertSee('This link is not valid');
});
