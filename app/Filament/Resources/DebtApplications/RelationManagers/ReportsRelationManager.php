<?php

namespace App\Filament\Resources\DebtApplications\RelationManagers;

use App\Filament\Resources\DebtReports\Tables\DebtReportsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class ReportsRelationManager extends RelationManager
{
    protected static string $relationship = 'reports';

    protected static ?string $title = 'Reports';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return DebtReportsTable::configure($table, withCase: false)->description('Generate with "More → Generate report". Reports from a calculation always show that calculation\'s figures.');
    }
}
