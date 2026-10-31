<?php

namespace App\Filament\Resources\Redirects\Tables;

use App\Models\Redirect;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RedirectsTable
{
    private const TYPES = [
        Redirect::PERMANENT => '301 Permanent',
        Redirect::TEMPORARY => '302 Temporary',
        Redirect::GONE => '410 Gone',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source_path')
                    ->label('Old path')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('destination')
                    ->label('Goes to')
                    ->searchable()
                    ->placeholder('Nowhere (410 Gone)')
                    ->wrap(),
                TextColumn::make('status_code')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => self::TYPES[$state] ?? (string) $state)
                    ->color(fn (int $state): string => match ($state) {
                        Redirect::PERMANENT => 'success',
                        Redirect::GONE => 'gray',
                        default => 'warning',
                    }),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('hits')
                    ->label('Times used')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('last_used_at')
                    ->label('Last used')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Never')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(60)
                    ->tooltip(fn (Redirect $record): ?string => $record->notes)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('source_path')
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
                SelectFilter::make('status_code')
                    ->label('Type')
                    ->options(self::TYPES),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
