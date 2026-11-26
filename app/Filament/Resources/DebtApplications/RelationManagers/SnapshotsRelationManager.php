<?php

namespace App\Filament\Resources\DebtApplications\RelationManagers;

use App\Filament\Resources\DebtCalculationSnapshots\Tables\DebtCalculationSnapshotsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class SnapshotsRelationManager extends RelationManager
{
    protected static string $relationship = 'snapshots';

    protected static ?string $title = 'Calculation history';

    public function table(Table $table): Table
    {
        return DebtCalculationSnapshotsTable::configure($table, withCase: false);
    }
}
