<?php

namespace App\Filament\Imports;

use App\Models\Lender;
use App\Modules\Eligibility\Models\Employer;
use App\Modules\Eligibility\Models\EmployerCategory;
use App\Modules\Eligibility\Models\EmployerRating;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class EmployerRatingImporter extends Importer
{
    protected static ?string $model = EmployerRating::class;

    public static function getColumns(): array
    {
        return [
            // None of these three are real columns on EmployerRating (which only has
            // employer_id/lender_id/employer_category_id) — they're only used inside
            // resolveRecord() to locate/create the Employer, Lender and
            // EmployerCategory, so their default "assign this state to the record"
            // behaviour is disabled with a no-op fillRecordUsing().
            ImportColumn::make('employer_name')
                ->requiredMapping()
                ->rules(['required', 'max:255'])
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('lender_name')
                ->requiredMapping()
                ->rules(['required', 'max:255'])
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('category_key')
                ->requiredMapping()
                ->rules(['required', 'max:50'])
                ->fillRecordUsing(fn () => null),
        ];
    }

    /**
     * One CSV row is one (employer × lender) rating. The lender and the category
     * (which is lender-specific, set up under Lenders → Employer Categories) must
     * already exist — categories are curated per lender, so silently creating one
     * from a CSV typo would corrupt that lender's own rating scale. The employer
     * itself is created if genuinely new, matched by name.
     */
    public function resolveRecord(): EmployerRating
    {
        $lenderName = trim($this->data['lender_name']);

        $lender = Lender::query()->where('slug', Str::slug($lenderName))->first();

        if (! $lender) {
            throw new RowImportFailedException(
                "No lender found named '{$lenderName}'.",
            );
        }

        $categoryKey = trim($this->data['category_key']);

        $category = EmployerCategory::query()
            ->where('lender_id', $lender->id)
            ->where('key', $categoryKey)
            ->first();

        if (! $category) {
            throw new RowImportFailedException(
                "No '{$categoryKey}' employer category is configured for {$lender->name}. Set it up under Lenders → {$lender->name} → Employer Categories first.",
            );
        }

        $employer = Employer::query()->firstOrCreate(
            ['name' => trim($this->data['employer_name'])],
        );

        $rating = EmployerRating::query()->firstOrNew([
            'employer_id' => $employer->id,
            'lender_id' => $lender->id,
        ]);
        $rating->employer_category_id = $category->id;

        return $rating;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your employer rating import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
