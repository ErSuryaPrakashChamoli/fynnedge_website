<?php

namespace App\Mail\Newsletter;

use App\Modules\Newsletter\Models\NewsletterSubscriber;
use Illuminate\Mail\Mailables\Content;

/**
 * Sent once, after confirmation. Takes the plaintext unsubscribe token because
 * the database only holds its hash — and because even a welcome email has to
 * carry a working way out.
 */
class WelcomeMail extends NewsletterMail
{
    public function __construct(
        public NewsletterSubscriber $subscriber,
        public ?string $unsubscribeToken = null,
    ) {}

    protected function subjectLine(): string
    {
        return 'Welcome to FynnEdge Insights';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter.welcome',
            with: [
                'subscriber' => $this->subscriber,
                'unsubscribeUrl' => $this->unsubscribeToken
                    ? route('newsletter.unsubscribe', ['token' => $this->unsubscribeToken])
                    : null,
            ],
        );
    }
}
