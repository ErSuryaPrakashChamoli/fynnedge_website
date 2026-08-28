<?php

use App\Models\AuditLog;
use App\Models\LenderProduct;
use App\Models\User;
use App\Modules\Eligibility\Enums\EligibilityRuleSetStatus;
use App\Modules\Eligibility\Models\EligibilityRuleSet;

it('logs a created entry with the actor when a model is made', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $lenderProduct = LenderProduct::factory()->create();
    $ruleSet = EligibilityRuleSet::factory()->create(['lender_product_id' => $lenderProduct->id]);

    $log = AuditLog::query()
        ->where('auditable_type', EligibilityRuleSet::class)
        ->where('auditable_id', $ruleSet->id)
        ->where('action', 'created')
        ->sole();

    expect($log->user_id)->toBe($user->id);
});

it('logs an updated entry containing only the changed attributes', function () {
    $ruleSet = EligibilityRuleSet::factory()->create(['status' => EligibilityRuleSetStatus::Draft]);

    $ruleSet->update(['status' => EligibilityRuleSetStatus::Active]);

    $log = AuditLog::query()
        ->where('auditable_type', EligibilityRuleSet::class)
        ->where('auditable_id', $ruleSet->id)
        ->where('action', 'updated')
        ->sole();

    expect($log->changes)->toHaveKey('status');
    expect($log->changes)->not->toHaveKey('created_at');
});

it('does not log an update entry when nothing meaningful changed', function () {
    $ruleSet = EligibilityRuleSet::factory()->create();

    $ruleSet->touch();

    $updateLogs = AuditLog::query()
        ->where('auditable_type', EligibilityRuleSet::class)
        ->where('auditable_id', $ruleSet->id)
        ->where('action', 'updated')
        ->count();

    expect($updateLogs)->toBe(0);
});

it('logs a deleted entry when a model is removed', function () {
    $ruleSet = EligibilityRuleSet::factory()->create();
    $id = $ruleSet->id;

    $ruleSet->delete();

    $log = AuditLog::query()
        ->where('auditable_type', EligibilityRuleSet::class)
        ->where('auditable_id', $id)
        ->where('action', 'deleted')
        ->sole();

    expect($log)->not->toBeNull();
});

it('exposes a model auditLogs relationship newest first', function () {
    $ruleSet = EligibilityRuleSet::factory()->create(['status' => EligibilityRuleSetStatus::Draft]);
    $ruleSet->update(['status' => EligibilityRuleSetStatus::Active]);

    $logs = $ruleSet->auditLogs;

    expect($logs)->toHaveCount(2);
    expect($logs->first()->action)->toBe('updated');
    expect($logs->last()->action)->toBe('created');
});
