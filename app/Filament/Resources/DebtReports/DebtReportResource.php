<?php

namespace App\Filament\Resources\DebtReports;

use App\Filament\Clusters\Concerns\BelongsToAdminModule;
use App\Filament\Resources\DebtReports\Pages\ListDebtReports;
use App\Filament\Resources\DebtReports\Tables\DebtReportsTable;
use App\Modules\DebtConsolidation\Models\DebtReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Debt Consolidation → Reports: every generated PDF (encrypted, versioned,
 * with SHA-256), its download and its release to the customer.
 */
class DebtReportResource extends Resource
{
    use BelongsToAdminModule;

    protected static ?string $model = DebtReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Reports';

    protected static ?string $modelLabel = 'report';

    protected static ?string $slug = 'debt-reports';

    public static function table(Table $table): Table
    {
        return DebtReportsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['application', 'generator']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListDebtReports::route('/')];
    }
}
