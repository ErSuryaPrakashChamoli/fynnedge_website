<?php

namespace App\Filament\Resources\CalculatorPages\Tables;

use App\Models\CalculatorPage;
use App\Support\Calculators\CalculatorCatalog;
use Filament\Actions\Action;
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
                    ->formatStateUsing(fn (string $state) => CalculatorCatalog::pages()[$state] ?? $state)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('heading')->label('Headline')->placeholder('— shared headline —'),
                TextColumn::make('title')->label('About heading')->placeholder('— default heading —'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('calculator_key')
            ->recordActions([
                Action::make('view')
                    ->label('View page')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (CalculatorPage $record): string => url('calculators/'.$record->calculator_key), shouldOpenInNewTab: true),
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
