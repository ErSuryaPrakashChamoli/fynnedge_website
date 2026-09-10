<?php

namespace App\Mail\Newsletter;

use App\Modules\Newsletter\Models\NewsletterSubscriber;
use Illuminate\Mail\Mailables\Content;

/**
 * A short receipt so someone who unsubscribed has written proof it worked —
 * and a resubscribe link if they did it by accident. This is the last email the
 * address receives.
 */
class UnsubscribeConfirmationMail extends NewsletterMail
{
    public function __construct(public NewsletterSubscriber $subscriber) {}

    protected function subjectLine(): string
    {
        return 'You have been unsubscribed from FynnEdge Insights';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter.unsubscribed',
            with: ['subscriber' => $this->subscriber],
        );
    }
}
