<?php

namespace App\Filament\Resources\DebtApplications\RelationManagers;

use App\Filament\Resources\DebtLenderApplications\Tables\DebtLenderApplicationsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class LenderApplicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'lenderApplications';

    protected static ?string $title = 'Lender applications';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return DebtLenderApplicationsTable::configure($table, withCase: false)
            ->description('Open a new one with "More → New lender application" (needs the customer\'s consent for that lender). Each lender\'s status is independent of the case status.');
    }
}
