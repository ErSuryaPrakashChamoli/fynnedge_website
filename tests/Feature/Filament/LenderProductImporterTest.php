<?php

use App\Filament\Imports\LenderProductImporter;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LoanProduct;
use App\Models\User;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;

/**
 * Drives the Importer pipeline directly (as Filament's own import job does)
 * rather than through the multi-step upload/column-mapping UI wizard, which
 * isn't practical to exercise in a feature test.
 */
function runLenderProductImportRow(array $row): void
{
    $import = Import::create([
        'file_name' => 'lenders.csv',
        'file_path' => 'imports/lenders.csv',
        'importer' => LenderProductImporter::class,
        'total_rows' => 1,
        'user_id' => User::factory()->create()->id,
    ]);

    $columnMap = array_combine(array_keys($row), array_keys($row));

    $importer = new LenderProductImporter($import, $columnMap, []);
    $importer($row);
}

it('creates a new lender and lender product from a CSV row', function () {
    $loanProduct = LoanProduct::factory()->create(['slug' => 'import-test-product']);

    runLenderProductImportRow([
        'lender_name' => 'Brand New Bank',
        'lender_type' => 'bank',
        'loan_product_slug' => 'import-test-product',
        'min_amount' => '50000',
        'max_amount' => '1000000',
        'processing_fee_percent_min' => '1',
        'processing_fee_percent_max' => '2',
        'min_credit_score' => '650',
        'employment_types' => 'salaried|self-employed',
    ]);

    $lender = Lender::query()->where('slug', 'brand-new-bank')->first();
    expect($lender)->not->toBeNull();
    expect($lender->type->value)->toBe('bank');

    $offer = LenderProduct::query()
        ->where('lender_id', $lender->id)
        ->where('loan_product_id', $loanProduct->id)
        ->first();

    expect($offer)->not->toBeNull();
    expect((float) $offer->min_amount)->toBe(50000.0);
    expect((float) $offer->processing_fee_percent_min)->toBe(1.0);
    expect((float) $offer->processing_fee_percent_max)->toBe(2.0);
    expect($offer->min_credit_score)->toBe(650);
    expect($offer->employment_types)->toBe(['salaried', 'self-employed']);
});

it('updates the existing offer on re-import instead of duplicating it', function () {
    $lender = Lender::factory()->create(['name' => 'Existing Bank', 'slug' => 'existing-bank']);
    $loanProduct = LoanProduct::factory()->create(['slug' => 'reimport-test-product']);
    LenderProduct::factory()->for($lender)->for($loanProduct, 'loanProduct')->create([
        'min_credit_score' => 600,
    ]);

    runLenderProductImportRow([
        'lender_name' => 'Existing Bank',
        'loan_product_slug' => 'reimport-test-product',
        'min_credit_score' => '750',
    ]);

    expect(LenderProduct::query()
        ->where('lender_id', $lender->id)
        ->where('loan_product_id', $loanProduct->id)
        ->count())->toBe(1);

    expect(LenderProduct::query()
        ->where('lender_id', $lender->id)
        ->where('loan_product_id', $loanProduct->id)
        ->value('min_credit_score'))->toBe(750);
});

it('imports a flat processing fee range with GST flagged', function () {
    $loanProduct = LoanProduct::factory()->create(['slug' => 'flat-fee-import-product']);

    runLenderProductImportRow([
        'lender_name' => 'Flat Fee Bank',
        'loan_product_slug' => 'flat-fee-import-product',
        'processing_fee_flat_amount_min' => '500',
        'processing_fee_flat_amount_max' => '999',
        'processing_fee_gst_extra' => '1',
    ]);

    $offer = LenderProduct::query()
        ->whereHas('lender', fn ($query) => $query->where('slug', 'flat-fee-bank'))
        ->where('loan_product_id', $loanProduct->id)
        ->first();

    expect($offer)->not->toBeNull();
    expect((float) $offer->processing_fee_flat_amount_min)->toBe(500.0);
    expect((float) $offer->processing_fee_flat_amount_max)->toBe(999.0);
    expect($offer->processing_fee_gst_extra)->toBeTrue();
    expect($offer->processingFeeDisplay())->toBe('₹500–₹999 + GST');
});

it('fails the row when the loan product slug does not exist', function () {
    runLenderProductImportRow([
        'lender_name' => 'Nowhere Bank',
        'loan_product_slug' => 'does-not-exist',
    ]);
})->throws(RowImportFailedException::class);
