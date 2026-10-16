<?php

namespace App\Console\Commands;

use App\Modules\Equifax\Actions\ProcessEquifaxRequest;
use App\Modules\Equifax\Enums\EquifaxRequestStatus;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use Illuminate\Console\Command;

/**
 * Catches Equifax requests a dead queue worker left behind, so a PAN is never
 * locked forever: pending too long → never sent, failed and retryable;
 * processing too long → maybe sent, reconciliation required. Never calls Surepass.
 */
class EquifaxFlagStaleRequestsCommand extends Command
{
    protected $signature = 'equifax:flag-stale {--minutes=15 : How long a request may sit pending or processing}';

    protected $description = 'Resolve Equifax requests stuck in pending or processing';

    public function handle(ProcessEquifaxRequest $process): int
    {
        $before = now()->subMinutes(max(5, (int) $this->option('minutes')));

        $stale = EquifaxCreditRequest::query()
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('status', EquifaxRequestStatus::Pending)->where('requested_at', '<', $before))
                ->orWhere(fn ($query) => $query->where('status', EquifaxRequestStatus::Processing)->where('processing_started_at', '<', $before)))
            ->get();

        $resolved = $stale->filter(fn (EquifaxCreditRequest $request) => $process->interrupt($request))->count();

        $this->components->info("{$resolved} stale Equifax request(s) resolved.");

        return self::SUCCESS;
    }
}
