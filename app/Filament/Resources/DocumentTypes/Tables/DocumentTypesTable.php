<?php

namespace App\Filament\Resources\DocumentTypes\Tables;

use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->searchable()->sortable(),
                TextColumn::make('label')->searchable(),
                TextColumn::make('order')->sortable(),
                TextColumn::make('requirements_count')
                    ->label('Used by')
                    ->counts('requirements')
                    ->alignCenter(),
            ])
            ->defaultSort('order')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
