<?php

namespace App\Filament\Resources\DebtLenderMatches;

use App\Filament\Clusters\Concerns\BelongsToAdminModule;
use App\Filament\Resources\DebtLenderMatches\Pages\ListDebtLenderMatches;
use App\Filament\Resources\DebtLenderMatches\Tables\DebtLenderMatchesTable;
use App\Modules\DebtConsolidation\Models\DebtLenderMatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Debt Consolidation → Lender Matching: how each case compares with each
 * lender's debt consolidation policy, and the advisor's review. A match is
 * never an approval.
 */
class DebtLenderMatchResource extends Resource
{
    use BelongsToAdminModule;

    protected static ?string $model = DebtLenderMatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $navigationLabel = 'Lender Matching';

    protected static ?string $modelLabel = 'lender match';

    protected static ?string $slug = 'debt-lender-matches';

    public static function table(Table $table): Table
    {
        return DebtLenderMatchesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['application', 'lenderProduct.lender', 'lenderProduct.loanProduct']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListDebtLenderMatches::route('/')];
    }
}
