<?php

namespace App\Filament\Imports;

use App\Enums\RateSourceType;
use App\Enums\RateStatus;
use App\Enums\RateType;
use App\Enums\RateUnit;
use App\Enums\RateVerificationStatus;
use App\Enums\RepaymentMethod;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LenderProductRate;
use App\Models\LoanProduct;
use App\Support\Rates\RatePublicationRules;
use App\Support\Rates\RateWorkflow;
use App\Support\Rates\RateWorkflowException;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * "Upload verified rates": the bulk version of the Verify action. Each row is
 * one lender's rate for one loan type, with its own evidence — the https
 * link, the source's name, the sentence on that page that states the rate,
 * and the date it was checked — so a bulk upload never verifies anything a
 * person did not check. A row is the lender's own source unless its
 * source_type says "third_party" (a comparison site, named in source_name),
 * which the website labels as such and never calls verified. The sheet to fill in comes from "Download verification sheet"
 * (App\Support\Rates\RateVerificationSheet), whose columns match these.
 *
 * A row updates the rate it names (reference column) or else the open draft /
 * submitted rate for that lender, loan type, variant and segment; a live rate
 * gets a new version. Every row must pass RatePublicationRules before anything
 * is written, because Filament runs a chunk of rows in ONE transaction and does
 * not roll back a failed row's partial writes. With "publish" chosen by a
 * publisher, each saved row is then published through RateWorkflow.
 */
class LenderProductRateImporter extends Importer
{
    protected static ?string $model = LenderProductRate::class;

    /**
     * Same as LenderProductImporter: a typical sheet runs inside the upload
     * request, so no queue worker is needed for it to finish.
     */
    public const SYNCHRONOUS_ROW_LIMIT = 1000;

    /**
     * @var array<string, Lender>|null
     */
    private ?array $lenders = null;

    /**
     * @var array<string, LoanProduct|null>
     */
    private array $loanProducts = [];

    private ?LenderProduct $offer = null;

    public function getJobConnection(): ?string
    {
        return $this->import->total_rows <= self::SYNCHRONOUS_ROW_LIMIT ? 'sync' : null;
    }

    public static function getColumns(): array
    {
        $ignore = fn () => null;

        return [
            ImportColumn::make('reference')
                ->label('Reference')
                ->rules(['nullable', 'uuid'])
                ->fillRecordUsing($ignore),
            ImportColumn::make('lender')
                ->requiredMapping()
                ->rules(['required', 'max:255'])
                ->fillRecordUsing($ignore),
            ImportColumn::make('loan_type')
                ->label('Loan type')
                ->requiredMapping()
                ->rules(['required', 'max:255'])
                ->fillRecordUsing($ignore),
            ImportColumn::make('variant')->rules(['nullable', 'max:255']),
            ImportColumn::make('borrower_segment')->rules(['nullable', 'max:255']),
            ImportColumn::make('rate_unit')
                ->rules(['nullable', Rule::enum(RateUnit::class)])
                ->fillRecordUsing(function (LenderProductRate $record, ?string $state): void {
                    $record->rate_unit = RateUnit::tryFrom((string) $state)
                        ?? $record->rate_unit
                        ?? $record->lenderProduct?->loanProduct?->category?->rateUnit()
                        ?? RateUnit::PerAnnum;
                }),
            ImportColumn::make('rate_min')->requiredMapping()->numeric()->rules(['required', 'numeric', 'gt:0']),
            ImportColumn::make('rate_max')->numeric()->rules(['nullable', 'numeric', 'gte:rate_min']),
            ImportColumn::make('rate_type')->rules(['nullable', Rule::enum(RateType::class)]),
            ImportColumn::make('repayment_method')->rules(['nullable', Rule::enum(RepaymentMethod::class)]),
            ImportColumn::make('benchmark')->rules(['nullable', 'max:255']),
            ImportColumn::make('source_url')
                ->label('Source URL')
                ->requiredMapping()
                ->rules(['required', 'url', 'starts_with:https://', 'max:2048']),
            ImportColumn::make('source_type')
                ->label('Source type')
                ->rules(['nullable', Rule::enum(RateSourceType::class)])
                ->fillRecordUsing($ignore),
            ImportColumn::make('source_name')
                ->label('Source name')
                ->rules(['nullable', 'max:255'])
                ->fillRecordUsing(fn (LenderProductRate $record, ?string $state) => $record->source_name = blank($state) ? null : trim($state)),
            ImportColumn::make('source_quote')
                ->label('Quote from the source page')
                ->requiredMapping()
                ->rules(['required', 'max:1000'])
                ->fillRecordUsing($ignore),
            ImportColumn::make('checked_on')
                ->label('Checked on')
                ->requiredMapping()
                ->rules(['required', 'date', 'before_or_equal:'.LenderProductRate::today()->toDateString()])
                ->fillRecordUsing(fn (LenderProductRate $record, string $state) => $record->last_verified_at = $state),
            ImportColumn::make('valid_until')
                ->rules(['nullable', 'date'])
                ->fillRecordUsing(fn (LenderProductRate $record, ?string $state) => $record->effective_until = blank($state) ? null : $state),
            ImportColumn::make('eligibility_notes')->rules(['nullable', 'max:500']),
            ImportColumn::make('disclaimer')->rules(['nullable', 'max:500']),
        ];
    }

