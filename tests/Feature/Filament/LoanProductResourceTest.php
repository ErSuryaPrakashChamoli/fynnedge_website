<?php

use App\Enums\LoanCategory;
use App\Filament\RelationManagers\AuditLogsRelationManager;
use App\Filament\Resources\LoanProducts\Pages\CreateLoanProduct;
use App\Filament\Resources\LoanProducts\Pages\EditLoanProduct;
use App\Filament\Resources\LoanProducts\Pages\ListLoanProducts;
use App\Models\LoanProduct;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('lists loan products', function () {
    LoanProduct::factory()->count(3)->create();

    Livewire::test(ListLoanProducts::class)
        ->assertSuccessful();
});

it('creates a loan product with a slug and category', function () {
    Livewire::test(CreateLoanProduct::class)
        ->fillForm([
            'name' => 'Business Loan',
            'slug' => 'business-loan',
            'category' => LoanCategory::BusinessLoan->value,
            'summary' => 'Working capital for growing businesses.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(LoanProduct::query()->where('slug', 'business-loan')->exists())->toBeTrue();
});

it('requires a unique slug', function () {
    LoanProduct::factory()->create(['slug' => 'personal-loan']);

    Livewire::test(CreateLoanProduct::class)
        ->fillForm([
            'name' => 'Personal Loan Duplicate',
            'slug' => 'personal-loan',
            'category' => LoanCategory::PersonalLoan->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['slug']);
});

it('lets an admin set marketing content on a loan product without touching its calculator/eligibility fields', function () {
    $loanProduct = LoanProduct::factory()->create([
        'min_amount' => 50000,
        'max_amount' => 500000,
    ]);

    Livewire::test(EditLoanProduct::class, ['record' => $loanProduct->getRouteKey()])
        ->fillForm([
            'marketing_headline' => "India's fastest personal loan approval",
            'benefits' => ['Same-day disbursal', 'No hidden charges'],
            'cta_label' => 'Get Started Now',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $loanProduct->refresh();
    expect($loanProduct->marketing_headline)->toBe("India's fastest personal loan approval");
    expect($loanProduct->benefits)->toBe(['Same-day disbursal', 'No hidden charges']);
    expect($loanProduct->cta_label)->toBe('Get Started Now');
    expect((float) $loanProduct->min_amount)->toBe(50000.0);
    expect((float) $loanProduct->max_amount)->toBe(500000.0);
});

it('records and shows an audit trail when a loan product changes its limits', function () {
    $loanProduct = LoanProduct::factory()->create(['max_amount' => 2000000, 'max_tenure_months' => 60]);

    $loanProduct->update(['max_amount' => 3000000, 'max_tenure_months' => 84]);

    $log = $loanProduct->auditLogs()->where('action', 'updated')->sole();
    expect($log->changes)->toHaveKeys(['max_amount', 'max_tenure_months']);

    Livewire::test(AuditLogsRelationManager::class, [
        'ownerRecord' => $loanProduct,
        'pageClass' => EditLoanProduct::class,
    ])
        ->assertCanSeeTableRecords($loanProduct->auditLogs);
});
