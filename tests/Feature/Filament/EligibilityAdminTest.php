<?php

use App\Enums\LenderStatus;
use App\Filament\Pages\EligibilityTester;
use App\Filament\Resources\EligibilityRuleSets\Pages\EditEligibilityRuleSet;
use App\Filament\Resources\EligibilityRuleSets\RelationManagers\AuditLogsRelationManager;
use App\Filament\Resources\EligibilityRuleSets\RelationManagers\RulesRelationManager;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LoanProduct;
use App\Models\User;
use App\Modules\Eligibility\Enums\EligibilityRuleSetStatus;
use App\Modules\Eligibility\Enums\RuleLogic;
use App\Modules\Eligibility\Enums\RuleOperator;
use App\Modules\Eligibility\Enums\RulePriority;
use App\Modules\Eligibility\Models\EligibilityRule;
use App\Modules\Eligibility\Models\EligibilityRuleCondition;
use App\Modules\Eligibility\Models\EligibilityRuleSet;
use App\Modules\Eligibility\Models\Employer;
use App\Modules\Eligibility\Models\EmployerCategory;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->admin);
});

it('renders the eligibility rule set index and edit pages', function () {
    $lender = Lender::factory()->create();
    $lenderProduct = LenderProduct::factory()->create(['lender_id' => $lender->id]);
    $ruleSet = EligibilityRuleSet::factory()->create(['lender_product_id' => $lenderProduct->id]);

    $this->get('/admin/eligibility-rule-sets')->assertOk();
    $this->get("/admin/eligibility-rule-sets/{$ruleSet->public_id}/edit")->assertOk();
});

it('opens a rule edit form with its conditions repeater pre-filled', function () {
    $lenderProduct = LenderProduct::factory()->create();
    $ruleSet = EligibilityRuleSet::factory()->create(['lender_product_id' => $lenderProduct->id]);
    $rule = EligibilityRule::factory()->create([
        'eligibility_rule_set_id' => $ruleSet->id,
        'label' => 'Minimum age',
        'priority' => RulePriority::Mandatory,
        'logic' => RuleLogic::And,
    ]);
    EligibilityRuleCondition::factory()->create([
        'eligibility_rule_id' => $rule->id,
        'attribute' => 'age',
        'operator' => RuleOperator::GreaterThanOrEqual,
        'value' => 21,
    ]);

    Livewire::test(RulesRelationManager::class, [
        'ownerRecord' => $ruleSet,
        'pageClass' => EditEligibilityRuleSet::class,
    ])
        ->assertCanSeeTableRecords([$rule])
        ->mountTableAction('edit', $rule)
        ->assertTableActionDataSet(['label' => 'Minimum age']);
});

it('renders the employer registry and lender employer categories', function () {
    $lender = Lender::factory()->create();
    $category = EmployerCategory::factory()->create(['lender_id' => $lender->id]);
    $employer = Employer::factory()->create();

    $this->get('/admin/employers')->assertOk();
    $this->get("/admin/employers/{$employer->public_id}/edit")->assertOk();
    $this->get("/admin/lenders/{$lender->public_id}/edit")->assertOk();
});

it('lets an admin evaluate a sample profile on the eligibility tester', function () {
    $loanProduct = LoanProduct::factory()->published()->create();
    $lender = Lender::factory()->create(['status' => LenderStatus::Active]);
    $lenderProduct = LenderProduct::factory()->create([
        'lender_id' => $lender->id,
        'loan_product_id' => $loanProduct->id,
        'status' => LenderStatus::Active,
    ]);
    $ruleSet = EligibilityRuleSet::factory()->create(['lender_product_id' => $lenderProduct->id]);
    EligibilityRuleCondition::factory()->create([
        'eligibility_rule_id' => EligibilityRule::factory()->create([
            'eligibility_rule_set_id' => $ruleSet->id,
            'priority' => RulePriority::Mandatory,
        ])->id,
        'attribute' => 'age',
        'operator' => RuleOperator::GreaterThanOrEqual,
        'value' => 21,
    ]);

    Livewire::test(EligibilityTester::class)
        ->fillForm([
            'loan_product_id' => $loanProduct->id,
            'age' => 30,
            'city' => 'Delhi',
            'monthly_income' => 60000,
            'loan_amount_requested' => 200000,
            'preferred_tenure_months' => 24,
        ])
        ->call('evaluate')
        ->assertHasNoFormErrors();
});

it('publishes a well-formed draft rule set from the edit page and blocks an empty one', function () {
    $lenderProduct = LenderProduct::factory()->create();
    $emptyDraft = EligibilityRuleSet::factory()->create([
        'lender_product_id' => $lenderProduct->id,
        'status' => EligibilityRuleSetStatus::Draft,
    ]);

    Livewire::test(EditEligibilityRuleSet::class, ['record' => $emptyDraft->getRouteKey()])
        ->callAction('publish');

    expect($emptyDraft->fresh()->status)->toBe(EligibilityRuleSetStatus::Draft);

    $readyDraft = EligibilityRuleSet::factory()->create([
        'lender_product_id' => LenderProduct::factory()->create()->id,
        'status' => EligibilityRuleSetStatus::Draft,
    ]);
    $rule = EligibilityRule::factory()->create([
        'eligibility_rule_set_id' => $readyDraft->id,
        'priority' => RulePriority::Mandatory,
    ]);
    EligibilityRuleCondition::factory()->create(['eligibility_rule_id' => $rule->id]);

    Livewire::test(EditEligibilityRuleSet::class, ['record' => $readyDraft->getRouteKey()])
        ->callAction('publish');

    expect($readyDraft->fresh()->status)->toBe(EligibilityRuleSetStatus::Active);
});

it('shows the audit history for a rule set', function () {
    $ruleSet = EligibilityRuleSet::factory()->create([
        'lender_product_id' => LenderProduct::factory()->create()->id,
        'status' => EligibilityRuleSetStatus::Draft,
    ]);
    $ruleSet->update(['status' => EligibilityRuleSetStatus::Active]);

    Livewire::test(AuditLogsRelationManager::class, [
        'ownerRecord' => $ruleSet,
        'pageClass' => EditEligibilityRuleSet::class,
    ])
        ->assertCanSeeTableRecords($ruleSet->auditLogs);
});