    /**
     * @return array<int, Component>
     */
    public static function getOptionsFormComponents(): array
    {
        return [
            Checkbox::make('confirmed')
                ->label('Every row was checked, on the date in its "checked_on" column, on the lender\'s own website — or, only where its source_type is "third_party", on the comparison site named in its source_name column.')
                ->accepted()
                ->validationMessages(['accepted' => 'Confirm where every row was checked.']),
            Toggle::make('publish')
                ->label('Approve & publish each row that passes the checks')
                ->helperText('Otherwise every row is saved as a verified draft for a publisher to approve.')
                ->default(true)
                ->visible(fn (): bool => (bool) Auth::user()?->can('publish', LenderProductRate::class)),
        ];
    }

    /**
     * The rate a row writes into. A reference names one exactly — but
     * references are per database, so a sheet downloaded from one environment
     * (say, local) and uploaded to another (live) falls back to lender + loan
     * type: the rate with the same variant and segment, else the offer's one
     * unverified draft (the rate the sheet was asking someone to verify), else
     * a new rate.
     */
    public function resolveRecord(): LenderProductRate
    {
        if (filled($this->data['reference'] ?? null)) {
            $named = LenderProductRate::query()->with('lenderProduct.loanProduct')->where('public_id', $this->data['reference'])->first();

            if ($named !== null) {
                $this->offer = $named->lenderProduct;

                return $this->editable($named);
            }
        }

        $this->offer = $this->resolveOffer();

        $rates = $this->offer->rates()->where('status', '!=', RateStatus::Archived)->get();

        $sameLine = $rates->filter(fn (LenderProductRate $rate) => self::same($rate->variant, $this->data['variant'] ?? null)
            && self::same($rate->borrower_segment, $this->data['borrower_segment'] ?? null));

        $match = $sameLine->first(fn (LenderProductRate $rate) => $rate->status !== RateStatus::Published) ?? $sameLine->first();

        if ($match === null) {
            $unverifiedDrafts = $rates->filter(fn (LenderProductRate $rate) => $rate->status === RateStatus::Draft
                && $rate->verification_status === RateVerificationStatus::Unverified);

            $match = $unverifiedDrafts->count() === 1 ? $unverifiedDrafts->first() : null;
        }

        if ($match !== null) {
            return $this->editable($match);
        }

        $rate = new LenderProductRate([
            'lender_product_id' => $this->offer->id,
            'status' => RateStatus::Draft,
            'rate_unit' => $this->offer->loanProduct->category->rateUnit(),
        ]);
        $rate->setRelation('lenderProduct', $this->offer);

        return $rate;
    }

