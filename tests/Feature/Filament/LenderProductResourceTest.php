<?php

use App\Enums\LenderStatus;
use App\Filament\RelationManagers\AuditLogsRelationManager;
use App\Filament\Resources\LenderProducts\Pages\CreateLenderProduct;
use App\Filament\Resources\LenderProducts\Pages\EditLenderProduct;
use App\Filament\Resources\LenderProducts\Pages\ListLenderProducts;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LoanProduct;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('lists lender offers', function () {
    LenderProduct::factory()->count(3)->create();

    Livewire::test(ListLenderProducts::class)
        ->assertSuccessful();
});

it('creates a lender offer with eligibility criteria', function () {
    $lender = Lender::factory()->create();
    $loanProduct = LoanProduct::factory()->create();

    Livewire::test(CreateLenderProduct::class)
        ->fillForm([
            'lender_id' => $lender->id,
            'loan_product_id' => $loanProduct->id,
            'status' => LenderStatus::Active->value,
            'min_amount' => 50000,
            'max_amount' => 1500000,
            'processing_fee_percent_min' => 1.5,
            'processing_fee_percent_max' => 3,
            'min_age' => 21,
            'max_age' => 58,
            'min_credit_score' => 700,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(LenderProduct::query()
        ->where('lender_id', $lender->id)
        ->where('loan_product_id', $loanProduct->id)
        ->where('min_credit_score', 700)
        ->exists())->toBeTrue();
});

it('rejects a duplicate lender + loan product combination', function () {
    $lender = Lender::factory()->create();
    $loanProduct = LoanProduct::factory()->create();
    LenderProduct::factory()->for($lender)->for($loanProduct, 'loanProduct')->create();

    Livewire::test(CreateLenderProduct::class)
        ->fillForm([
            'lender_id' => $lender->id,
            'loan_product_id' => $loanProduct->id,
            'status' => LenderStatus::Active->value,
        ])
        ->call('create')
        ->assertHasErrors();
});

it('records and shows an audit trail when a lender offer changes its terms', function () {
    $lenderProduct = LenderProduct::factory()->create(['max_amount' => 1000000, 'max_tenure_months' => 60]);

    $lenderProduct->update(['max_amount' => 3000000, 'max_tenure_months' => 84]);

    $log = $lenderProduct->auditLogs()->where('action', 'updated')->sole();
    expect($log->changes)->toHaveKeys(['max_amount', 'max_tenure_months']);
    expect($log->changes['max_tenure_months'])->toBe(['old' => 60, 'new' => 84]);

    Livewire::test(AuditLogsRelationManager::class, [
        'ownerRecord' => $lenderProduct,
        'pageClass' => EditLenderProduct::class,
    ])
        ->assertCanSeeTableRecords($lenderProduct->auditLogs)
        ->assertTableActionDoesNotExist('create')
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete');
});
