<?php

namespace App\Modules\CreditScore\Contracts;

use App\Modules\CreditBureau\DataTransferObjects\CreditCheckResult;
use App\Modules\CreditScore\Models\CreditScoreCheck;

interface CreditScoreProvider
{
    public function name(): string;

    public function check(CreditScoreCheck $check): CreditCheckResult;
}
