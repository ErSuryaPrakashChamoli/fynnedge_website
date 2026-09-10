<?php

namespace App\Http\Controllers;

use App\Modules\Newsletter\Models\NewsletterCampaignRecipient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * First-party campaign engagement tracking.
 *
 * The mailer here is provider-agnostic (`log` by default), so there are no
 * delivery/open webhooks to consume — these two endpoints are the only
 * engagement signals the application can honestly produce by itself:
 *
 *   open  — a 1x1 image. Most clients block images by default, so opens are a
 *           FLOOR, never a count. The admin UI says so rather than implying
 *           precision.
 *   click — the campaign's own CTA, routed through here. The destination comes
 *           from the campaign row, never from a query parameter, so this cannot
 *           be used as an open redirect.
 *
 * `delivered_at` stays null unless a provider webhook ever fills it in. It is
 * never inferred, because "handed to the transport" is not "delivered".
 */
class NewsletterTrackingController extends Controller
{
    /**
     * A transparent 1x1 GIF, so a blocked or expired token still renders
     * something harmless instead of a broken-image icon in someone's inbox.
     */
    private const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function open(int $recipient, string $token): Response
    {
        $this->resolve($recipient, $token)?->markOpened();

        return response(base64_decode(self::PIXEL))
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function click(int $recipient, string $token): RedirectResponse
    {
        $record = $this->resolve($recipient, $token);

        $record?->markClicked();

        $destination = $record?->campaign?->cta_url;

        return redirect()->away($destination ?: url('/'));
    }

    private function resolve(int $recipient, string $token): ?NewsletterCampaignRecipient
    {
        return NewsletterCampaignRecipient::query()
            ->with('campaign')
            ->whereKey($recipient)
            ->where('tracking_token', hash('sha256', $token))
            ->first();
    }
}
