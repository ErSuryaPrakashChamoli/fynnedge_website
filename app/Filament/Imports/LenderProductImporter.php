<?php

namespace App\Filament\Imports;

use App\Enums\EmploymentType;
use App\Enums\LenderStatus;
use App\Enums\LenderType;
use App\Enums\RateStatus;
use App\Enums\RateUnit;
use App\Enums\RateVerificationStatus;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LenderProductRate;
use App\Models\LoanProduct;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class LenderProductImporter extends Importer
{
    protected static ?string $model = LenderProduct::class;

    /**
     * Files up to this many rows are imported inside the upload request
     * instead of on the queue. Processing is quick (a few ms per row), so a
     * typical file finishes in seconds — whereas a queued import only runs
     * when a queue worker is up, and without one it sits at 0 rows forever
     * with no error shown. Larger files still go to the queue, so a very big
     * upload cannot hit the PHP request time limit.
     */
    public const SYNCHRONOUS_ROW_LIMIT = 1000;

    /**
     * Per-chunk lookup caches: a file repeats the same few loan products and
     * lenders on every row, so each slug is queried once per chunk instead of
     * once per row.
     *
     * @var array<string, LoanProduct|null>
     */
    private array $loanProductsBySlug = [];

    /**
     * Every lender keyed by slug, loaded in one query on first use. The table
     * is small (dozens of rows), and a file names the same lenders again and
     * again, so this beats a lookup per row.
     *
     * @var array<string, Lender>|null
     */
    private ?array $lendersBySlug = null;

    /**
     * Existing offers, loaded once per loan product (one query instead of one
     * per row) and keyed by lender id. Offers created during the import are
     * added too, so a lender repeated for the same product updates the row it
     * just created instead of inserting a duplicate.
     *
     * @var array<int, array<int, LenderProduct>>
     */
    private array $offersByLoanProduct = [];

    /**
     * Rate figures already on file per offer (drafts, submitted and live),
     * loaded once per loan product, so a re-upload never duplicates a draft.
     *
     * @var array<int, array<int, list<array{0: float, 1: float|null}>>>
     */
    private array $ratesByLoanProduct = [];

    public function getJobConnection(): ?string
    {
        return $this->import->total_rows <= self::SYNCHRONOUS_ROW_LIMIT ? 'sync' : null;
    }

    public static function getColumns(): array
    {
        return [
            // These three are only used inside resolveRecord() to locate/create
            // the Lender and LoanProduct — they're not columns on LenderProduct
            // itself, so their default "assign this state to the record"
            // behaviour is disabled with a no-op fillRecordUsing().
            ImportColumn::make('lender_name')
                ->requiredMapping()
                ->rules(['required', 'max:255'])
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('lender_type')
                ->rules(['nullable', 'in:bank,nbfc'])
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('loan_product_slug')
                ->requiredMapping()
                ->rules(['required', 'max:255'])
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('status')
                ->rules(['nullable', 'in:active,inactive']),
            ImportColumn::make('min_amount')->numeric()->rules(['nullable', 'numeric']),
            ImportColumn::make('max_amount')->numeric()->rules(['nullable', 'numeric']),
            ImportColumn::make('min_tenure_months')->integer()->rules(['nullable', 'integer']),
            ImportColumn::make('max_tenure_months')->integer()->rules(['nullable', 'integer']),
            // Rates are never written onto the offer: a rate in the file becomes
            // an unverified draft in Catalog → Interest Rates (afterSave()), which
            // must be evidenced and approved before the website shows it.
            ImportColumn::make('interest_rate_from')->numeric()->rules(['nullable', 'numeric', 'gt:0', 'max:60'])->fillRecordUsing(fn () => null),
            ImportColumn::make('interest_rate_to')->numeric()->rules(['nullable', 'numeric', 'gt:0', 'max:60'])->fillRecordUsing(fn () => null),
            ImportColumn::make('processing_fee_flat_amount_min')->numeric()->rules(['nullable', 'numeric']),
            ImportColumn::make('processing_fee_flat_amount_max')->numeric()->rules(['nullable', 'numeric']),
            ImportColumn::make('processing_fee_percent_min')->numeric()->rules(['nullable', 'numeric']),
            ImportColumn::make('processing_fee_percent_max')->numeric()->rules(['nullable', 'numeric']),
            ImportColumn::make('processing_fee_gst_extra')
                ->label('GST extra? (yes/no)')
                ->boolean()
                ->rules(['nullable', 'boolean']),
            ImportColumn::make('processing_fee_note')->rules(['nullable', 'max:255']),
            ImportColumn::make('min_age')->integer()->rules(['nullable', 'integer']),
            ImportColumn::make('max_age')->integer()->rules(['nullable', 'integer']),
            ImportColumn::make('min_credit_score')->integer()->rules(['nullable', 'integer']),
            ImportColumn::make('min_monthly_income')->numeric()->rules(['nullable', 'numeric']),
            ImportColumn::make('min_employment_vintage_months')->integer()->rules(['nullable', 'integer']),
            ImportColumn::make('employment_types')
                ->array(separator: '|')
                ->fillRecordUsing(function (LenderProduct $record, ?array $state): void {
                    $record->employment_types = collect($state)
                        ->map(fn (string $value) => EmploymentType::tryFrom(trim($value))?->value)
                        ->filter()
                        ->values()
                        ->all();
                }),
        ];
    }

    /**
     * One CSV row is one (lender × loan product) combination. The loan
     * product must already exist (resolved strictly by slug); the lender is
     * looked up by slug and only created when genuinely new — an existing
     * lender's name/type/logo/branding is never overwritten from a CSV row,
     * that stays admin-managed via the Lenders resource.
     */
    public function resolveRecord(): LenderProduct
    {
        $loanProductSlug = $this->data['loan_product_slug'];

        $loanProduct = array_key_exists($loanProductSlug, $this->loanProductsBySlug)
            ? $this->loanProductsBySlug[$loanProductSlug]
            : $this->loanProductsBySlug[$loanProductSlug] = LoanProduct::query()->where('slug', $loanProductSlug)->first();

        if (! $loanProduct) {
            throw new RowImportFailedException(
                "No loan product found with slug '{$this->data['loan_product_slug']}'.",
            );
        }

        $lenderSlug = Str::slug($this->data['lender_name']);

        $this->lendersBySlug ??= Lender::query()->get()->keyBy('slug')->all();

        $lender = $this->lendersBySlug[$lenderSlug] ??= Lender::query()->firstOrCreate(
            ['slug' => $lenderSlug],
            [
                'name' => $this->data['lender_name'],
                'type' => LenderType::tryFrom($this->data['lender_type'] ?? ''),
                'status' => LenderStatus::Active,
            ],
        );

        $this->offersByLoanProduct[$loanProduct->id] ??= LenderProduct::query()
            ->where('loan_product_id', $loanProduct->id)
            ->get()
            ->keyBy('lender_id')
            ->all();

        return $this->offersByLoanProduct[$loanProduct->id][$lender->id] ??= new LenderProduct([
            'lender_id' => $lender->id,
            'loan_product_id' => $loanProduct->id,
        ]);
    }

    /**
     * Turns a rate in the uploaded row into an unverified draft rate for the
     * offer — unless the offer already has a draft or live rate with the same
     * figures, so re-uploading a file does not pile up duplicates. Existing
     * rates are loaded once per loan product per chunk, like the offers.
     */
    protected function afterSave(): void
    {
        $rateFrom = $this->data['interest_rate_from'] ?? null;

        if (! is_numeric($rateFrom)) {
            return;
        }

        $rateTo = is_numeric($this->data['interest_rate_to'] ?? null) ? round((float) $this->data['interest_rate_to'], 2) : null;
        $rateFrom = round((float) $rateFrom, 2);

        $loanProductId = $this->record->loan_product_id;

        $this->ratesByLoanProduct[$loanProductId] ??= LenderProductRate::query()
            ->whereIn('lender_product_id', LenderProduct::query()->select('id')->where('loan_product_id', $loanProductId))
            ->whereIn('status', [RateStatus::Draft, RateStatus::PendingApproval, RateStatus::Published])
            ->get(['lender_product_id', 'rate_min', 'rate_max'])
            ->groupBy('lender_product_id')
            ->map(fn ($rates) => $rates->map(fn (LenderProductRate $rate) => [(float) $rate->rate_min, $rate->rate_max !== null ? (float) $rate->rate_max : null])->all())
            ->all();

        $known = $this->ratesByLoanProduct[$loanProductId][$this->record->id] ?? [];

        if (in_array([$rateFrom, $rateTo], $known, true)) {
            return;
        }

        LenderProductRate::query()->create([
            'lender_product_id' => $this->record->id,
            'rate_min' => $rateFrom,
            'rate_max' => $rateTo,
            'rate_unit' => ($this->loanProductsBySlug[$this->data['loan_product_slug']] ?? null)?->category?->rateUnit() ?? RateUnit::PerAnnum,
            'source_notes' => "Uploaded in a Lender Offers CSV import on {$this->import->created_at?->toDateString()}. Add the lender's official source and verify before submitting.",
            'verification_status' => RateVerificationStatus::Unverified,
            'status' => RateStatus::Draft,
        ]);

        $this->ratesByLoanProduct[$loanProductId][$this->record->id][] = [$rateFrom, $rateTo];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your lender offer import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
