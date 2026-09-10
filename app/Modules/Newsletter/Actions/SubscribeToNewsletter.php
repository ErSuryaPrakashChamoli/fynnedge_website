<?php

namespace App\Modules\Newsletter\Actions;

use App\Mail\Newsletter\ConfirmationMail;
use App\Mail\Newsletter\WelcomeMail;
use App\Modules\Newsletter\Enums\SubscriberStatus;
use App\Modules\Newsletter\Enums\SubscriptionSource;
use App\Modules\Newsletter\Models\NewsletterSubscriber;
use App\Modules\Newsletter\Services\NewsletterSettings;
use Illuminate\Support\Facades\Mail;

/**
 * The one way a subscription is created or revived.
 *
 * It is idempotent by design: the email column is unique, so a repeat signup
 * updates the existing row rather than creating a second one, and the caller
 * gets back the same neutral outcome either way. That neutrality is deliberate
 * — the public endpoint must not reveal whether an address is already on the
 * list, or the form becomes a subscriber-enumeration oracle.
 *
 * An already-active subscriber is NOT re-confirmed and NOT re-welcomed: doing
 * so would let anyone trigger mail to any address by resubmitting the form.
 */
class SubscribeToNewsletter
{
    public function handle(
        string $email,
        ?string $name = null,
        SubscriptionSource $source = SubscriptionSource::Website,
        ?string $sourceUrl = null,
        ?string $consentIp = null,
    ): NewsletterSubscriber {
        $email = NewsletterSubscriber::normalizeEmail($email);

        $subscriber = NewsletterSubscriber::query()->firstOrNew(['email' => $email]);
        $wasActive = $subscriber->exists && $subscriber->status === SubscriberStatus::Active;

        $subscriber->fill(array_filter([
            'name' => $name ?: $subscriber->name,
            // Source is only recorded the first time, so the page that actually
            // won the subscriber isn't overwritten by a later duplicate submit.
            'source' => $subscriber->source ?: $source->value,
            'source_url' => $subscriber->source_url ?: $sourceUrl,
        ], fn (mixed $value): bool => filled($value)));

        $requiresConfirmation = NewsletterSettings::doubleOptInEnabled();

        if (! $wasActive) {
            $subscriber->forceFill([
                'status' => $requiresConfirmation ? SubscriberStatus::Pending : SubscriberStatus::Active,
                'subscribed_at' => $subscriber->subscribed_at ?? now(),
                'confirmed_at' => $requiresConfirmation ? null : ($subscriber->confirmed_at ?? now()),
                'unsubscribed_at' => null,
                'consent_at' => now(),
                'consent_ip' => $consentIp,
            ]);
        }

        $subscriber->save();

        if ($wasActive) {
            return $subscriber;
        }

        if ($requiresConfirmation) {
            $token = $subscriber->issueToken('confirmation_token');
            Mail::to($subscriber->email)->send(new ConfirmationMail($subscriber, $token));

            return $subscriber;
        }

        // Single opt-in: the welcome email is the only mail they get, and it
        // still has to carry a working unsubscribe link.
        $unsubscribeToken = $subscriber->issueToken('unsubscribe_token');

        if (NewsletterSettings::welcomeEmailEnabled()) {
            Mail::to($subscriber->email)->send(new WelcomeMail($subscriber, $unsubscribeToken));
        }

        return $subscriber;
    }
}
