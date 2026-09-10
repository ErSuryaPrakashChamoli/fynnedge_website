<?php

namespace App\Mail\Newsletter;

use App\Modules\Newsletter\Models\NewsletterSubscriber;
use Illuminate\Mail\Mailables\Content;

/**
 * Step two of double opt-in. Carries the ONLY copy of the plaintext
 * confirmation token — the database holds a hash — so it is passed in rather
 * than read off the model.
 */
class ConfirmationMail extends NewsletterMail
{
    public function __construct(
        public NewsletterSubscriber $subscriber,
        public string $token,
    ) {}

    protected function subjectLine(): string
    {
        return 'Please confirm your FynnEdge Insights subscription';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter.confirmation',
            with: [
                'subscriber' => $this->subscriber,
                'confirmUrl' => route('newsletter.confirm', ['token' => $this->token]),
                'expiryDays' => NewsletterSubscriber::CONFIRMATION_TTL_DAYS,
            ],
        );
    }
}
