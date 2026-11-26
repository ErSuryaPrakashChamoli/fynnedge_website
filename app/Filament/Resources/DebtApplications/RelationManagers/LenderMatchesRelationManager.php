<?php

namespace App\Filament\Resources\DebtApplications\RelationManagers;

use App\Filament\Resources\DebtLenderMatches\Tables\DebtLenderMatchesTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class LenderMatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'lenderMatches';

    protected static ?string $title = 'Lender matching';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return DebtLenderMatchesTable::configure($table, withCase: false)
            ->description('Run "Match lenders" at the top of the page to refresh. Potential match = fits the lender\'s recorded criteria; it is not an approval or an offer.');
    }
}
