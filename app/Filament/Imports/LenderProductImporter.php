<?php

namespace App\Filament\Imports;

use App\Enums\EmploymentType;
use App\Enums\LenderStatus;
use App\Enums\LenderType;
use App\Models\Lender;
use App\Models\LenderProduct;
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
            ImportColumn::make('interest_rate_from')->numeric()->rules(['nullable', 'numeric']),
            ImportColumn::make('interest_rate_to')->numeric()->rules(['nullable', 'numeric']),
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
        $loanProduct = LoanProduct::query()
            ->where('slug', $this->data['loan_product_slug'])
            ->first();

        if (! $loanProduct) {
            throw new RowImportFailedException(
                "No loan product found with slug '{$this->data['loan_product_slug']}'.",
            );
        }

        $lender = Lender::query()->firstOrCreate(
            ['slug' => Str::slug($this->data['lender_name'])],
            [
                'name' => $this->data['lender_name'],
                'type' => LenderType::tryFrom($this->data['lender_type'] ?? ''),
                'status' => LenderStatus::Active,
            ],
        );

        return LenderProduct::query()->firstOrNew([
            'lender_id' => $lender->id,
            'loan_product_id' => $loanProduct->id,
        ]);
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
