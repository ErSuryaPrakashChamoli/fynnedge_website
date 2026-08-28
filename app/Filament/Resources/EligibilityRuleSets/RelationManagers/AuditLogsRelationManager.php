<?php

namespace App\Filament\Resources\EligibilityRuleSets\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AuditLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'auditLogs';

    protected static ?string $title = 'History';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('action')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('user.name')->label('By')->placeholder('System'),
                TextColumn::make('changes')
                    ->label('Changes')
                    ->formatStateUsing(function (Model $record) {
                        if (! $record->changes) {
                            return '—';
                        }

                        return collect($record->changes)
                            ->map(fn ($value, $key) => is_scalar($value) || $value === null
                                ? "{$key}: ".json_encode($value)
                                : "{$key}: updated")
                            ->implode(', ');
                    })
                    ->wrap()
                    ->limit(120),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
