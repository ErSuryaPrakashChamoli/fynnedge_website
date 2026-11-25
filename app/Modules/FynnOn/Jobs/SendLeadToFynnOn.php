<?php

namespace App\Modules\FynnOn\Jobs;

use App\Modules\FynnOn\DataTransferObjects\FynnOnDelivery;
use App\Modules\FynnOn\Enums\FynnOnSyncStatus;
use App\Modules\FynnOn\Notifications\FynnOnDeliveryFailed;
use App\Modules\FynnOn\Services\FynnOnClient;
use App\Modules\FynnOn\Services\FynnOnLeadPayload;
use App\Modules\FynnOn\Services\FynnOnSettings;
use App\Modules\FynnOn\Services\FynnOnSync;
use App\Modules\Leads\Enums\LeadSource;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Delivers one lead to FynnOn LMS, off the visitor's request, within seconds
 * of it being saved (dispatched after the database commit).
 *
 * Retries: a temporary failure (timeout, 5xx, 429) is recorded as Failed and
 * the job is released with a growing delay (1 min, 5 min, 15 min, 1 h); after
 * the 5th attempt — or at once for a failure a retry cannot fix (401, 422…) —
 * it is recorded as Abandoned and the alert email is notified. An admin can
 * resend from the Lead Inbox or the FynnOn Sync screen.
 *
 * Idempotency: unique per lead+event while queued (ShouldBeUnique), skipped if
 * the LMS already confirmed that event (unless an admin forces a resend), and
 * the same Idempotency-Key header is sent on every attempt.
 */
class SendLeadToFynnOn implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** Comfortably above the HTTP timeout, so the HTTP client gives up first. */
    public int $timeout = 60;

    public int $uniqueFor = 900;

    /**
     * Seconds to wait before attempts 2, 3, 4 and 5.
     *
     * @var array<int, int>
     */
    public const RETRY_DELAYS = [60, 300, 900, 3600];

    public function __construct(
        public string $source,
        public int $leadId,
        public string $event = FynnOnSync::EVENT_CREATED,
        public bool $force = false,
    ) {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return "{$this->source}:{$this->leadId}:{$this->event}".($this->force ? ':manual' : '');
    }

    public function handle(FynnOnClient $client): void
    {
        $source = LeadSource::tryFrom($this->source);
        $lead = $source?->modelClass()::query()->find($this->leadId);

        if ($source === null || $lead === null || ! FynnOnSettings::isConfigured()) {
            return;
        }

        if (! $this->force && FynnOnSync::wasDelivered($lead, $this->event)) {
            return;
        }

        $reference = FynnOnLeadPayload::reference($source, $lead);
        $delivery = $client->send(FynnOnLeadPayload::for($source, $lead, $this->event), $reference.':'.$this->event);
        $attempt = $this->attempts();

        if ($delivery->delivered()) {
            FynnOnSync::record($lead, $delivery->status, $delivery, $attempt, $this->event, $reference);

            return;
        }

        if ($delivery->retryable && $attempt < $this->tries) {
            FynnOnSync::record($lead, FynnOnSyncStatus::Failed, $delivery, $attempt, $this->event, $reference);
            $this->release(self::RETRY_DELAYS[$attempt - 1] ?? 3600);

            return;
        }

        $this->abandon($lead, $source, $delivery, $attempt, $reference);
    }

    public function failed(?Throwable $exception): void
    {
        $source = LeadSource::tryFrom($this->source);
        $lead = $source?->modelClass()::query()->find($this->leadId);

        if ($source === null || $lead === null) {
            return;
        }

        $this->abandon(
            $lead,
            $source,
            new FynnOnDelivery(FynnOnSyncStatus::Abandoned, error: 'Delivery job stopped unexpectedly ('.class_basename($exception ?? 'Error').').'),
            $this->attempts(),
            FynnOnLeadPayload::reference($source, $lead),
        );
    }

    private function abandon(Model $lead, LeadSource $source, FynnOnDelivery $delivery, int $attempt, string $reference): void
    {
        FynnOnSync::record($lead, FynnOnSyncStatus::Abandoned, $delivery, $attempt, $this->event, $reference);

        Log::warning('FynnOn LMS delivery abandoned', ['reference' => $reference, 'attempt' => $attempt, 'http_status' => $delivery->httpStatus, 'error' => $delivery->error]);

        if ($email = FynnOnSettings::alertEmail()) {
            Notification::route('mail', $email)->notify(new FynnOnDeliveryFailed($source, $reference, $delivery->error, $attempt));
        }
    }
}
