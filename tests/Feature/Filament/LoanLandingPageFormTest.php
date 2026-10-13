<?php

use App\Enums\LandingPageGroup;
use App\Enums\PublishStatus;
use App\Filament\Resources\LoanLandingPages\Pages\CreateLoanLandingPage;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('requires a city for a location page', function () {
    $product = LoanProduct::factory()->create();

    Livewire::test(CreateLoanLandingPage::class)
        ->fillForm([
            'loan_product_id' => $product->id,
            'group' => LandingPageGroup::ByLocation->value,
            'title' => 'Personal Loan in Delhi',
            'slug' => 'personal-loan-in-delhi',
            'status' => PublishStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['city' => 'required']);

    expect(LoanLandingPage::query()->count())->toBe(0);
});

it('saves the city of a location page', function () {
    $product = LoanProduct::factory()->create();

    Livewire::test(CreateLoanLandingPage::class)
        ->fillForm([
            'loan_product_id' => $product->id,
            'group' => LandingPageGroup::ByLocation->value,
            'title' => 'Personal Loan in Delhi',
            'slug' => 'personal-loan-in-delhi',
            'city' => 'Delhi',
            'status' => PublishStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(LoanLandingPage::query()->sole()->city)->toBe('Delhi');
});

it('shows the amount field only on an amount page and the city field only on a location page', function (LandingPageGroup $group, string $visibleField, string $hiddenField) {
    Livewire::test(CreateLoanLandingPage::class)
        ->fillForm(['group' => $group->value])
        ->assertFormFieldVisible($visibleField)
        ->assertFormFieldHidden($hiddenField);
})->with([
    'amount page' => [LandingPageGroup::ByAmount, 'amount', 'city'],
    'location page' => [LandingPageGroup::ByLocation, 'city', 'amount'],
]);
