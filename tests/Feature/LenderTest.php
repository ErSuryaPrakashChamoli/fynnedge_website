<?php

use App\Enums\EmploymentType;
use App\Models\Lender;
use App\Models\LenderProduct;
use Illuminate\Support\Facades\Storage;

it('has no logo url when no logo has been uploaded', function () {
    $lender = Lender::factory()->create(['logo_path' => null]);

    expect($lender->logoUrl())->toBeNull();
});

it('resolves a logo url from the public disk when a logo is set', function () {
    Storage::fake('public');
    Storage::disk('public')->put('lenders/demo-logo.png', 'fake-image-content');

    $lender = Lender::factory()->create(['logo_path' => 'lenders/demo-logo.png']);

    expect($lender->logoUrl())->toBe(Storage::disk('public')->url('lenders/demo-logo.png'));
    expect($lender->logoUrl())->toContain('/storage/lenders/demo-logo.png');
});

it('displays a processing fee range when both bounds are set and differ', function () {
    $offer = LenderProduct::factory()->create([
        'processing_fee_percent_min' => 1,
        'processing_fee_percent_max' => 3,
        'processing_fee_gst_extra' => false,
    ]);

    expect($offer->processingFeeDisplay())->toBe('1.00%–3.00%');
});

it('displays a single processing fee value when both bounds are equal', function () {
    $offer = LenderProduct::factory()->create([
        'processing_fee_percent_min' => 2,
        'processing_fee_percent_max' => 2,
        'processing_fee_gst_extra' => false,
    ]);

    expect($offer->processingFeeDisplay())->toBe('2.00%');
});

it('displays "up to" when only the max processing fee bound is set', function () {
    $offer = LenderProduct::factory()->create([
        'processing_fee_percent_min' => null,
        'processing_fee_percent_max' => 2.5,
        'processing_fee_gst_extra' => false,
    ]);

    expect($offer->processingFeeDisplay())->toBe('Up to 2.50%');
});

it('displays "from" when only the min processing fee bound is set', function () {
    $offer = LenderProduct::factory()->create([
        'processing_fee_percent_min' => 1.25,
        'processing_fee_percent_max' => null,
        'processing_fee_gst_extra' => false,
    ]);

    expect($offer->processingFeeDisplay())->toBe('From 1.25%');
});

it('returns no processing fee display when nothing is set', function () {
    $offer = LenderProduct::factory()->create([
        'processing_fee_percent_min' => null,
        'processing_fee_percent_max' => null,
        'processing_fee_flat_amount_min' => null,
        'processing_fee_flat_amount_max' => null,
    ]);

    expect($offer->processingFeeDisplay())->toBeNull();
});

it('appends GST when the fee is flagged as GST-extra', function () {
    $offer = LenderProduct::factory()->create([
        'processing_fee_percent_min' => 2,
        'processing_fee_percent_max' => 2,
        'processing_fee_gst_extra' => true,
    ]);

    expect($offer->processingFeeDisplay())->toBe('2.00% + GST');
});

it('does not append GST when a fee is set but GST-extra is false', function () {
    $offer = LenderProduct::factory()->create([
        'processing_fee_percent_min' => 2,
        'processing_fee_percent_max' => 2,
        'processing_fee_gst_extra' => false,
    ]);

    expect($offer->processingFeeDisplay())->toBe('2.00%');
});

it('displays a single flat fee amount, taking precedence over a percentage', function () {
    $offer = LenderProduct::factory()->create([
        'processing_fee_flat_amount_min' => 2500,
        'processing_fee_flat_amount_max' => 2500,
        'processing_fee_percent_min' => 1,
        'processing_fee_percent_max' => 3,
        'processing_fee_gst_extra' => false,
    ]);

    expect($offer->processingFeeDisplay())->toBe('₹2,500');
});

it('displays a flat fee range when both bounds are set and differ', function () {
    $offer = LenderProduct::factory()->create([
        'processing_fee_flat_amount_min' => 500,
        'processing_fee_flat_amount_max' => 2000,
        'processing_fee_gst_extra' => false,
    ]);

    expect($offer->processingFeeDisplay())->toBe('₹500–₹2,000');
});

it('displays a flat fee amount with GST appended', function () {
    $offer = LenderProduct::factory()->create([
        'processing_fee_flat_amount_min' => 999,
        'processing_fee_flat_amount_max' => 999,
        'processing_fee_gst_extra' => true,
    ]);

    expect($offer->processingFeeDisplay())->toBe('₹999 + GST');
});

it('returns no eligibility summary points when nothing is set', function () {
    $offer = LenderProduct::factory()->create([
        'min_age' => null,
        'max_age' => null,
        'min_credit_score' => null,
        'min_monthly_income' => null,
        'min_employment_vintage_months' => null,
        'employment_types' => null,
    ]);

    expect($offer->eligibilitySummaryPoints())->toBe([]);
});

it('builds eligibility summary points from whichever fields are set', function () {
    $offer = LenderProduct::factory()->create([
        'min_age' => 21,
        'max_age' => 58,
        'min_credit_score' => 700,
        'min_monthly_income' => 25000,
        'min_employment_vintage_months' => 12,
        'employment_types' => [EmploymentType::Salaried->value, EmploymentType::SelfEmployed->value],
    ]);

    expect($offer->eligibilitySummaryPoints())->toBe([
        'Age 21–58 yrs',
        'Min. income ₹25,000/mo',
        'Credit score 700+',
        '12+ months in current employment/business',
        'Salaried or Self-employed',
    ]);
});

it('only includes the partial fields that are set', function () {
    $offer = LenderProduct::factory()->create([
        'min_age' => null,
        'max_age' => null,
        'min_credit_score' => 650,
        'min_monthly_income' => null,
        'min_employment_vintage_months' => null,
        'employment_types' => null,
    ]);

    expect($offer->eligibilitySummaryPoints())->toBe(['Credit score 650+']);
});
