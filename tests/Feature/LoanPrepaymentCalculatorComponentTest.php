<?php

use App\Enums\LoanCategory;
use Livewire\Livewire;

beforeEach(function () {
    seedCalculatorProduct(LoanCategory::PersonalLoan);
    seedCalculatorProduct(LoanCategory::HomeLoan);
});

it('mounts with the given category\'s preset defaults', function () {
    Livewire::test('loan-prepayment-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->assertSet('category', LoanCategory::PersonalLoan->value)
        ->assertSet('outstandingPrincipal', 500000.0)
        ->assertSet('mode', 'reduce_tenure')
        ->assertOk();
});

it('falls back to Personal Loan for a category with no preset', function () {
    Livewire::test('loan-prepayment-calculator', ['category' => LoanCategory::CreditCard->value])
        ->assertSet('category', LoanCategory::PersonalLoan->value);
});

it('switches to reduce-EMI mode', function () {
    Livewire::test('loan-prepayment-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->call('selectMode', 'reduce_emi')
        ->assertSet('mode', 'reduce_emi');
});

it('clamps a prepayment amount larger than the outstanding principal', function () {
    Livewire::test('loan-prepayment-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('prepaymentAmount', 10_000_000)
        ->assertSet('prepaymentAmount', 500000.0)
        ->assertHasErrors(['prepaymentAmount']);
});

it('shows a shorter new tenure than the original in reduce-tenure mode', function () {
    $component = Livewire::test('loan-prepayment-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('prepaymentAmount', 100000);

    $result = $component->instance()->result();

    expect($result['new_tenure_months'])->toBeLessThan($result['original_tenure_months']);
});
