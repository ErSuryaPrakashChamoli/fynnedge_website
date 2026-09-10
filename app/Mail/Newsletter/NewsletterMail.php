<?php

namespace App\Mail\Newsletter;

use App\Modules\Newsletter\Services\NewsletterSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Shared base for every newsletter email.
 *
 * Two things are centralised here on purpose:
 *   - the sender identity, so it comes from the admin-managed settings with a
 *     fall back to the app's mail.from, and never from a hardcoded address;
 *   - ShouldQueue, so no email is ever sent inside the browser request that
 *     triggered it. The queue connection stays whatever the app is configured
 *     for (database today) — this class makes no transport assumptions.
 */
abstract class NewsletterMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    abstract protected function subjectLine(): string;

    public function envelope(): Envelope
    {
        $replyTo = NewsletterSettings::replyTo();

        return new Envelope(
            from: new Address(NewsletterSettings::senderEmail(), NewsletterSettings::senderName()),
            replyTo: $replyTo ? [new Address($replyTo)] : [],
            subject: $this->subjectLine(),
        );
    }
}
