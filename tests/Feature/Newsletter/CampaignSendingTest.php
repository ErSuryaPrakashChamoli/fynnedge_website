<?php

use App\Mail\Newsletter\CampaignMail;
use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Enums\RecipientStatus;
use App\Modules\Newsletter\Enums\SubscriberStatus;
use App\Modules\Newsletter\Enums\SubscriptionSource;
use App\Modules\Newsletter\Jobs\SendNewsletterCampaign;
use App\Modules\Newsletter\Jobs\SendNewsletterEmail;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Models\NewsletterCampaignRecipient;
use App\Modules\Newsletter\Models\NewsletterSegment;
use App\Modules\Newsletter\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

it('queues one email per active subscriber and marks the campaign sent', function () {
    Queue::fake();

    NewsletterSubscriber::factory()->active()->count(3)->create();
    NewsletterSubscriber::factory()->count(2)->create();          // pending
    NewsletterSubscriber::factory()->unsubscribed()->create();     // opted out

    $campaign = NewsletterCampaign::factory()->scheduled()->create();

    (new SendNewsletterCampaign($campaign))->handle();

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Sent)
        ->and($campaign->fresh()->sent_at)->not->toBeNull()
        ->and(NewsletterCampaignRecipient::query()->count())->toBe(3);

    Queue::assertPushed(SendNewsletterEmail::class, 3);
});

it('never mails the same subscriber twice, even if the campaign job runs again', function () {
    Queue::fake();

    NewsletterSubscriber::factory()->active()->count(2)->create();
    $campaign = NewsletterCampaign::factory()->scheduled()->create();

    (new SendNewsletterCampaign($campaign))->handle();
    // A retried or re-dispatched job: the recipient rows already claim everyone.
    $campaign->forceFill(['status' => CampaignStatus::Sending])->save();
    (new SendNewsletterCampaign($campaign))->handle();

    expect(NewsletterCampaignRecipient::query()->count())->toBe(2);
    Queue::assertPushed(SendNewsletterEmail::class, 2);
});

