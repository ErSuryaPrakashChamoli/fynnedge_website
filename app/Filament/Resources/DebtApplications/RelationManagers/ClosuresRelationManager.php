<?php

namespace App\Filament\Resources\DebtApplications\RelationManagers;

use App\Filament\Resources\DebtClosures\Tables\DebtClosuresTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class ClosuresRelationManager extends RelationManager
{
    protected static string $relationship = 'closures';

    protected static ?string $title = 'Debt closure';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return DebtClosuresTable::configure($table, withCase: false)
            ->description('Opened automatically when a lender application is marked disbursed. A loan counts as closed only at "Closure verified": confirmed payment and, for full closures, a verified NOC.');
    }
}
