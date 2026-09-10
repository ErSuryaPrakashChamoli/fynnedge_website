<?php

namespace App\Modules\Newsletter\Jobs;

use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Enums\RecipientStatus;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Models\NewsletterCampaignRecipient;
use App\Modules\Newsletter\Models\NewsletterSubscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

/**
 * Fans a campaign out into one queued email per recipient.
 *
 * The browser request that clicks "Send" only dispatches THIS job; it never
 * touches the mailer, so a campaign to fifty thousand people is the same
 * amount of work in the request as a campaign to one.
 *
 * Idempotence comes from the recipients table, not from job bookkeeping: rows
 * are inserted under a unique (campaign, subscriber) index BEFORE any mail is
 * queued, and an insert that collides is skipped. A retried or accidentally
 * re-dispatched job therefore cannot mail anyone twice.
 */
class SendNewsletterCampaign implements ShouldQueue
{
    use Queueable;

    /**
     * Chunked so the audience is never fully materialised in memory, and so a
     * failure part-way through leaves the already-claimed rows recorded.
     */
    private const CHUNK = 500;

    public function __construct(public NewsletterCampaign $campaign) {}

    public function handle(): void
    {
        $campaign = $this->campaign->fresh();

        // Cancelled between scheduling and running, or already sent: do nothing.
        if (! $campaign || ! in_array($campaign->status, [CampaignStatus::Scheduled, CampaignStatus::Sending], strict: true)) {
            return;
        }

        $campaign->forceFill(['status' => CampaignStatus::Sending])->save();

        $campaign->audience()->chunkById(self::CHUNK, function (Collection $subscribers) use ($campaign): void {
            foreach ($subscribers as $subscriber) {
                $this->queueFor($campaign, $subscriber);
            }
        });

        $campaign->forceFill([
            'status' => CampaignStatus::Sent,
            'sent_at' => now(),
        ])->save();
    }

    private function queueFor(NewsletterCampaign $campaign, NewsletterSubscriber $subscriber): void
    {
        $token = NewsletterCampaignRecipient::newTrackingToken();

        /*
         * firstOrCreate rather than create: the unique index is the real guard,
         * and this turns a duplicate into a no-op instead of an exception that
         * would fail the whole chunk. `wasRecentlyCreated` is what decides
         * whether to queue mail, so an existing row is never re-sent.
         */
        $recipient = NewsletterCampaignRecipient::query()->firstOrCreate(
            [
                'newsletter_campaign_id' => $campaign->getKey(),
                'newsletter_subscriber_id' => $subscriber->getKey(),
            ],
            [
                'status' => RecipientStatus::Pending,
                'tracking_token' => hash('sha256', $token),
            ],
        );

        if (! $recipient->wasRecentlyCreated) {
            return;
        }

        SendNewsletterEmail::dispatch($recipient, $token);
    }
}
