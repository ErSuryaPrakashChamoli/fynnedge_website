<?php

use App\Modules\Newsletter\Enums\NewsletterCategory;
use App\Modules\Newsletter\Enums\RecipientStatus;
use App\Modules\Newsletter\Enums\SubscriberStatus;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Models\NewsletterCampaignRecipient;
use App\Modules\Newsletter\Models\NewsletterSubscriber;

function trackedRecipient(array $campaignAttributes = []): NewsletterCampaignRecipient
{
    $subscriber = NewsletterSubscriber::factory()->active()->create();
    $campaign = NewsletterCampaign::factory()->create($campaignAttributes);

    return NewsletterCampaignRecipient::query()->create([
        'newsletter_campaign_id' => $campaign->getKey(),
        'newsletter_subscriber_id' => $subscriber->getKey(),
        'status' => RecipientStatus::Sent,
        'tracking_token' => hash('sha256', 'track-me'),
    ]);
}

it('records an open from the tracking pixel and returns an image', function () {
    $recipient = trackedRecipient();

    $this->get(route('newsletter.track.open', ['recipient' => $recipient->getKey(), 'token' => 'track-me']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/gif');

    expect($recipient->fresh()->opened_at)->not->toBeNull();
});

it('keeps the first open time when the image is fetched again', function () {
    $recipient = trackedRecipient();

    $this->get(route('newsletter.track.open', ['recipient' => $recipient->getKey(), 'token' => 'track-me']));
    $firstOpen = $recipient->fresh()->opened_at;

    $this->travel(1)->hours();
    $this->get(route('newsletter.track.open', ['recipient' => $recipient->getKey(), 'token' => 'track-me']));

    expect($recipient->fresh()->opened_at->toDateTimeString())->toBe($firstOpen->toDateTimeString());
});

it('still returns an image for a bad tracking token, without recording anything', function () {
    $recipient = trackedRecipient();

    $this->get(route('newsletter.track.open', ['recipient' => $recipient->getKey(), 'token' => 'wrong']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/gif');

    expect($recipient->fresh()->opened_at)->toBeNull();
});

it('records a click and redirects to the campaign CTA', function () {
    $recipient = trackedRecipient(['cta_url' => 'https://fynnedge.com/resources/example']);

    $this->get(route('newsletter.track.click', ['recipient' => $recipient->getKey(), 'token' => 'track-me']))
        ->assertRedirect('https://fynnedge.com/resources/example');

    $recipient->refresh();

    expect($recipient->clicked_at)->not->toBeNull()
        // A click proves a render, so it implies an open an image-blocker never reported.
        ->and($recipient->opened_at)->not->toBeNull();
});

it('cannot be used as an open redirect', function () {
    $recipient = trackedRecipient(['cta_url' => 'https://fynnedge.com/resources/example']);

    // The destination comes from the campaign row; a query parameter is ignored.
    $this->get(route('newsletter.track.click', [
        'recipient' => $recipient->getKey(),
        'token' => 'track-me',
    ]).'?url=https://evil.test')->assertRedirect('https://fynnedge.com/resources/example');
});

it('sends a bad click token home rather than anywhere an attacker chose', function () {
    $recipient = trackedRecipient(['cta_url' => 'https://fynnedge.com/resources/example']);

    $this->get(route('newsletter.track.click', ['recipient' => $recipient->getKey(), 'token' => 'wrong']))
        ->assertRedirect(url('/'));

    expect($recipient->fresh()->clicked_at)->toBeNull();
});

it('shows the preferences page for a valid token', function () {
    NewsletterSubscriber::factory()->active('unsub-token')->create(['email' => 'reader@example.com']);

    $this->get(route('newsletter.preferences', ['token' => 'unsub-token']))
        ->assertOk()
        ->assertSee('reader@example.com')
        ->assertSee('Credit &amp; CIBIL', false);
});

it('404s the preferences page for an unknown token, revealing nothing', function () {
    $this->get(route('newsletter.preferences', ['token' => 'nonsense']))->assertNotFound();
});

it('saves category preferences', function () {
    $subscriber = NewsletterSubscriber::factory()->active('unsub-token')->create();

    $this->post(route('newsletter.preferences.update', ['token' => 'unsub-token']), [
        'categories' => [NewsletterCategory::Loans->value, NewsletterCategory::CreditAndCibil->value],
    ])->assertRedirect();

    $subscriber->refresh()->load('preferences');

    expect($subscriber->acceptsCategory(NewsletterCategory::Loans))->toBeTrue()
        ->and($subscriber->acceptsCategory(NewsletterCategory::CreditAndCibil))->toBeTrue()
        ->and($subscriber->acceptsCategory(NewsletterCategory::PersonalFinance))->toBeFalse()
        ->and($subscriber->status)->toBe(SubscriberStatus::Active);
});

it('treats unticking every category as an unsubscribe', function () {
    $subscriber = NewsletterSubscriber::factory()->active('unsub-token')->create();

    $this->post(route('newsletter.preferences.update', ['token' => 'unsub-token']), ['categories' => []]);

    expect($subscriber->fresh()->status)->toBe(SubscriberStatus::Unsubscribed);
});

it('re-activates an unsubscribed person who picks a category again', function () {
    $subscriber = NewsletterSubscriber::factory()->unsubscribed('unsub-token')->create();

    $this->post(route('newsletter.preferences.update', ['token' => 'unsub-token']), [
        'categories' => [NewsletterCategory::Loans->value],
    ]);

    expect($subscriber->fresh()->status)->toBe(SubscriberStatus::Active);
});

it('ignores an unknown category rather than storing it', function () {
    $subscriber = NewsletterSubscriber::factory()->active('unsub-token')->create();

    $this->post(route('newsletter.preferences.update', ['token' => 'unsub-token']), [
        'categories' => ['loans', 'not_a_real_category'],
    ]);

    expect($subscriber->preferences()->pluck('category')->all())
        ->not->toContain('not_a_real_category');
});

it('counts a subscriber with no stored preferences as opted into everything', function () {
    $subscriber = NewsletterSubscriber::factory()->active()->create();

    foreach (NewsletterCategory::cases() as $category) {
        expect($subscriber->acceptsCategory($category))->toBeTrue();
    }
});
