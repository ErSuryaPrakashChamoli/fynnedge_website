<?php

namespace App\Mail\Newsletter;

use App\Modules\Newsletter\Models\NewsletterCampaignRecipient;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Headers;

/**
 * One campaign, addressed to one recipient row.
 *
 * It takes the recipient rather than the subscriber because every tracking and
 * unsubscribe URL in the body is per-recipient — that is what makes opens,
 * clicks and one-click unsubscribes attributable without putting an email
 * address in a URL.
 */
class CampaignMail extends NewsletterMail
{
    public function __construct(
        public NewsletterCampaignRecipient $recipient,
        public string $trackingToken,
        public string $unsubscribeToken,
    ) {}

    protected function subjectLine(): string
    {
        return $this->recipient->campaign->subject;
    }

    /**
     * List-Unsubscribe lets Gmail/Outlook show their own one-click unsubscribe
     * control, which measurably reduces spam complaints — people who can't find
     * the link in the body report the message instead.
     */
    public function headers(): Headers
    {
        $url = route('newsletter.unsubscribe', ['token' => $this->unsubscribeToken]);

        return new Headers(text: [
            'List-Unsubscribe' => '<'.$url.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        $campaign = $this->recipient->campaign;

        return new Content(
            view: 'emails.newsletter.campaign',
            with: [
                'campaign' => $campaign,
                'subscriber' => $this->recipient->subscriber,
                'unsubscribeUrl' => route('newsletter.unsubscribe', ['token' => $this->unsubscribeToken]),
                'preferencesUrl' => route('newsletter.preferences', ['token' => $this->unsubscribeToken]),
                'openTrackingUrl' => route('newsletter.track.open', [
                    'recipient' => $this->recipient->getKey(),
                    'token' => $this->trackingToken,
                ]),
                'ctaUrl' => $campaign->cta_url
                    ? route('newsletter.track.click', [
                        'recipient' => $this->recipient->getKey(),
                        'token' => $this->trackingToken,
                    ])
                    : null,
            ],
        );
    }
}
