<?php

use App\Enums\LoanCategory;
use App\Enums\RateSourceType;
use App\Enums\RateStatus;
use App\Enums\RateVerificationStatus;
use App\Filament\Resources\LenderProductRates\Pages\ListLenderProductRates;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LenderProductRate;
use App\Models\LoanProduct;
use App\Models\User;
use App\Support\Rates\RateBook;
use App\Support\Rates\RateRoles;
use App\Support\Rates\RateVerificationSheet;
use Filament\Actions\Imports\Models\FailedImportRow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * @param  list<array<string, mixed>>  $rows
 */
function verifiedRatesSheet(array $rows): UploadedFile
{
    $lines = [implode(',', RateVerificationSheet::COLUMNS)];

    foreach ($rows as $row) {
        $lines[] = collect(RateVerificationSheet::COLUMNS)
            ->map(fn (string $column) => '"'.str_replace('"', '""', (string) ($row[$column] ?? '')).'"')
            ->implode(',');
    }

    return UploadedFile::fake()->createWithContent('interest-rates-to-verify.csv', implode("\n", $lines)."\n");
}

/**
 * @param  list<array<string, mixed>>  $rows
 */
function uploadVerifiedRates(array $rows, array $options = ['confirmed' => true, 'publish' => true]): void
{
    Livewire::test(ListLenderProductRates::class, ['activeTab' => 'all'])
        ->callAction('uploadVerifiedRates', data: [
            'file' => verifiedRatesSheet($rows),
            'columnMap' => array_combine(RateVerificationSheet::COLUMNS, RateVerificationSheet::COLUMNS),
            ...$options,
        ])
        ->assertHasNoActionErrors();
}

/**
 * @return array<string, mixed>
 */
function evidenceRow(array $overrides = []): array
{
    return [
        'lender' => 'Upload Bank',
        'loan_type' => 'personal-loan',
        'rate_unit' => 'per_annum',
        'rate_min' => '10.75',
        'rate_max' => '21.50',
        'source_url' => 'https://www.upload-bank.example/personal-loan/rates',
        'source_name' => 'Upload Bank — Personal loan interest rates',
        'source_quote' => 'Interest rate: 10.75% to 21.50% p.a.',
        'checked_on' => now()->toDateString(),
        ...$overrides,
    ];
}

beforeEach(function () {
    config()->set('queue.default', 'database');
    $this->product = LoanProduct::factory()->published()->create(['category' => LoanCategory::PersonalLoan, 'slug' => 'personal-loans', 'name' => 'Personal Loans']);
    $this->offer = LenderProduct::factory()->for($this->product, 'loanProduct')->for(Lender::factory()->state(['name' => 'Upload Bank']))->create();
});

it('downloads the rates in view as a sheet whose columns the upload reads', function () {
    $this->actingAs(tap(User::factory()->create(['is_admin' => true]))->syncRoles([RateRoles::EDITOR_ROLE]));
    $draft = LenderProductRate::factory()->unverified()->for($this->offer, 'lenderProduct')->create(['rate_min' => 12]);

    ob_start();
    RateVerificationSheet::download(LenderProductRate::query())->sendContent();
    $csv = ob_get_clean();

    expect($csv)
        ->toStartWith(implode(',', RateVerificationSheet::COLUMNS))
        ->toContain($draft->public_id.',"Upload Bank",personal-loans');
});

