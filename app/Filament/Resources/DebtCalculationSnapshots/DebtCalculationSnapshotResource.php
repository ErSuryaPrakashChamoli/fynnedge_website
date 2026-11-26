<?php

namespace App\Filament\Resources\DebtCalculationSnapshots;

use App\Filament\Clusters\Concerns\BelongsToAdminModule;
use App\Filament\Resources\DebtCalculationSnapshots\Pages\ListDebtCalculationSnapshots;
use App\Filament\Resources\DebtCalculationSnapshots\Pages\ViewDebtCalculationSnapshot;
use App\Filament\Resources\DebtCalculationSnapshots\Tables\DebtCalculationSnapshotsTable;
use App\Modules\DebtConsolidation\Models\DebtCalculationSnapshot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Debt Consolidation → Financial Assessments: every stored calculation
 * (immutable), with formula and scoring versions — the record reports are
 * built from.
 */
class DebtCalculationSnapshotResource extends Resource
{
    use BelongsToAdminModule;

    protected static ?string $model = DebtCalculationSnapshot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?string $navigationLabel = 'Financial Assessments';

    protected static ?string $modelLabel = 'financial assessment';

    protected static ?string $slug = 'debt-assessments';

    public static function table(Table $table): Table
    {
        return DebtCalculationSnapshotsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['application', 'creator']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDebtCalculationSnapshots::route('/'),
            'view' => ViewDebtCalculationSnapshot::route('/{record}'),
        ];
    }
}
