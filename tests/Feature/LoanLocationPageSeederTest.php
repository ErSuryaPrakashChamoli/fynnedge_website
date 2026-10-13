<?php

use App\Enums\LandingPageGroup;
use App\Enums\LoanCategory;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use Database\Seeders\LoanLocationPageSeeder;

it('seeds a published city page for each of the 24 cities on a loan product that already has sub-pages', function () {
    $personalLoan = LoanProduct::factory()->create(['name' => 'Personal Loan', 'slug' => 'personal-loan', 'category' => LoanCategory::PersonalLoan]);
    LoanLandingPage::factory()->published()->for($personalLoan, 'loanProduct')->create(['group' => LandingPageGroup::ByNeed]);
    LoanProduct::factory()->create(['category' => LoanCategory::HomeLoan]);

    $this->seed(LoanLocationPageSeeder::class);

    $locationPages = LoanLandingPage::query()->where('group', LandingPageGroup::ByLocation)->orderBy('sort_order')->get();
    expect($locationPages)->toHaveCount(24);
    expect($locationPages->pluck('loan_product_id')->unique()->all())->toBe([$personalLoan->id]);
    expect($locationPages->first())
        ->city->toBe('Delhi')
        ->title->toBe('Personal Loan in Delhi')
        ->slug->toBe('personal-loan-in-delhi')
        ->isCurrentlyPublished()->toBeTrue();
    expect($locationPages->last()->city)->toBe('Dehradun');
});

it('leaves the existing amount, type and need pages untouched', function () {
    $personalLoan = LoanProduct::factory()->create(['category' => LoanCategory::PersonalLoan]);
    $needPage = LoanLandingPage::factory()->published()->for($personalLoan, 'loanProduct')->create([
        'group' => LandingPageGroup::ByNeed,
        'body' => '<p>Edited by an admin.</p>',
    ]);

    $this->seed(LoanLocationPageSeeder::class);

    expect($needPage->refresh()->body)->toBe('<p>Edited by an admin.</p>');
});

it('does not duplicate city pages when it runs again', function () {
    $personalLoan = LoanProduct::factory()->create(['category' => LoanCategory::PersonalLoan]);
    LoanLandingPage::factory()->published()->for($personalLoan, 'loanProduct')->create(['group' => LandingPageGroup::ByNeed]);
    $this->seed(LoanLocationPageSeeder::class);

    $this->seed(LoanLocationPageSeeder::class);

    expect(LoanLandingPage::query()->where('group', LandingPageGroup::ByLocation)->count())->toBe(24);
});
