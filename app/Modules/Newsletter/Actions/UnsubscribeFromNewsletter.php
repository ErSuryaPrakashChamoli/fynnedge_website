<?php

namespace App\Modules\Newsletter\Actions;

use App\Modules\Newsletter\Enums\SubscriberStatus;
use App\Modules\Newsletter\Models\NewsletterSubscriber;

/**
 * Unsubscribing NEVER deletes the row: the record of "this address asked not to
 * be mailed" is the whole point, and deleting it would let the same address be
 * re-added by an import and mailed again.
 *
 * The unsubscribe token is deliberately NOT consumed, so the link keeps working
 * if the person clicks it again from an older email — an unsubscribe link that
 * 404s reads as "it didn't work" and invites a spam complaint.
 */
class UnsubscribeFromNewsletter
{
    public function handle(string $token): ?NewsletterSubscriber
    {
        $subscriber = NewsletterSubscriber::findByToken('unsubscribe_token', $token)->first();

        if (! $subscriber) {
            return null;
        }

        if ($subscriber->status !== SubscriberStatus::Unsubscribed) {
            $subscriber->forceFill([
                'status' => SubscriberStatus::Unsubscribed,
                'unsubscribed_at' => now(),
            ])->save();
        }

        return $subscriber;
    }
}
