<?php

namespace App\Console\Commands;

use App\Modules\Cibil\Actions\ProcessCibilRequest;
use App\Modules\Cibil\Enums\CibilRequestStatus;
use App\Modules\Cibil\Models\CibilCreditRequest;
use Illuminate\Console\Command;

/**
 * Catches CIBIL requests a dead queue worker left behind, so a PAN is never
 * locked forever: pending too long → never sent, failed and retryable;
 * processing too long → maybe sent, reconciliation required. Never calls Surepass.
 */
class CibilFlagStaleRequestsCommand extends Command
{
    protected $signature = 'cibil:flag-stale {--minutes=15 : How long a request may sit pending or processing}';

    protected $description = 'Resolve CIBIL requests stuck in pending or processing';

    public function handle(ProcessCibilRequest $process): int
    {
        $before = now()->subMinutes(max(5, (int) $this->option('minutes')));

        $stale = CibilCreditRequest::query()
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('status', CibilRequestStatus::Pending)->where('requested_at', '<', $before))
                ->orWhere(fn ($query) => $query->where('status', CibilRequestStatus::Processing)->where('processing_started_at', '<', $before)))
            ->get();

        $resolved = $stale->filter(fn (CibilCreditRequest $request) => $process->interrupt($request))->count();

        $this->components->info("{$resolved} stale CIBIL request(s) resolved.");

        return self::SUCCESS;
    }
}
