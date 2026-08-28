<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Modules\Journey\Enums\JourneySessionStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JourneySessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'journeySessions';

    protected static ?string $title = 'Applications';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('loanProduct.name')->label('Product'),
                TextColumn::make('currentStep.title')->label('Current step'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (JourneySessionStatus $state) => match ($state) {
                        JourneySessionStatus::Completed => 'success',
                        JourneySessionStatus::InProgress => 'warning',
                        JourneySessionStatus::Abandoned => 'gray',
                    }),
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('completed_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
