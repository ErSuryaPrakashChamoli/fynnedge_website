<?php

namespace App\Filament\Resources\DebtApplications\RelationManagers;

use App\Filament\Resources\DebtLiabilities\Schemas\DebtLiabilityForm;
use App\Filament\Resources\DebtLiabilities\Tables\DebtLiabilitiesTable;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class LiabilitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'liabilities';

    protected static ?string $title = 'Existing liabilities';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return DebtLiabilityForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return DebtLiabilitiesTable::configure($table, withCase: false)
            ->recordTitleAttribute('lender_name')
            ->headerActions([CreateAction::make()->label('Add liability')->modalWidth('5xl')])
            ->pushRecordActions([DeleteAction::make()]);
    }
}
