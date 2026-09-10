<?php

namespace App\Filament\Resources\Redirects\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RedirectsTable
{
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
                    ->wrap(),
                TextColumn::make('status_code')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => $state === 301 ? '301 Permanent' : '302 Temporary')
                    ->color(fn (int $state): string => $state === 301 ? 'success' : 'warning'),
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
            ])
            ->defaultSort('source_path')
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
                TernaryFilter::make('status_code')
                    ->label('Type')
                    ->placeholder('All')
                    ->trueLabel('301 Permanent')
                    ->falseLabel('302 Temporary')
                    ->queries(
                        true: fn ($query) => $query->where('status_code', 301),
                        false: fn ($query) => $query->where('status_code', 302),
                        blank: fn ($query) => $query,
                    ),
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
