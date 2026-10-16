<?php

namespace App\Modules\Experian\Jobs;

use App\Modules\Experian\Actions\ProcessExperianRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Makes the paid Surepass call for one pending Experian request, off the web
 * request. Exactly one attempt: $tries = 1 overrides the worker's --tries=3,
 * because a second attempt after a timeout could be a second charge. A job
 * that dies is resolved by failed(), and anything a dead worker leaves behind
 * is caught by the `experian:flag-stale` sweep.
 */
class FetchExperianRequest implements ShouldQueue
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

    public function handle(ProcessExperianRequest $process): void
    {
        $process->handle($this->requestId);
    }

    public function failed(?Throwable $exception): void
    {
        ProcessExperianRequest::markInterrupted($this->requestId);
    }
}
