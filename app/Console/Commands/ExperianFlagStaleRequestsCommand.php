<?php

namespace App\Console\Commands;

use App\Modules\Experian\Actions\ProcessExperianRequest;
use App\Modules\Experian\Enums\ExperianRequestStatus;
use App\Modules\Experian\Models\ExperianCreditRequest;
use Illuminate\Console\Command;

/**
 * Catches Experian requests a dead queue worker left behind, so a PAN is never
 * locked forever: pending too long → never sent, failed and retryable;
 * processing too long → maybe sent, reconciliation required. Never calls Surepass.
 */
class ExperianFlagStaleRequestsCommand extends Command
{
    protected $signature = 'experian:flag-stale {--minutes=15 : How long a request may sit pending or processing}';

    protected $description = 'Resolve Experian requests stuck in pending or processing';

    public function handle(ProcessExperianRequest $process): int
    {
        $before = now()->subMinutes(max(5, (int) $this->option('minutes')));

        $stale = ExperianCreditRequest::query()
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('status', ExperianRequestStatus::Pending)->where('requested_at', '<', $before))
                ->orWhere(fn ($query) => $query->where('status', ExperianRequestStatus::Processing)->where('processing_started_at', '<', $before)))
            ->get();

        $resolved = $stale->filter(fn (ExperianCreditRequest $request) => $process->interrupt($request))->count();

        $this->components->info("{$resolved} stale Experian request(s) resolved.");

        return self::SUCCESS;
    }
}
