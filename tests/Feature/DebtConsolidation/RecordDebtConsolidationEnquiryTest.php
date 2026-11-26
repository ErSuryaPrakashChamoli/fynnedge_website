<?php

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Models\ContactEnquiry;
use App\Models\LoanProduct;
use App\Modules\Enquiries\Actions\RecordEnquiry;
use App\Modules\Enquiries\DataTransferObjects\EnquiryDraft;
use App\Modules\Enquiries\Enums\EnquiryOutcome;

function debtEnquiry(string $phone = '9876543210'): EnquiryDraft
{
    return new EnquiryDraft(phone: $phone, type: EnquiryType::DebtConsolidation, enquirySource: 'Debt Consolidation', name: 'Asha', phoneVerified: true);
}

it('records a debt consolidation enquiry separately from an open loan enquiry by the same number', function () {
    $loanEnquiry = ContactEnquiry::factory()->create([
        'phone' => '9876543210',
        'enquiry_type' => EnquiryType::LoanEnquiry,
        'loan_product_id' => LoanProduct::factory(),
        'status' => EnquiryStatus::New,
    ]);

    $outcome = app(RecordEnquiry::class)->handle(debtEnquiry());

    expect($outcome)->toBe(EnquiryOutcome::Created)
        ->and($loanEnquiry->fresh()->enquiry_count)->toBe($loanEnquiry->enquiry_count)
        ->and(ContactEnquiry::query()->where('enquiry_type', EnquiryType::DebtConsolidation)->sole())
        ->phone->toBe('9876543210')
        ->enquiry_source->toBe('Debt Consolidation')
        ->phone_verified_at->not->toBeNull();
});

it('counts a repeat debt consolidation enquiry on the open one', function () {
    app(RecordEnquiry::class)->handle(debtEnquiry());

    $outcome = app(RecordEnquiry::class)->handle(debtEnquiry());

    expect($outcome)->toBe(EnquiryOutcome::Duplicate)
        ->and(ContactEnquiry::query()->sole()->enquiry_count)->toBe(2);
});
