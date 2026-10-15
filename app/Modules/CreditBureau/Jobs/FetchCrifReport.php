<?php

namespace App\Modules\CreditBureau\Jobs;

use App\Modules\CreditBureau\Actions\ProcessCrifReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Makes the paid Surepass call for one pending CRIF request, off the web
 * request. Exactly one attempt: $tries = 1 overrides the worker's --tries=3,
 * because a second attempt after a timeout could be a second charge. A job
 * that dies is resolved by failed(), and anything a dead worker leaves behind
 * is caught by the `crif:flag-stale` sweep.
 */
class FetchCrifReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Comfortably above the HTTP timeout, so the HTTP client gives up first. */
    public int $timeout = 150;

    public bool $failOnTimeout = true;

    public function __construct(public int $reportId)
    {
        $this->afterCommit();
    }

    public function handle(ProcessCrifReport $process): void
    {
        $process->handle($this->reportId);
    }

    public function failed(?Throwable $exception): void
    {
        ProcessCrifReport::markInterrupted($this->reportId);
    }
}
