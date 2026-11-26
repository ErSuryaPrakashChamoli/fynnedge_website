<?php

namespace App\Filament\Resources\DebtLiabilities;

use App\Filament\Clusters\Concerns\BelongsToAdminModule;
use App\Filament\Resources\DebtLiabilities\Pages\ListDebtLiabilities;
use App\Filament\Resources\DebtLiabilities\Schemas\DebtLiabilityForm;
use App\Filament\Resources\DebtLiabilities\Tables\DebtLiabilitiesTable;
use App\Modules\DebtConsolidation\Models\DebtLiability;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Debt Consolidation → Liabilities: every existing debt recorded across all
 * cases, for verification work and portfolio review.
 */
class DebtLiabilityResource extends Resource
{
    use BelongsToAdminModule;

    protected static ?string $model = DebtLiability::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $navigationLabel = 'Liabilities';

    protected static ?string $modelLabel = 'liability';

    protected static ?string $slug = 'debt-liabilities';

    public static function form(Schema $schema): Schema
    {
        return DebtLiabilityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DebtLiabilitiesTable::configure($table)->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('application');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListDebtLiabilities::route('/')];
    }
}