it('verifies and publishes every uploaded row for a publisher, by reference or by lender and loan type', function () {
    $publisher = tap(User::factory()->create(['is_admin' => true]))->syncRoles([RateRoles::PUBLISHER_ROLE]);
    $this->actingAs($publisher);
    $placeholder = LenderProductRate::factory()->unverified()->for($this->offer, 'lenderProduct')->create(['rate_min' => 12, 'source_notes' => 'Pre-audit figure.']);
    $otherOffer = LenderProduct::factory()->for($this->product, 'loanProduct')->for(Lender::factory()->state(['name' => 'Second Bank']))->create();
    $pending = LenderProductRate::factory()->pendingApproval()->for($otherOffer, 'lenderProduct')->create(['rate_min' => 11]);

    uploadVerifiedRates([
        evidenceRow(),
        evidenceRow(['reference' => $pending->public_id, 'lender' => 'Second Bank', 'rate_min' => '11.25', 'rate_max' => '', 'source_quote' => 'Starting at 11.25% p.a.']),
    ]);

    expect($placeholder->fresh())
        ->status->toBe(RateStatus::Published)
        ->verification_status->toBe(RateVerificationStatus::Verified)
        ->rate_min->toBe('10.75')
        ->published_by->toBe($publisher->id)
        ->source_notes->toStartWith('Checked on '.now()->toDateString().' by '.$publisher->name.' (bulk upload). Source: Upload Bank — Personal loan interest rates. "Interest rate: 10.75% to 21.50% p.a."')
        ->and($pending->fresh())->status->toBe(RateStatus::Published)->rate_min->toBe('11.25')->rate_max->toBeNull()
        ->and(RateBook::headlineFor($this->product)->startingLabel())->toBe('10.75%');
});

it('publishes a row read on a comparison site as third-party, named after that site', function () {
    $this->actingAs(tap(User::factory()->create(['is_admin' => true]))->syncRoles([RateRoles::PUBLISHER_ROLE]));
    $draft = LenderProductRate::factory()->unverified()->for($this->offer, 'lenderProduct')->create(['rate_min' => 12]);

    uploadVerifiedRates([
        evidenceRow(['source_type' => 'third_party', 'source_name' => 'Paisabazaar', 'source_url' => 'https://www.paisabazaar.com/upload-bank/']),
        evidenceRow(['lender' => 'Upload Bank', 'variant' => 'Unnamed site', 'source_type' => 'third_party', 'source_name' => '']),
    ]);

    expect($draft->fresh())
        ->status->toBe(RateStatus::Published)
        ->source_type->toBe(RateSourceType::ThirdParty)
        ->source_name->toBe('Paisabazaar')
        ->needsReverification()->toBeTrue()
        ->and(RateBook::headlineFor($this->product))->toBeNull()
        ->and(FailedImportRow::query()->sole()->validation_error)->toContain('Name the third-party site');
});

it('makes a third-party rate official when a re-check on the lender\'s site leaves the source type blank', function () {
    $this->actingAs(tap(User::factory()->create(['is_admin' => true]))->syncRoles([RateRoles::PUBLISHER_ROLE]));
    $listed = publishRate($this->offer, 12.0, null, ['source_type' => RateSourceType::ThirdParty, 'source_name' => 'Paisabazaar']);

    uploadVerifiedRates([collect(evidenceRow(['reference' => $listed->public_id]))->except('source_type')->all()]);

    expect($listed->fresh()->status)->toBe(RateStatus::Archived)
        ->and(LenderProductRate::query()->published()->sole())
        ->source_type->toBe(RateSourceType::Official)
        ->rate_min->toBe('10.75');
});

it('publishes an uploaded figure for a live rate as a new version, keeping the old one in the history', function () {
    $this->actingAs(tap(User::factory()->create(['is_admin' => true]))->syncRoles([RateRoles::PUBLISHER_ROLE]));
    $live = LenderProductRate::factory()->published()->for($this->offer, 'lenderProduct')->create(['rate_min' => 10.5]);

    uploadVerifiedRates([evidenceRow(['rate_min' => '10.90', 'rate_max' => ''])]);

    $current = LenderProductRate::query()->where('series_id', $live->series_id)->where('status', RateStatus::Published)->sole();
    expect($current)->version->toBe(2)->rate_min->toBe('10.90')
        ->and($live->fresh()->status)->toBe(RateStatus::Archived);
});

