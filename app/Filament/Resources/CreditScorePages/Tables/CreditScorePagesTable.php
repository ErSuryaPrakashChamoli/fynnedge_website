<?php

namespace App\Filament\Resources\CreditScorePages\Tables;

use App\Modules\CreditScore\Enums\BureauName;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CreditScorePagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bureau')
                    ->label('Page')
                    ->formatStateUsing(fn (BureauName $state): string => $state->getLabel())
                    ->description(fn (BureauName $state): string => '/credit-score/'.$state->value)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')->placeholder('— default heading —'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('bureau')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
