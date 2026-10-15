<?php

namespace App\Console\Commands;

use App\Modules\CreditBureau\Actions\ProcessCrifReport;
use App\Modules\CreditBureau\Enums\CrifReportStatus;
use App\Modules\CreditBureau\Models\CrifReport;
use Illuminate\Console\Command;

/**
 * Catches CRIF requests a dead queue worker left behind, so a PAN is never
 * locked forever by a request nobody is working on:
 *
 *  - pending too long → it was never sent: failed, safe to retry;
 *  - processing too long → it may have been sent: reconciliation required.
 *
 * Never sends anything to Surepass itself.
 */
class CrifFlagStaleRequestsCommand extends Command
{
    protected $signature = 'crif:flag-stale {--minutes=15 : How long a request may sit pending or processing}';

    protected $description = 'Resolve CRIF report requests stuck in pending or processing';

    public function handle(ProcessCrifReport $process): int
    {
        $before = now()->subMinutes(max(5, (int) $this->option('minutes')));

        $stale = CrifReport::query()
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('status', CrifReportStatus::Pending)->where('requested_at', '<', $before))
                ->orWhere(fn ($query) => $query->where('status', CrifReportStatus::Processing)->where('processing_started_at', '<', $before)))
            ->get();

        $resolved = $stale->filter(fn (CrifReport $report) => $process->interrupt($report))->count();

        $this->components->info("{$resolved} stale CRIF request(s) resolved.");

        return self::SUCCESS;
    }
}
