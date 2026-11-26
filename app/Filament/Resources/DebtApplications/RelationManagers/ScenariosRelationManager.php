<?php

namespace App\Filament\Resources\DebtApplications\RelationManagers;

use App\Filament\Resources\DebtScenarios\Schemas\DebtScenarioForm;
use App\Filament\Resources\DebtScenarios\Tables\DebtScenariosTable;
use App\Modules\DebtConsolidation\Enums\ActorType;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ScenariosRelationManager extends RelationManager
{
    protected static string $relationship = 'scenarios';

    protected static ?string $title = 'Scenarios';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return DebtScenarioForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return DebtScenariosTable::configure($table, withCase: false)
            ->description('With no scenarios, the standard options from Debt Consolidation Settings are compared. Press "Recalculate" after changes.')
            ->headerActions([CreateAction::make()->label('Add scenario')->mutateDataUsing(fn (array $data): array => [...$data, 'created_by_type' => ActorType::Staff, 'created_by' => auth()->id()])])
            ->pushRecordActions([DeleteAction::make()]);
    }
}
