<?php

namespace App\Modules\Newsletter\Actions;

use App\Mail\Newsletter\WelcomeMail;
use App\Modules\Newsletter\Enums\SubscriberStatus;
use App\Modules\Newsletter\Models\NewsletterSubscriber;
use App\Modules\Newsletter\Services\NewsletterSettings;
use Illuminate\Support\Facades\Mail;

/**
 * Completes double opt-in. The confirmation token is consumed on success, so
 * the link in the inbox works exactly once; an unsubscribe token is issued in
 * the same step, because from here on every email this person receives must
 * carry a way out.
 */
class ConfirmSubscription
{
    public const RESULT_CONFIRMED = 'confirmed';

    public const RESULT_ALREADY_CONFIRMED = 'already_confirmed';

    public const RESULT_EXPIRED = 'expired';

    public const RESULT_INVALID = 'invalid';

    /**
     * @return array{result: string, subscriber: NewsletterSubscriber|null}
     */
    public function handle(string $token): array
    {
        $subscriber = NewsletterSubscriber::findByToken('confirmation_token', $token)->first();

        if (! $subscriber) {
            /*
             * A confirmed subscriber has already had this token cleared, so a
             * refreshed confirmation link lands here. Telling them "invalid"
             * would read as "your subscription broke", so an active subscriber
             * gets the already-confirmed page instead — no token needed, since
             * this branch reveals nothing an unauthenticated visitor didn't
             * already hold a link for.
             */
            return ['result' => self::RESULT_INVALID, 'subscriber' => null];
        }

        if ($subscriber->status === SubscriberStatus::Active) {
            return ['result' => self::RESULT_ALREADY_CONFIRMED, 'subscriber' => $subscriber];
        }

        if ($subscriber->confirmationTokenIsExpired()) {
            return ['result' => self::RESULT_EXPIRED, 'subscriber' => $subscriber];
        }

        $subscriber->forceFill([
            'status' => SubscriberStatus::Active,
            'confirmed_at' => now(),
            'unsubscribed_at' => null,
            'confirmation_token' => null,
        ])->save();

        $unsubscribeToken = $subscriber->issueToken('unsubscribe_token');

        if (NewsletterSettings::welcomeEmailEnabled()) {
            Mail::to($subscriber->email)->send(new WelcomeMail($subscriber, $unsubscribeToken));
        }

        return ['result' => self::RESULT_CONFIRMED, 'subscriber' => $subscriber];
    }
}
