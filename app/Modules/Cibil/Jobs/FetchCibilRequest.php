<?php

namespace App\Modules\Cibil\Jobs;

use App\Modules\Cibil\Actions\ProcessCibilRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Makes the paid Surepass call for one pending CIBIL request, off the web
 * request. Exactly one attempt: $tries = 1 overrides the worker's --tries=3,
 * because a second attempt after a timeout could be a second charge. A job
 * that dies is resolved by failed(), and anything a dead worker leaves behind
 * is caught by the `cibil:flag-stale` sweep.
 */
class FetchCibilRequest implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Comfortably above the HTTP timeout, so the HTTP client gives up first. */
    public int $timeout = 150;

    public bool $failOnTimeout = true;

    public function __construct(public int $requestId)
    {
        $this->afterCommit();
    }

    public function handle(ProcessCibilRequest $process): void
    {
        $process->handle($this->requestId);
    }

    public function failed(?Throwable $exception): void
    {
        ProcessCibilRequest::markInterrupted($this->requestId);
    }
}
