<?php

namespace App\Filament\Resources\JourneySessions\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ResponsesRelationManager extends RelationManager
{
    protected static string $relationship = 'responses';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('field_key')
            ->columns([
                TextColumn::make('field_key')->label('Field'),
                TextColumn::make('value')
                    ->formatStateUsing(fn (Model $record) => is_array($record->value) ? implode(', ', $record->value) : (string) $record->value),
            ])
            ->defaultSort('field_key')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
