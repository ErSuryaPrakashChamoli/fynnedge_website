<?php

use App\Mail\Newsletter\CampaignMail;
use App\Mail\Newsletter\ConfirmationMail;
use App\Mail\Newsletter\UnsubscribeConfirmationMail;
use App\Mail\Newsletter\WelcomeMail;
use App\Models\Setting;
use App\Modules\Newsletter\Enums\RecipientStatus;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Models\NewsletterCampaignRecipient;
use App\Modules\Newsletter\Models\NewsletterSubscriber;

/**
 * Mail::fake() records that a mailable was queued but never RENDERS it, so a
 * broken Blade template passes every other test in this suite and only fails
 * in production, at the moment the queue worker tries to send. These tests
 * render each email for real.
 *
 * That is not hypothetical: it caught an @else written immediately after a word
 * character in the welcome template, which Blade silently declines to compile.
 */
function renderableCampaignRecipient(): NewsletterCampaignRecipient
{
    return NewsletterCampaignRecipient::query()->create([
        'newsletter_campaign_id' => NewsletterCampaign::factory()->create([
            'content' => '<h2>Heading</h2><p>Body copy.</p>',
            'cta_label' => 'Read the article',
            'cta_url' => 'https://fynnedge.com/resources/example',
        ])->getKey(),
        'newsletter_subscriber_id' => NewsletterSubscriber::factory()->active()->create()->getKey(),
        'status' => RecipientStatus::Pending,
        'tracking_token' => hash('sha256', 'track-me'),
    ]);
}

it('renders every newsletter email without a template error', function (string $mailable) {
    $subscriber = NewsletterSubscriber::factory()->active()->create(['name' => 'Reader']);

    $html = match ($mailable) {
        ConfirmationMail::class => (new ConfirmationMail($subscriber, 'plain-token'))->render(),
        WelcomeMail::class => (new WelcomeMail($subscriber, 'unsub-token'))->render(),
        UnsubscribeConfirmationMail::class => (new UnsubscribeConfirmationMail($subscriber))->render(),
        CampaignMail::class => (new CampaignMail(renderableCampaignRecipient(), 'track-me', 'unsub-token'))->render(),
    };

    expect($html)
        ->toContain('<html')
        // No unrendered Blade left behind — the signature of a directive that
        // did not compile.
        ->not->toContain('{{')
        ->not->toContain('@if')
        ->not->toContain('@endif')
        // Email clients run no JavaScript; anything relying on it is broken mail.
        ->not->toContain('<script');
})->with([
    ConfirmationMail::class,
    WelcomeMail::class,
    UnsubscribeConfirmationMail::class,
    CampaignMail::class,
]);

it('renders a subscriber with no name without leaving a gap in the greeting', function () {
    $subscriber = NewsletterSubscriber::factory()->active()->create(['name' => null]);

    // The apostrophe arrives HTML-escaped, which is correct for an HTML email.
    expect((new WelcomeMail($subscriber, 'unsub-token'))->render())
        ->toContain('all set')
        ->not->toContain('Hi ,')
        ->not->toContain('Hi ,you');
});

it('puts an unsubscribe link in every email that markets to someone', function () {
    $subscriber = NewsletterSubscriber::factory()->active()->create();

    expect((new WelcomeMail($subscriber, 'unsub-token'))->render())
        ->toContain(route('newsletter.unsubscribe', ['token' => 'unsub-token']));

    expect((new CampaignMail(renderableCampaignRecipient(), 'track-me', 'unsub-token'))->render())
        ->toContain(route('newsletter.unsubscribe', ['token' => 'unsub-token']));
});

it('sends from the admin-configured identity, falling back to the app mail config', function () {
    $subscriber = NewsletterSubscriber::factory()->active()->create();

    $envelope = (new WelcomeMail($subscriber))->envelope();
    expect($envelope->from->address)->toBe(config('mail.from.address'));

    Setting::set('newsletter_sender_email', 'insights@fynnedge.com');
    Setting::set('newsletter_sender_name', 'FynnEdge Insights');

    $envelope = (new WelcomeMail($subscriber))->envelope();

    expect($envelope->from->address)->toBe('insights@fynnedge.com')
        ->and($envelope->from->name)->toBe('FynnEdge Insights');
});

it('carries the List-Unsubscribe headers mail clients use for one-click opt-out', function () {
    $headers = (new CampaignMail(renderableCampaignRecipient(), 'track-me', 'unsub-token'))->headers();

    expect($headers->text)->toHaveKey('List-Unsubscribe')
        ->and($headers->text['List-Unsubscribe'])->toContain('/newsletter/unsubscribe/unsub-token')
        ->and($headers->text)->toHaveKey('List-Unsubscribe-Post');
});
