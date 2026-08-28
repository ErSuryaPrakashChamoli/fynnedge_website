<?php

use App\Enums\LoanCategory;
use App\Filament\Resources\LoanProducts\Pages\CreateLoanProduct;
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
