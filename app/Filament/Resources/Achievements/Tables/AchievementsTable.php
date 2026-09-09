<?php

namespace App\Filament\Resources\Achievements\Tables;

use App\Enums\PublishStatus;
use App\Models\Achievement;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AchievementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')->label('Order')->sortable(),
                TextColumn::make('label')->label('Metric name')->searchable(),
                TextColumn::make('value')
                    ->label('Shown as')
                    ->state(fn (Achievement $record): string => $record->displayValue()),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (PublishStatus $state) => match ($state) {
                        PublishStatus::Published => 'success',
                        PublishStatus::Draft => 'gray',
                    }),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                SelectFilter::make('status')->options(PublishStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
