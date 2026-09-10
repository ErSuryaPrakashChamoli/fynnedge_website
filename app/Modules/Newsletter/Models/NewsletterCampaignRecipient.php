<?php

namespace App\Modules\Newsletter\Models;

use App\Modules\Newsletter\Enums\RecipientStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * The per-subscriber delivery log, and the ledger that makes sending idempotent:
 * a row is inserted (under a unique index) before any mail is queued, so a
 * retried or re-dispatched campaign job cannot mail the same person twice.
 */
#[Fillable(['newsletter_campaign_id', 'newsletter_subscriber_id', 'status', 'tracking_token'])]
class NewsletterCampaignRecipient extends Model
{
    protected function casts(): array
    {
        return [
            'status' => RecipientStatus::class,
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * @var array<int, string>
     */
    protected $hidden = ['tracking_token'];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NewsletterCampaign::class, 'newsletter_campaign_id');
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(NewsletterSubscriber::class, 'newsletter_subscriber_id');
    }

    /**
     * Tracking tokens are stored hashed like every other token here: an open
     * pixel URL sits in an inbox forever and gets forwarded, so the value in
     * the URL must not be the value in the database.
     */
    public static function newTrackingToken(): string
    {
        return Str::random(48);
    }

    public function markOpened(): void
    {
        // First open only: later opens are re-fetches of the same image and
        // would otherwise keep overwriting the timestamp that mattered.
        if ($this->opened_at === null) {
            $this->forceFill(['opened_at' => now()])->save();
        }
    }

    public function markClicked(): void
    {
        $this->forceFill([
            'clicked_at' => $this->clicked_at ?? now(),
            // A click proves delivery and a render, so it implies an open that
            // an image-blocking client never reported.
            'opened_at' => $this->opened_at ?? now(),
        ])->save();
    }
}