it('does nothing for a cancelled or already sent campaign', function () {
    Queue::fake();

    NewsletterSubscriber::factory()->active()->create();

    foreach ([NewsletterCampaign::factory()->cancelled()->create(), NewsletterCampaign::factory()->sent()->create()] as $campaign) {
        (new SendNewsletterCampaign($campaign))->handle();
    }

    expect(NewsletterCampaignRecipient::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('sends only to the campaign segment', function () {
    Queue::fake();

    NewsletterSubscriber::factory()->active()->fromSource(SubscriptionSource::Blog)->count(2)->create();
    NewsletterSubscriber::factory()->active()->fromSource(SubscriptionSource::Homepage)->count(3)->create();

    $segment = NewsletterSegment::factory()->withCriteria(['sources' => ['blog']])->create();
    $campaign = NewsletterCampaign::factory()->scheduled()->create(['newsletter_segment_id' => $segment->getKey()]);

    (new SendNewsletterCampaign($campaign))->handle();

    expect(NewsletterCampaignRecipient::query()->count())->toBe(2);
});

it('delivers the email and records it as sent', function () {
    Mail::fake();

    $subscriber = NewsletterSubscriber::factory()->active()->create();
    $campaign = NewsletterCampaign::factory()->scheduled()->create();
    $recipient = NewsletterCampaignRecipient::query()->create([
        'newsletter_campaign_id' => $campaign->getKey(),
        'newsletter_subscriber_id' => $subscriber->getKey(),
        'status' => RecipientStatus::Pending,
        'tracking_token' => hash('sha256', 'track-me'),
    ]);

    (new SendNewsletterEmail($recipient, 'track-me'))->handle();

    $recipient->refresh();

    expect($recipient->status)->toBe(RecipientStatus::Sent)
        ->and($recipient->sent_at)->not->toBeNull()
        // Never inferred from a successful hand-off to the transport.
        ->and($recipient->delivered_at)->toBeNull();

    Mail::assertQueued(CampaignMail::class, fn (CampaignMail $mail) => $mail->hasTo($subscriber->email));
});

it('skips a subscriber who unsubscribed while the campaign sat in the queue', function () {
    Mail::fake();

    $subscriber = NewsletterSubscriber::factory()->active()->create();
    $campaign = NewsletterCampaign::factory()->scheduled()->create();
    $recipient = NewsletterCampaignRecipient::query()->create([
        'newsletter_campaign_id' => $campaign->getKey(),
        'newsletter_subscriber_id' => $subscriber->getKey(),
        'status' => RecipientStatus::Pending,
        'tracking_token' => hash('sha256', 'track-me'),
    ]);

    // The window between building the audience and actually sending.
    $subscriber->forceFill(['status' => SubscriberStatus::Unsubscribed])->save();

    (new SendNewsletterEmail($recipient, 'track-me'))->handle();

    expect($recipient->fresh()->status)->toBe(RecipientStatus::Skipped);
    Mail::assertNothingQueued();
});

it('records a failure with its reason instead of failing the whole campaign', function () {
    $subscriber = NewsletterSubscriber::factory()->active()->create();
    $campaign = NewsletterCampaign::factory()->scheduled()->create();
    $recipient = NewsletterCampaignRecipient::query()->create([
        'newsletter_campaign_id' => $campaign->getKey(),
        'newsletter_subscriber_id' => $subscriber->getKey(),
        'status' => RecipientStatus::Pending,
        'tracking_token' => hash('sha256', 'track-me'),
    ]);

    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP connection refused'));

    expect(fn () => (new SendNewsletterEmail($recipient, 'track-me'))->handle())
        ->toThrow(RuntimeException::class);

    $recipient->refresh();

    expect($recipient->status)->toBe(RecipientStatus::Failed)
        ->and($recipient->failed_at)->not->toBeNull()
        ->and($recipient->error_message)->toContain('SMTP connection refused');
});

it('does not re-send an email for a recipient row that is no longer pending', function () {
    Mail::fake();

    $subscriber = NewsletterSubscriber::factory()->active()->create();
    $campaign = NewsletterCampaign::factory()->sent()->create();
    $recipient = NewsletterCampaignRecipient::query()->create([
        'newsletter_campaign_id' => $campaign->getKey(),
        'newsletter_subscriber_id' => $subscriber->getKey(),
        'status' => RecipientStatus::Sent,
        'tracking_token' => hash('sha256', 'track-me'),
        'sent_at' => now(),
    ]);

    (new SendNewsletterEmail($recipient, 'track-me'))->handle();

    Mail::assertNothingQueued();
});

it('renders the campaign email with an unsubscribe link and a tracking pixel', function () {
    $subscriber = NewsletterSubscriber::factory()->active('unsub-token')->create();
    $campaign = NewsletterCampaign::factory()->create([
        'content' => '<p>The body of the newsletter.</p>',
        'cta_label' => 'Read the article',
        'cta_url' => 'https://fynnedge.com/resources/example',
    ]);
    $recipient = NewsletterCampaignRecipient::query()->create([
        'newsletter_campaign_id' => $campaign->getKey(),
        'newsletter_subscriber_id' => $subscriber->getKey(),
        'status' => RecipientStatus::Pending,
        'tracking_token' => hash('sha256', 'track-me'),
    ]);

    $html = (new CampaignMail($recipient, 'track-me', 'unsub-token'))->render();

    expect($html)
        ->toContain('The body of the newsletter.')
        ->toContain(route('newsletter.unsubscribe', ['token' => 'unsub-token']))
        ->toContain(route('newsletter.track.open', ['recipient' => $recipient->getKey(), 'token' => 'track-me']))
        // The CTA points at the tracked redirect, never straight at the destination.
        ->toContain(route('newsletter.track.click', ['recipient' => $recipient->getKey(), 'token' => 'track-me']))
        ->toContain('Read the article')
        // No JavaScript in an email, ever.
        ->not->toContain('<script');
});
