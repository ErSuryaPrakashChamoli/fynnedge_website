<?php

namespace App\Support\Rates;

use RuntimeException;

/**
 * A rate could not move to the requested state. $problems lists each reason
 * in words an editor can act on, so the admin can show all of them at once.
 */
class RateWorkflowException extends RuntimeException
{
    /**
     * @param  list<string>  $problems
     */
    public function __construct(public readonly array $problems)
    {
        parent::__construct(implode(' ', $problems));
    }
}
