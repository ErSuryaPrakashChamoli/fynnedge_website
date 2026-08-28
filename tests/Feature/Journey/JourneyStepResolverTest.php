<?php

use App\Models\LoanProduct;
use App\Modules\Journey\Enums\FieldType;
use App\Modules\Journey\Enums\JourneyDefinitionStatus;
use App\Modules\Journey\Models\JourneyDefinition;
use App\Modules\Journey\Models\JourneyStep;
use App\Modules\Journey\Models\JourneyStepField;
use App\Modules\Journey\Services\JourneyStepResolver;

beforeEach(function () {
    $this->resolver = new JourneyStepResolver;

    $product = LoanProduct::factory()->create();
    $this->definition = JourneyDefinition::create([
        'loan_product_id' => $product->id,
        'version' => 1,
        'status' => JourneyDefinitionStatus::Active,
    ]);

    $this->stepA = JourneyStep::create(['journey_definition_id' => $this->definition->id, 'key' => 'a', 'title' => 'A', 'order' => 1]);
    $this->stepB = JourneyStep::create([
        'journey_definition_id' => $this->definition->id, 'key' => 'b', 'title' => 'B', 'order' => 2,
        'condition_rules' => ['field' => 'wants_b', 'operator' => '=', 'value' => 'yes'],
    ]);
    $this->stepC = JourneyStep::create(['journey_definition_id' => $this->definition->id, 'key' => 'c', 'title' => 'C', 'order' => 3]);

    $this->definition->load('steps.fields');
});

it('includes a conditional step only when its condition is satisfied', function () {
    $withoutB = $this->resolver->visibleSteps($this->definition, []);
    expect($withoutB->pluck('key')->all())->toBe(['a', 'c']);

    $withB = $this->resolver->visibleSteps($this->definition, ['wants_b' => 'yes']);
    expect($withB->pluck('key')->all())->toBe(['a', 'b', 'c']);
});

it('resolves the next visible step, skipping hidden ones', function () {
    $next = $this->resolver->nextStep($this->definition, $this->stepA, []);
    expect($next->key)->toBe('c');

    $nextWithB = $this->resolver->nextStep($this->definition, $this->stepA, ['wants_b' => 'yes']);
    expect($nextWithB->key)->toBe('b');
});

it('returns null for the previous step from the first step', function () {
    expect($this->resolver->previousStep($this->definition, $this->stepA, []))->toBeNull();
});

it('resolves the previous visible step', function () {
    $previous = $this->resolver->previousStep($this->definition, $this->stepC, []);
    expect($previous->key)->toBe('a');
});

it('computes progress against only the visible steps', function () {
    $progress = $this->resolver->progress($this->definition, $this->stepC, []);
    expect($progress)->toBe(['completed' => 1, 'total' => 2, 'percent' => 50]);
});

it('builds validation rules only for visible fields', function () {
    JourneyStepField::create([
        'journey_step_id' => $this->stepA->id, 'key' => 'name', 'label' => 'Name',
        'type' => FieldType::Text, 'validation_rules' => ['required', 'string'], 'order' => 1,
    ]);
    JourneyStepField::create([
        'journey_step_id' => $this->stepA->id, 'key' => 'nickname', 'label' => 'Nickname',
        'type' => FieldType::Text, 'validation_rules' => ['required'], 'order' => 2,
        'conditional_on' => ['field' => 'has_nickname', 'operator' => '=', 'value' => 'yes'],
    ]);

    $this->stepA->refresh();

    $rules = $this->resolver->validationRulesFor($this->stepA, []);
    expect($rules)->toBe(['name' => ['required', 'string']]);

    $rulesWithNickname = $this->resolver->validationRulesFor($this->stepA, ['has_nickname' => 'yes']);
    expect($rulesWithNickname)->toBe(['name' => ['required', 'string'], 'nickname' => ['required']]);
});
