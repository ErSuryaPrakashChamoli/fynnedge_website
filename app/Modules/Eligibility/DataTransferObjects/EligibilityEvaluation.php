<?php

namespace App\Modules\Eligibility\DataTransferObjects;

use App\Modules\Eligibility\Enums\EligibilityStatus;
use App\Modules\Eligibility\Enums\RulePriority;

final readonly class EligibilityEvaluation
{
    /**
     * @param  array<int, array{eligibility_rule_id: int, label: string, priority: RulePriority, passed: bool, customer_message: ?string}>  $reasons
     */
    public function __construct(
        public EligibilityStatus $status,
        public ?float $foir,
        public array $reasons,
    ) {}
}
