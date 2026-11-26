<?php

namespace App\Filament\Resources\DebtApplications\RelationManagers;

use App\Modules\DebtConsolidation\Enums\DebtApplicationStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatusChangesRelationManager extends RelationManager
{
    protected static string $relationship = 'statusChanges';

    protected static ?string $title = 'Status history';

    public function table(Table $table): Table
    {
        $label = fn (?string $state): string => $state === null ? '—' : (DebtApplicationStatus::tryFrom($state)?->getLabel() ?? $state);

        return $table
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('j M Y, g:i a', 'Asia/Kolkata'),
                TextColumn::make('from_status')->label('From')->formatStateUsing($label)->placeholder('—'),
                TextColumn::make('to_status')->label('To')->formatStateUsing($label)->badge(),
                TextColumn::make('actor_type')->label('By')->badge()->color('gray')->description(fn ($record): ?string => $record->user?->name),
                TextColumn::make('note')->wrap()->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
