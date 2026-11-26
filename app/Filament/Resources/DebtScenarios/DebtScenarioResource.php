<?php

namespace App\Filament\Resources\DebtScenarios;

use App\Filament\Clusters\Concerns\BelongsToAdminModule;
use App\Filament\Resources\DebtScenarios\Pages\ListDebtScenarios;
use App\Filament\Resources\DebtScenarios\Schemas\DebtScenarioForm;
use App\Filament\Resources\DebtScenarios\Tables\DebtScenariosTable;
use App\Modules\DebtConsolidation\Models\DebtScenario;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Debt Consolidation → Savings Comparison: the proposed consolidation loans
 * each case is compared against. Figures appear on each case's Savings
 * comparison tab after Recalculate.
 */
class DebtScenarioResource extends Resource
{
    use BelongsToAdminModule;

    protected static ?string $model = DebtScenario::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $navigationLabel = 'Savings Comparison';

    protected static ?string $modelLabel = 'consolidation scenario';

    protected static ?string $slug = 'debt-scenarios';

    public static function form(Schema $schema): Schema
    {
        return DebtScenarioForm::configure($schema, withCase: true);
    }

    public static function table(Table $table): Table
    {
        return DebtScenariosTable::configure($table)->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListDebtScenarios::route('/')];
    }
}
