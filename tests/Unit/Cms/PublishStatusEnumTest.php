<?php

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;

it('labels every publish status', function () {
    expect(PublishStatus::Draft->getLabel())->toBe('Draft');
    expect(PublishStatus::Published->getLabel())->toBe('Published');
});

it('labels every loan category', function () {
    expect(LoanCategory::PersonalLoan->getLabel())->toBe('Personal Loan');
    expect(LoanCategory::HomeLoan->getLabel())->toBe('Home Loan');
    expect(LoanCategory::LoanAgainstProperty->getLabel())->toBe('Loan Against Property');
    expect(LoanCategory::BusinessLoan->getLabel())->toBe('Business Loan');
    expect(LoanCategory::CreditCard->getLabel())->toBe('Credit Card');
});