it('skips rows without proper evidence and saves the rest', function () {
    $this->actingAs(tap(User::factory()->create(['is_admin' => true]))->syncRoles([RateRoles::PUBLISHER_ROLE]));
    $otherOffer = LenderProduct::factory()->for($this->product, 'loanProduct')->for(Lender::factory()->state(['name' => 'Careless Bank']))->create();

    uploadVerifiedRates([
        evidenceRow(),
        evidenceRow(['lender' => 'Careless Bank', 'source_url' => 'http://www.bankbazaar.example/careless']),
        evidenceRow(['lender' => 'Careless Bank', 'source_quote' => '']),
        evidenceRow(['lender' => 'Careless Bank', 'rate_unit' => 'per_month', 'rate_min' => '1.2', 'rate_max' => '']),
    ]);

    expect(LenderProductRate::query()->where('lender_product_id', $this->offer->id)->sole()->status)->toBe(RateStatus::Published)
        ->and(LenderProductRate::query()->where('lender_product_id', $otherOffer->id)->exists())->toBeFalse()
        ->and(FailedImportRow::query()->pluck('validation_error')->implode(' | '))
        ->toContain('The source URL field must start with one of the following: https://.')
        ->toContain('The quote from the source page field is required.')
        ->toContain('Personal Loan rates are published p.a. — change the rate unit.');
});

it('saves an editor\'s upload as verified drafts for a publisher to approve', function () {
    $this->actingAs(tap(User::factory()->create(['is_admin' => true]))->syncRoles([RateRoles::EDITOR_ROLE]));

    uploadVerifiedRates([evidenceRow()], ['confirmed' => true, 'publish' => true]);

    expect(LenderProductRate::query()->sole())
        ->status->toBe(RateStatus::Draft)
        ->verification_status->toBe(RateVerificationStatus::Verified);
});

it('refuses the upload until the uploader confirms the rows were checked on the lenders\' own sites', function () {
    $this->actingAs(tap(User::factory()->create(['is_admin' => true]))->syncRoles([RateRoles::PUBLISHER_ROLE]));

    Livewire::test(ListLenderProductRates::class, ['activeTab' => 'all'])
        ->callAction('uploadVerifiedRates', data: [
            'file' => verifiedRatesSheet([evidenceRow()]),
            'columnMap' => array_combine(RateVerificationSheet::COLUMNS, RateVerificationSheet::COLUMNS),
            'confirmed' => false,
            'publish' => true,
        ])
        ->assertHasActionErrors(['confirmed']);

    expect(LenderProductRate::query()->count())->toBe(0);
});

it('does not offer the upload to a read-only reviewer', function () {
    $this->actingAs(tap(User::factory()->create(['is_admin' => true]))->syncRoles([RateRoles::REVIEWER_ROLE]));

    Livewire::test(ListLenderProductRates::class)->assertActionHidden('uploadVerifiedRates');
});

it('finds the right rate by lender and loan type when the sheet came from another environment', function () {
    $this->actingAs(tap(User::factory()->create(['is_admin' => true]))->syncRoles([RateRoles::PUBLISHER_ROLE]));
    $placeholder = LenderProductRate::factory()->unverified()->for($this->offer, 'lenderProduct')->create(['rate_min' => 12]);

    uploadVerifiedRates([evidenceRow([
        'reference' => (string) Str::uuid(),
        'variant' => 'Personal Loan (Unsecured) – fixed',
    ])]);

    expect(LenderProductRate::query()->sole())
        ->id->toBe($placeholder->id)
        ->status->toBe(RateStatus::Published)
        ->variant->toBe('Personal Loan (Unsecured) – fixed')
        ->rate_min->toBe('10.75');
});

it('finds the lender\'s offer when another loan product or lender record matches the same names', function () {
    $this->actingAs(tap(User::factory()->create(['is_admin' => true]))->syncRoles([RateRoles::PUBLISHER_ROLE]));
    LoanProduct::factory()->published()->create(['id' => 0, 'category' => LoanCategory::PersonalLoan, 'slug' => 'instant-personal-loan', 'name' => 'Instant Personal Loan']);
    Lender::factory()->create(['name' => 'Upload Bank', 'slug' => 'upload-bank-duplicate']);

    uploadVerifiedRates([evidenceRow(['reference' => (string) Str::uuid()])]);

    expect(LenderProductRate::query()->sole())
        ->lender_product_id->toBe($this->offer->id)
        ->status->toBe(RateStatus::Published);
});
