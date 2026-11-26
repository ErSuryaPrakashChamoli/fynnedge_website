<?php

use App\Modules\DebtConsolidation\Actions\AssessDebtApplication;
use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Enums\LenderPolicyStatus;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtCalculationSnapshot;
use App\Modules\DebtConsolidation\Models\DebtFinancialProfile;
use App\Modules\DebtConsolidation\Models\DebtForeclosureQuotation;
use App\Modules\DebtConsolidation\Models\DebtLiability;
use App\Modules\DebtConsolidation\Models\DebtScenario;
use App\Modules\DebtConsolidation\Models\DebtScoringConfig;
use App\Modules\DebtConsolidation\Services\Finance\DebtConsolidationEngine;
use App\Modules\DebtConsolidation\Services\Finance\FormulaVersion;

it('stores an immutable snapshot that reproduces its own results', function () {
    $application = DebtApplication::factory()->create();
    DebtFinancialProfile::factory()->for($application, 'application')->create();
    DebtLiability::factory()->for($application, 'application')->create();
    DebtLiability::factory()->for($application, 'application')->creditCard()->create();
    DebtScenario::factory()->for($application, 'application')->create();

    $snapshot = app(AssessDebtApplication::class)->handle($application, ActorType::Staff);

    expect($snapshot->formula_version)->toBe(FormulaVersion::CURRENT)
        ->and($snapshot->inputsIntact())->toBeTrue()
        ->and((new DebtConsolidationEngine)->assess($snapshot->caseInput())->toArray())->toEqual($snapshot->results)
        // 5 L personal loan + 1.5 L card, the worked example in the engine tests.
        ->and((float) $snapshot->total_outstanding)->toBe(650000.0)
        ->and((float) $snapshot->net_benefit)->toBe(2220.49);
    expect(fn () => $snapshot->update(['results' => []]))->toThrow(LogicException::class);
});

it('scores with the active scoring configuration and records its version', function () {
    DebtScoringConfig::query()->where('applicant_type', ApplicantType::Individual)->update(['status' => LenderPolicyStatus::Retired]);
    $config = DebtScoringConfig::factory()->active()->create(['version' => '7']);
    $application = DebtApplication::factory()->create();
    DebtLiability::factory()->for($application, 'application')->create();

    $snapshot = app(AssessDebtApplication::class)->handle($application, ActorType::Staff);

    expect($snapshot->scoring_config_id)->toBe($config->id)
        ->and($snapshot->scoring_version)->toBe('7');
});

it('compares the standard scenarios from settings when the case has none', function () {
    $application = DebtApplication::factory()->create();
    DebtLiability::factory()->for($application, 'application')->create();

    $snapshot = app(AssessDebtApplication::class)->handle($application, ActorType::Customer);

    expect(collect($snapshot->results['scenarios'])->pluck('scenario.key')->all())
        ->toBe(['standard-personal_loan', 'standard-loan_against_property']);
});

it('uses a verified lender quotation in place of the estimated closure amount', function () {
    $application = DebtApplication::factory()->create();
    $liability = DebtLiability::factory()->for($application, 'application')->create(['foreclosure_charge_percent' => 4]);
    DebtForeclosureQuotation::factory()->for($liability, 'liability')->verified()->create(['total_amount' => 517500]);

    $snapshot = app(AssessDebtApplication::class)->handle($application, ActorType::Staff);
    $closure = $snapshot->results['liabilities'][$liability->public_id]['full_closure'];

    expect($closure['total'])->toEqual(517500)
        ->and($closure['source'])->toBe('quotation')
        ->and($closure['verified'])->toBeTrue();
});

it('refuses any change to, or deletion of, an assessment snapshot or the status history', function () {
    $application = DebtApplication::factory()->create();
    DebtLiability::factory()->for($application, 'application')->create();
    $snapshot = app(AssessDebtApplication::class)->handle($application, ActorType::Staff);
    $change = $application->recordStatusChange('draft', 'enquiry_received', ActorType::Customer);

    expect(fn () => $snapshot->update(['suitability_score' => 99]))->toThrow(LogicException::class)
        ->and(fn () => $snapshot->update(['debt_application_id' => DebtApplication::factory()->create()->id]))->toThrow(LogicException::class)
        ->and(fn () => $snapshot->delete())->toThrow(LogicException::class)
        ->and(fn () => $change->update(['note' => 'edited']))->toThrow(LogicException::class)
        ->and(fn () => $change->delete())->toThrow(LogicException::class);
});

it('links a calculator snapshot to a case once, after which only retention of anonymous ones may delete', function () {
    $anonymous = DebtCalculationSnapshot::factory()->create(['kind' => DebtCalculationSnapshot::KIND_CALCULATOR, 'debt_application_id' => null]);
    $linked = DebtCalculationSnapshot::factory()->create(['kind' => DebtCalculationSnapshot::KIND_CALCULATOR, 'debt_application_id' => null]);
    $case = DebtApplication::factory()->create();

    $linked->update(['debt_application_id' => $case->id]);

    expect(fn () => $linked->update(['debt_application_id' => DebtApplication::factory()->create()->id]))->toThrow(LogicException::class)
        ->and(fn () => $linked->delete())->toThrow(LogicException::class);

    $anonymous->delete();

    expect(DebtCalculationSnapshot::query()->whereKey($anonymous->id)->exists())->toBeFalse();
});
