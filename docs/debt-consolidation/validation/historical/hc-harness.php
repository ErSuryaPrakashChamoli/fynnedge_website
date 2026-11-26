<?php

/**
 * Historical case validation (BV-13 / HV-01): runs the production Debt
 * Consolidation engine on an anonymised historical case pack and writes the
 * engine's output plus a per-comparison extract (C1–C9) for the
 * "Comparison" sheet of HV-historical-validation-workbook.xlsx.
 *
 *   php docs/debt-consolidation/validation/historical/hc-harness.php <pack.json> <output-dir>
 *
 * Safeguards (18-HISTORICAL-CASE-VALIDATION-PROCEDURE.md §2, §4):
 *  - refuses to run unless the pack records the HV-00 Legal/DPO approval reference;
 *  - refuses a pack or output folder inside the website repository (the pack
 *    lives in the restricted validation folder, never in the codebase);
 *  - refuses case codes other than HC-01 … HC-20 and any field that looks like
 *    personal data;
 *  - reads no database and writes only the two output files.
 *
 * It never touches the preserved acceptance-stage files in the parent folder.
 */

use App\Modules\DebtConsolidation\DataTransferObjects\CaseInput;
use App\Modules\DebtConsolidation\DataTransferObjects\ScoringConfig;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Services\Finance\DebtConsolidationEngine;
use Illuminate\Contracts\Console\Kernel;

$repository = realpath(__DIR__.'/../../../..');

$fail = function (string $message): never {
    fwrite(STDERR, "Refused: {$message}\n");
    exit(1);
};

[$packPath, $outputDir] = [$argv[1] ?? null, $argv[2] ?? null];

if ($packPath === null || $outputDir === null) {
    $fail('usage: php hc-harness.php <pack.json> <output-dir>');
}

$packReal = realpath($packPath) ?: $fail("pack file {$packPath} not found.");
$outputReal = realpath($outputDir) ?: $fail("output folder {$outputDir} not found.");

foreach ([$packReal, $outputReal] as $path) {
    if (str_starts_with($path.'/', $repository.'/')) {
        $fail("{$path} is inside the website repository. Keep the validation pack in the restricted folder (procedure §2).");
    }
}

$pack = json_decode((string) file_get_contents($packReal), true, flags: JSON_THROW_ON_ERROR);

if (trim((string) ($pack['hv00_reference'] ?? '')) === '') {
    $fail('the pack has no "hv00_reference". HV-00 (Legal/DPO permission) must be recorded before any historical case is processed.');
}

$personalKeys = '/(^|_)(name|customer|pan|aadhaar|aadhar|mobile|phone|email|address|dob|birth|employer|account|card_number|loan_number|utr|application_number)(_|$)/i';
$allowedNameKeys = ['lender_name', 'label', 'title'];

$scan = function (mixed $value, string $path) use (&$scan, $personalKeys, $allowedNameKeys, $fail): void {
    if (! is_array($value)) {
        return;
    }

    foreach ($value as $key => $child) {
        if (is_string($key) && ! in_array($key, $allowedNameKeys, true) && preg_match($personalKeys, $key)) {
            $fail("field \"{$path}{$key}\" looks like personal data. Remove it (procedure §4).");
        }

        $scan($child, $path.$key.'.');
    }
};

require $repository.'/vendor/autoload.php';
$app = require $repository.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$results = [];
$extract = [['case', 'comparison', 'item', 'engine_value']];

foreach ($pack['cases'] ?? [] as $case) {
    $code = (string) ($case['key'] ?? '');

    if (! preg_match('/^HC-(0[1-9]|1\d|20)$/', $code)) {
        $fail("case code \"{$code}\" is not HC-01 … HC-20.");
    }

    $scan($case, $code.'.');

    $applicantType = ApplicantType::from($case['applicant_type']);
    $input = CaseInput::fromArray([
        ...$case,
        'assumptions' => ['as_of' => $case['as_of'], ...config('debt_consolidation.defaults'), 'gst_rate_percent' => 18.0, ...($case['assumptions'] ?? [])],
        'scoring_config' => ScoringConfig::defaults($applicantType)->toArray(),
        'route_liability_types' => config('debt_consolidation.route_liability_types'),
    ]);

    $result = (new DebtConsolidationEngine)->assess($input)->toArray();
    $results[$code] = $result;

    foreach ($result['liabilities'] as $key => $liability) {
        $closure = $liability['full_closure'] ?? null;

        if ($closure !== null) {
            $extract[] = [$code, 'C1', "{$key} closure total", $closure['total']];
            $extract[] = [$code, 'C2', "{$key} foreclosure charge", $closure['charges']];
            $extract[] = [$code, 'C2', "{$key} GST on charge", $closure['tax']];
        }

        $extract[] = [$code, 'C3', "{$key} remaining repayment", $liability['projection']['remaining_repayment']];
        $extract[] = [$code, 'C4', "{$key} remaining interest", $liability['projection']['remaining_interest']];
        $extract[] = [$code, 'C8', "{$key} months remaining (cards: months to clear)", $liability['projection']['remaining_months']];
    }

    foreach ($result['scenarios'] as $scenario) {
        $row = $scenario['result'];
        $label = $row['scenario_key'];
        $extract[] = [$code, 'C5', "{$label} loan amount", $row['loan_amount']];
        $extract[] = [$code, 'C6', "{$label} processing fee", $row['processing_fee']];
        $extract[] = [$code, 'C6', "{$label} GST on fee", $row['processing_fee_tax']];
        $extract[] = [$code, 'C6', "{$label} net disbursal", $row['net_disbursal']];
        $extract[] = [$code, 'C7', "{$label} new EMI", $row['new_emi']];
        $extract[] = [$code, 'C9', "{$label} residual repayment of part-closed debts", $row['residual_repayment']];
        $extract[] = [$code, 'C10', "{$label} proposed FOIR % / DSCR", $scenario['affordability']['proposed_value']];
        $extract[] = [$code, 'C11', "{$label} net financial benefit (review)", $row['net_financial_benefit']];
        $extract[] = [$code, 'C11', "{$label} suitability band (review)", $scenario['suitability']['band']];
    }
}

if ($results === []) {
    $fail('the pack has no cases.');
}

file_put_contents($outputReal.'/hc-engine-results.json', json_encode([
    'hv00_reference' => $pack['hv00_reference'],
    'formula_version' => $results[array_key_first($results)]['formula_version'],
    'run_at' => now('Asia/Kolkata')->toIso8601String(),
    'results' => $results,
], JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION));

$csv = fopen($outputReal.'/hc-engine-extract.csv', 'w');
foreach ($extract as $line) {
    fputcsv($csv, $line, escape: '');
}
fclose($csv);

echo 'Engine '.$results[array_key_first($results)]['formula_version'].': '.count($results).' cases, '.(count($extract) - 1)." comparison values written to {$outputReal}\n";
