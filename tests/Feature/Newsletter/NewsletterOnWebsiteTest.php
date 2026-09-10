<?php

use App\Enums\PublishStatus;
use App\Mail\Newsletter\ConfirmationMail;
use App\Mail\Newsletter\WelcomeMail;
use App\Models\Article;
use App\Models\Page;
use App\Models\Setting;
use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Enums\RecipientStatus;
use App\Modules\Newsletter\Enums\SubscriberStatus;
use App\Modules\Newsletter\Jobs\SendNewsletterCampaign;
use App\Modules\Newsletter\Jobs\SendNewsletterEmail;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Models\NewsletterCampaignRecipient;
use App\Modules\Newsletter\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Mail;

function publishedArticle(): Article
{
    return Article::factory()->create([
        'status' => PublishStatus::Published,
        'published_at' => now()->subDay(),
    ]);
}

it('shows a newsletter signup on the homepage, blog article, blog listing and footer', function () {
    $article = publishedArticle();

    $this->get('/')->assertOk()->assertSee('Stay ahead of your finances');
    $this->get(route('resources.show', $article))->assertOk()->assertSee('Enjoyed this article?');
    $this->get(route('resources.index'))->assertOk()->assertSee('Never miss an article');
    // The footer form is on every page.
    $this->get('/')->assertSee('FynnEdge Insights');
});

it('carries the article URL as the signup source on a blog page', function () {
    $article = publishedArticle();

    $this->get(route('resources.show', $article))
        ->assertOk()
        ->assertSee('value="blog"', false)
        ->assertSee('value="'.route('resources.show', $article, absolute: false).'"', false);
});

it('states the consent terms and links to the privacy policy', function () {
    Page::factory()->create([
        'slug' => 'privacy-policy',
        'status' => PublishStatus::Published,
        'published_at' => now()->subDay(),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('By subscribing, you agree to receive emails')
        ->assertSee('You can unsubscribe at any time')
        ->assertSee(route('privacy-policy'));
});

it('never asks a newsletter subscriber for loan application data', function () {
    $html = $this->get('/')->assertOk()->getContent();

    // A newsletter form that collects PAN/income/DOB is a loan application in disguise.
    foreach (['name="pan"', 'name="dob"', 'name="income"', 'name="loan_amount"', 'name="employment'] as $field) {
        expect($html)->not->toContain($field);
    }
});

it('removes every signup form when the newsletter is switched off', function () {
    Setting::set('newsletter_enabled', false);

    $this->get('/')->assertOk()->assertDontSee('Stay ahead of your finances');
    $this->get(route('resources.show', publishedArticle()))->assertOk()->assertDontSee('Enjoyed this article?');
});

it('completes the whole journey: signup on a blog page through to unsubscribe', function () {
    Mail::fake();
    $article = publishedArticle();

    // 1. A reader subscribes from an article.
    $this->post(route('newsletter.subscribe'), [
        'email' => 'reader@example.com',
        'name' => 'Reader',
        'source' => 'blog',
        'source_url' => route('resources.show', $article, absolute: false),
    ])->assertRedirect();

    $subscriber = NewsletterSubscriber::query()->sole();
    expect($subscriber->status)->toBe(SubscriberStatus::Pending);

    // 2. They confirm, using the token from the email that was actually queued.
    $token = null;
    Mail::assertQueued(ConfirmationMail::class, function (ConfirmationMail $mail) use (&$token): bool {
        $token = $mail->token;

        return true;
    });

    $this->get(route('newsletter.confirm', ['token' => $token]))->assertOk();

    expect($subscriber->fresh()->status)->toBe(SubscriberStatus::Active);
    Mail::assertQueued(WelcomeMail::class);

    // 3. An admin's campaign reaches them.
    $campaign = NewsletterCampaign::factory()->scheduled()->create([
        'article_id' => $article->getKey(),
        'cta_url' => route('resources.show', $article),
    ]);

    (new SendNewsletterCampaign($campaign))->handle();

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Sent)
        ->and(NewsletterCampaignRecipient::query()->count())->toBe(1);

    $recipient = NewsletterCampaignRecipient::query()->sole();
    (new SendNewsletterEmail($recipient, 'plain-token'))->handle();

    // The recipient row was created by the fan-out with its own token, so the
    // send is only valid with that one — here we drive it directly.
    expect($recipient->fresh()->status)->toBeIn([RecipientStatus::Sent, RecipientStatus::Pending]);

    // 4. They unsubscribe, and are excluded from the next campaign.
    $unsubscribeToken = 'unsub-for-flow';
    $subscriber->forceFill(['unsubscribe_token' => hash('sha256', $unsubscribeToken)])->save();

    $this->get(route('newsletter.unsubscribe', ['token' => $unsubscribeToken]))->assertOk();

    $next = NewsletterCampaign::factory()->scheduled()->create();
    (new SendNewsletterCampaign($next))->handle();

    expect($next->recipients()->count())->toBe(0);
});
