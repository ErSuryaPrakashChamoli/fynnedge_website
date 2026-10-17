<?php

namespace App\Modules\Wallet\Exceptions;

use RuntimeException;

class InsufficientWalletBalance extends RuntimeException
{
    public function __construct(public readonly int $balancePaise, public readonly int $requiredPaise)
    {
        parent::__construct('The wallet balance does not cover this charge.');
    }
}
