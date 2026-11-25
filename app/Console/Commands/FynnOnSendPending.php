<?php

namespace App\Console\Commands;

use App\Modules\FynnOn\Enums\FynnOnSyncStatus;
use App\Modules\FynnOn\Services\FynnOnSettings;
use App\Modules\FynnOn\Services\FynnOnSync;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\Models\LeadInboxEntry;
use App\Modules\Leads\Services\LeadInbox;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Queues leads that were never delivered to FynnOn LMS — typically everything
 * that arrived before the LMS was connected, or after an outage. Never run on
 * a schedule: pushing a backlog is a decision, so it needs --since (or --all).
 * `--failed` also re-queues leads whose delivery was abandoned.
 */
class FynnOnSendPending extends Command
{
    protected $signature = 'fynnon:send-pending
        {--since= : Only leads received on/after this date (YYYY-MM-DD, India time)}
        {--all : Every undelivered lead, however old}
        {--failed : Also resend leads whose delivery was abandoned}
        {--source=* : Limit to lead sources (enquiry, credit_score_check, online_application, loan_application, otp_verification)}
        {--dry-run : Count what would be queued without queuing anything}';

    protected $description = 'Queue website leads that have not yet reached FynnOn LMS';

    public function handle(): int
    {
        if (! FynnOnSettings::isConfigured()) {
            $this->components->error('FynnOn LMS is not configured: set FYNNON_LMS_URL and FYNNON_LMS_TOKEN first.');

            return self::FAILURE;
        }

        if (blank($this->option('since')) && ! $this->option('all')) {
            $this->components->error('Choose a range: --since=YYYY-MM-DD, or --all.');

            return self::FAILURE;
        }

        $sources = collect((array) $this->option('source'))
            ->map(fn (string $value): ?LeadSource => LeadSource::tryFrom($value))
            ->filter()
            ->whenEmpty(fn () => collect(FynnOnSettings::enabledSources()))
            ->filter(fn (LeadSource $source): bool => FynnOnSettings::sends($source))
            ->values()
            ->all();

        $statuses = $this->option('failed') ? [FynnOnSyncStatus::Abandoned->value] : [];

        $query = LeadInbox::query($sources)
            ->where(fn ($query) => $query->whereNull('lms_status')->orWhereIn('lms_status', $statuses))
            ->when($this->option('since'), fn ($query, string $since) => $query->where('received_at', '>=', Carbon::parse($since, 'Asia/Kolkata')->startOfDay()->utc()))
            ->orderBy('received_at');

        $queued = 0;
        $skipped = 0;

        $query->lazy(200)->each(function (LeadInboxEntry $entry) use (&$queued, &$skipped): void {
            $lead = $entry->source->modelClass()::query()->find($entry->source_id);

            if ($lead === null || ! FynnOnSync::isEligible($entry->source, $lead)) {
                $skipped++;

                return;
            }

            if (! $this->option('dry-run')) {
                FynnOnSync::queueManually($entry->source, $entry->source_id);
            }

            $queued++;
        });

        $verb = $this->option('dry-run') ? 'would be queued' : 'queued';
        $this->components->info("{$queued} lead(s) {$verb} for FynnOn LMS; {$skipped} not yet a lead (unverified or unsubmitted) skipped.");

        return self::SUCCESS;
    }
}
