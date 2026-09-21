<?php

use App\Filament\Imports\LenderProductImporter;
use App\Filament\Resources\LenderProducts\Pages\ListLenderProducts;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LoanProduct;
use App\Models\User;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

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

/**
 * The columns the uploaded test file carries. The import form rejects a
 * column map that points at a header the file does not have.
 *
 * @return array<int, string>
 */
function lenderProductCsvHeaders(): array
{
    return ['lender_name', 'lender_type', 'loan_product_slug', 'status', 'min_amount', 'max_amount'];
}

function lenderProductCsv(int $rows, string $loanProductSlug): UploadedFile
{
    $lines = [implode(',', lenderProductCsvHeaders())];

    for ($i = 1; $i <= $rows; $i++) {
        $lines[] = implode(',', ["Upload Bank {$i}", 'bank', $loanProductSlug, 'active', '100000', '5000000']);
    }

    return UploadedFile::fake()->createWithContent('lender-products.csv', implode("\n", $lines)."\n");
}

it('imports an uploaded file straight away, even with no queue worker running', function () {
    config()->set('queue.default', 'database');
    LoanProduct::factory()->create(['slug' => 'upload-now-product']);

    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire\Livewire::test(ListLenderProducts::class)
        ->callAction('import', data: [
            'file' => lenderProductCsv(3, 'upload-now-product'),
            'columnMap' => array_combine(lenderProductCsvHeaders(), lenderProductCsvHeaders()),
        ])
        ->assertHasNoActionErrors();

    $import = Import::query()->sole();

    expect($import->processed_rows)->toBe(3);
    expect($import->successful_rows)->toBe(3);
    expect($import->completed_at)->not->toBeNull();
    expect(LenderProduct::query()->count())->toBe(3);
    expect(DB::table('jobs')->count())->toBe(0);
});

it('runs files up to the row limit in the request and leaves bigger ones to the queue', function (int $totalRows, ?string $connection) {
    $import = Import::create([
        'file_name' => 'lenders.csv',
        'file_path' => 'imports/lenders.csv',
        'importer' => LenderProductImporter::class,
        'total_rows' => $totalRows,
        'user_id' => User::factory()->create()->id,
    ]);

    expect((new LenderProductImporter($import, [], []))->getJobConnection())->toBe($connection);
})->with([
    'a typical file' => [351, 'sync'],
    'exactly at the limit' => [LenderProductImporter::SYNCHRONOUS_ROW_LIMIT, 'sync'],
    'one row over' => [LenderProductImporter::SYNCHRONOUS_ROW_LIMIT + 1, null],
]);

it('keeps one offer when a file lists the same lender and product twice, with the later row winning', function () {
    config()->set('queue.default', 'database');
    LoanProduct::factory()->create(['slug' => 'duplicate-row-product']);

    $csv = implode("\n", [
        implode(',', lenderProductCsvHeaders()),
        'Repeat Bank,bank,duplicate-row-product,active,100000,5000000',
        'Repeat Bank,bank,duplicate-row-product,active,200000,9000000',
    ])."\n";

    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire\Livewire::test(ListLenderProducts::class)
        ->callAction('import', data: [
            'file' => UploadedFile::fake()->createWithContent('lender-products.csv', $csv),
            'columnMap' => array_combine(lenderProductCsvHeaders(), lenderProductCsvHeaders()),
        ])
        ->assertHasNoActionErrors();

    $offer = LenderProduct::query()->sole();

    expect((float) $offer->min_amount)->toBe(200000.0);
    expect((float) $offer->max_amount)->toBe(9000000.0);
    expect(Lender::query()->where('slug', 'repeat-bank')->count())->toBe(1);
});
