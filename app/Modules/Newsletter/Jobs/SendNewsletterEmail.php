<?php

namespace App\Modules\Newsletter\Jobs;

use App\Mail\Newsletter\CampaignMail;
use App\Modules\Newsletter\Enums\RecipientStatus;
use App\Modules\Newsletter\Models\NewsletterCampaignRecipient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * One campaign email to one recipient.
 *
 * Per-recipient rather than per-batch so one bad address fails one row instead
 * of aborting the send, and so a retry re-sends only that message.
 */
class SendNewsletterEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  string  $trackingToken  plaintext; the row stores only its hash
     */
    public function __construct(
        public NewsletterCampaignRecipient $recipient,
        public string $trackingToken,
    ) {}

    public function handle(): void
    {
        $recipient = $this->recipient->fresh(['subscriber', 'campaign']);

        if (! $recipient || $recipient->status !== RecipientStatus::Pending) {
            return;
        }

        /*
         * Consent is re-checked at the moment of sending, not just when the
         * audience was built: a large campaign can spend minutes in the queue,
         * and someone who unsubscribes during that window must not receive it.
         */
        if (! $recipient->subscriber?->isMailable()) {
            $recipient->forceFill([
                'status' => RecipientStatus::Skipped,
                'error_message' => 'Subscriber was no longer mailable at send time.',
            ])->save();

            return;
        }

        $unsubscribeToken = $recipient->subscriber->issueToken('unsubscribe_token');

        try {
            Mail::to($recipient->subscriber->email)->send(
                new CampaignMail($recipient, $this->trackingToken, $unsubscribeToken),
            );
        } catch (Throwable $exception) {
            $recipient->forceFill([
                'status' => RecipientStatus::Failed,
                'failed_at' => now(),
                'error_message' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }

        /*
         * "Sent" means handed to the mail transport — that is the last thing
         * this application can honestly observe. delivered_at stays null unless
         * a provider webhook ever fills it in; it is never inferred from a
         * successful hand-off.
         */
        $recipient->forceFill([
            'status' => RecipientStatus::Sent,
            'sent_at' => now(),
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        $this->recipient->fresh()?->forceFill([
            'status' => RecipientStatus::Failed,
            'failed_at' => now(),
            'error_message' => $exception->getMessage(),
        ])->save();
    }
}
