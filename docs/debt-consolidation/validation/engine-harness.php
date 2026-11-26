<?php

/**
 * Runs the production Debt Consolidation engine (formula 1.0.0) on the
 * validation cases and writes its full output, untouched, to
 * engine-results.json. It reads no database and writes nothing else.
 *
 *   php docs/debt-consolidation/validation/engine-harness.php
 */

use App\Modules\DebtConsolidation\DataTransferObjects\CaseInput;
use App\Modules\DebtConsolidation\DataTransferObjects\ScoringConfig;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Services\Finance\DebtConsolidationEngine;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$cases = json_decode((string) file_get_contents(__DIR__.'/cases.json'), true, flags: JSON_THROW_ON_ERROR);
$out = [];

foreach ($cases as $case) {
    $applicantType = ApplicantType::from($case['applicant_type']);
    $input = CaseInput::fromArray([
        ...$case,
        // The shipped defaults: config/debt_consolidation.php and scoring configuration v1.
        'assumptions' => ['as_of' => $case['as_of'], ...config('debt_consolidation.defaults'), 'gst_rate_percent' => 18.0],
        'scoring_config' => ScoringConfig::defaults($applicantType)->toArray(),
        'route_liability_types' => config('debt_consolidation.route_liability_types'),
    ]);

    $out[$case['key']] = (new DebtConsolidationEngine)->assess($input)->toArray();
}

file_put_contents(__DIR__.'/engine-results.json', json_encode($out, JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION));
echo 'Engine '.$out[array_key_first($out)]['formula_version'].': '.count($out)." cases written to engine-results.json\n";
