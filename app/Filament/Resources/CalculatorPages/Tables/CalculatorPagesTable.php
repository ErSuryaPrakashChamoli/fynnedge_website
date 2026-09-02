<?php

namespace App\Filament\Resources\CalculatorPages\Tables;

use App\Support\Calculators\CalculatorPageKey;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CalculatorPagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('calculator_key')
                    ->label('Calculator')
                    ->formatStateUsing(fn (string $state) => CalculatorPageKey::tryFrom($state)?->getLabel() ?? $state)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')->placeholder('— default heading —'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('calculator_key')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