    /**
     * Marks the row verified with its evidence and refuses it — before
     * anything is written — unless it would pass every publication check.
     */
    protected function beforeSave(): void
    {
        $record = $this->record;
        $user = $this->import->user;

        $record->setRelation('lenderProduct', $this->offer);
        $record->verification_status = RateVerificationStatus::Verified;
        // Set on every row, so a sheet without the column (an older download)
        // re-checking a third-party rate on the lender's site makes it official.
        $record->source_type = RateSourceType::tryFrom((string) ($this->data['source_type'] ?? '')) ?? RateSourceType::Official;
        $source = filled($this->data['source_name'] ?? null) ? " Source: {$this->data['source_name']}." : '';
        $record->source_notes = "Checked on {$this->data['checked_on']} by {$user?->name} (bulk upload).{$source} \"{$this->data['source_quote']}\""
            .(filled($record->getOriginal('source_notes')) ? "\n\n".$record->getOriginal('source_notes') : '');

        if ($problems = RatePublicationRules::problems($record)) {
            throw new RowImportFailedException(implode(' ', $problems));
        }

        if (! $user?->can($record->exists ? 'update' : 'create', $record->exists ? $record : LenderProductRate::class)) {
            throw new RowImportFailedException('You do not have permission to change interest rates.');
        }

        // A submitted rate goes back to draft to take the new figures; the
        // status column is the one thing the model lets change on it.
        if ($record->exists && $record->getOriginal('status') === RateStatus::PendingApproval) {
            LenderProductRate::query()->whereKey($record->getKey())->update(['status' => RateStatus::Draft->value]);
            $record->status = RateStatus::Draft;
            $record->syncOriginalAttribute('status');
        }
    }

    protected function afterSave(): void
    {
        $user = $this->import->user;

        if (! ($this->options['publish'] ?? false) || ! $user?->can('publish', LenderProductRate::class)) {
            return;
        }

        try {
            RateWorkflow::publish($this->record, $user);
        } catch (RateWorkflowException $exception) {
            throw new RowImportFailedException('Saved as a verified draft but not published: '.implode(' ', $exception->problems));
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your verified rates upload has finished: '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' saved.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' could not be saved — download the failed rows to see why.';
        }

        return $body;
    }

    /**
     * The draft to write this row into: the rate itself while it is still a
     * draft or submitted, otherwise a new (unsaved) version of it — or the
     * series' open draft, if someone already started one.
     */
    private function editable(LenderProductRate $rate): LenderProductRate
    {
        if (in_array($rate->status, [RateStatus::Draft, RateStatus::PendingApproval], true)) {
            return $rate;
        }

        $versions = LenderProductRate::query()->where('series_id', $rate->series_id)->get();
        $open = $versions->first(fn (LenderProductRate $version) => in_array($version->status, [RateStatus::Draft, RateStatus::PendingApproval], true));

        if ($open !== null) {
            return $open;
        }

        $draft = $rate->replicate([
            'public_id', 'version', 'status', 'review_note', 'created_by', 'updated_by',
            'submitted_by', 'submitted_at', 'published_by', 'published_at', 'archived_at',
        ]);
        $draft->version = (int) $versions->max('version') + 1;
        $draft->status = RateStatus::Draft;

        return $draft;
    }

    private function resolveOffer(): LenderProduct
    {
        $this->lenders ??= Lender::query()->get()
            ->flatMap(fn (Lender $lender) => [Str::lower($lender->name) => $lender, $lender->slug => $lender])
            ->all();

        $lender = $this->lenders[Str::lower(trim($this->data['lender']))] ?? $this->lenders[Str::slug($this->data['lender'])] ?? null
            ?? throw new RowImportFailedException("No lender called \"{$this->data['lender']}\".");

        $key = Str::lower(trim($this->data['loan_type']));

        // A loan product's slug or name, or else a loan type (category).
        $loanProduct = array_key_exists($key, $this->loanProducts)
            ? $this->loanProducts[$key]
            : $this->loanProducts[$key] = LoanProduct::query()->where(fn ($query) => $query->where('slug', $key)->orWhereRaw('lower(name) = ?', [$key]))->first()
                ?? LoanProduct::query()->where('category', $key)->orderByRaw('status != ?', ['published'])->orderBy('id')->first();

        if ($loanProduct === null) {
            throw new RowImportFailedException("No loan type \"{$this->data['loan_type']}\".");
        }

        $offer = LenderProduct::query()
            ->where('lender_id', $lender->id)
            ->where('loan_product_id', $loanProduct->id)
            ->first()
            ?? throw new RowImportFailedException("{$lender->name} has no {$loanProduct->name} offer. Add it in Catalog → Lender Offers first.");

        return $offer->setRelation('lender', $lender)->setRelation('loanProduct', $loanProduct);
    }

    private static function same(?string $stored, ?string $given): bool
    {
        return Str::lower(trim((string) $stored)) === Str::lower(trim((string) $given));
    }
}
