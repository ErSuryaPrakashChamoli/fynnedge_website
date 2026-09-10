<?php

namespace App\Http\Controllers;

use App\Mail\Newsletter\ConfirmationMail;
use App\Mail\Newsletter\UnsubscribeConfirmationMail;
use App\Modules\Newsletter\Actions\ConfirmSubscription;
use App\Modules\Newsletter\Actions\SubscribeToNewsletter;
use App\Modules\Newsletter\Actions\UnsubscribeFromNewsletter;
use App\Modules\Newsletter\Enums\NewsletterCategory;
use App\Modules\Newsletter\Enums\SubscriberStatus;
use App\Modules\Newsletter\Enums\SubscriptionSource;
use App\Modules\Newsletter\Models\NewsletterSubscriber;
use App\Modules\Newsletter\Services\NewsletterSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

/**
 * The public newsletter surface: subscribe, confirm, unsubscribe, preferences.
 *
 * Every response here is deliberately identical whether or not the address is
 * already on the list. Anything else turns the form into a subscriber
 * enumeration oracle — "already subscribed" is a yes/no answer about a real
 * person's email address to anyone who can type one in.
 */
class NewsletterController extends Controller
{
    public function subscribe(Request $request, SubscribeToNewsletter $subscribe): RedirectResponse
    {
        abort_unless(NewsletterSettings::enabled(), 404);

        $data = $request->validate([
            /*
             * `email:rfc` deliberately, NOT `email:rfc,dns`. The dns rule performs a
             * live MX lookup on every submission of a public, unauthenticated
             * endpoint — that is a network call an attacker controls the timing and
             * volume of, and it rejects legitimate addresses on domains behind
             * split-horizon DNS. Double opt-in is the real deliverability filter
             * here: an address that cannot receive the confirmation email never
             * becomes active.
             */
            'email' => ['required', 'email:rfc', 'max:190'],
            'name' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:40'],
            'source_url' => ['nullable', 'string', 'max:255'],
            // Honeypot: a real person never fills a hidden field. Same technique
            // the rest of the site's public forms could adopt; it costs nothing
            // and stops the bulk of drive-by bot submissions before rate limits.
            'website' => ['prohibited'],
        ], [
            'email.email' => 'Enter a valid email address.',
        ]);

        $subscribe->handle(
            email: $data['email'],
            name: $data['name'] ?? null,
            source: SubscriptionSource::fromRequest($data['source'] ?? null),
            sourceUrl: $this->resolveSourceUrl($request, $data['source_url'] ?? null),
            consentIp: $request->ip(),
        );

        return back()->with([
            'newsletterStatus' => NewsletterSettings::doubleOptInEnabled()
                ? 'Almost there — check your inbox and confirm your email address to finish subscribing.'
                : 'You are subscribed. Look out for practical financial insights in your inbox.',
        ])->withFragment('newsletter');
    }

    public function confirm(string $token, ConfirmSubscription $confirm): View
    {
        $outcome = $confirm->handle($token);

        return view('newsletter.confirm', [
            'result' => $outcome['result'],
            'subscriber' => $outcome['subscriber'],
        ]);
    }

    public function unsubscribe(string $token, UnsubscribeFromNewsletter $unsubscribe): View
    {
        $subscriber = $unsubscribe->handle($token);

        /*
         * The receipt is only sent on the transition, not on every visit — an
         * unsubscribe link is often clicked twice, and mailing someone who just
         * asked not to be mailed each time would be its own kind of spam.
         */
        if ($subscriber && $subscriber->wasChanged('status')) {
            Mail::to($subscriber->email)->send(new UnsubscribeConfirmationMail($subscriber));
        }

        return view('newsletter.unsubscribe', [
            'subscriber' => $subscriber,
            'token' => $subscriber ? $token : null,
        ]);
    }

    public function preferences(string $token): View
    {
        $subscriber = NewsletterSubscriber::findByToken('unsubscribe_token', $token)->with('preferences')->first();

        abort_unless($subscriber, 404);

        return view('newsletter.preferences', [
            'subscriber' => $subscriber,
            'token' => $token,
            'categories' => NewsletterCategory::cases(),
        ]);
    }

    public function updatePreferences(Request $request, string $token): RedirectResponse
    {
        $subscriber = NewsletterSubscriber::findByToken('unsubscribe_token', $token)->first();

        abort_unless($subscriber, 404);

        $data = $request->validate([
            'categories' => ['array'],
            'categories.*' => ['string'],
        ]);

        $selected = array_filter(
            $data['categories'] ?? [],
            fn (string $category): bool => NewsletterCategory::tryFrom($category) !== null,
        );

        foreach (NewsletterCategory::cases() as $category) {
            $subscriber->preferences()->updateOrCreate(
                ['category' => $category->value],
                ['is_subscribed' => in_array($category->value, $selected, strict: true)],
            );
        }

        /*
         * Unticking everything is an unsubscribe in all but name — honouring it
         * as "still active, but matches no segment" would leave them on the
         * list and still reachable by an unsegmented campaign.
         */
        if ($selected === []) {
            $subscriber->forceFill([
                'status' => SubscriberStatus::Unsubscribed,
                'unsubscribed_at' => now(),
            ])->save();
        } elseif ($subscriber->status === SubscriberStatus::Unsubscribed) {
            $subscriber->forceFill([
                'status' => SubscriberStatus::Active,
                'unsubscribed_at' => null,
                'confirmed_at' => $subscriber->confirmed_at ?? now(),
            ])->save();
        }

        return redirect()
            ->route('newsletter.preferences', ['token' => $token])
            ->with('newsletterStatus', 'Your email preferences have been saved.');
    }

    /**
     * Resends the confirmation email for a still-pending subscriber. Rate
     * limited like the subscribe endpoint, and silent about whether the address
     * exists, for the same enumeration reason.
     */
    public function resendConfirmation(Request $request, SubscribeToNewsletter $subscribe): RedirectResponse
    {
        abort_unless(NewsletterSettings::enabled(), 404);

        $data = $request->validate(['email' => ['required', 'email', 'max:190']]);

        $subscriber = NewsletterSubscriber::query()
            ->where('email', NewsletterSubscriber::normalizeEmail($data['email']))
            ->where('status', SubscriberStatus::Pending)
            ->first();

        if ($subscriber) {
            $token = $subscriber->issueToken('confirmation_token');
            Mail::to($subscriber->email)->send(new ConfirmationMail($subscriber, $token));
        }

        return back()->with('newsletterStatus', 'If that address is awaiting confirmation, we have sent the link again.');
    }

    /**
     * The blog URL a signup came from is the point of source tracking, so it is
     * taken from the form's own hidden field where present — url()->previous()
     * is unreliable once a browser suppresses the referer. It is stored as a
     * path only: a full URL from a visitor could point anywhere, and this value
     * is displayed in the admin panel.
     */
    private function resolveSourceUrl(Request $request, ?string $submitted): string
    {
        $candidate = $submitted ?: $request->headers->get('referer', '');

        return '/'.ltrim((string) parse_url((string) $candidate, PHP_URL_PATH), '/');
    }
}
